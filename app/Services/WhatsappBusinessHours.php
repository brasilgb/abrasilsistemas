<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;

/**
 * Horário de atendimento do SLA da fila "Aguardando resposta": o relógio do SLA só avança dentro
 * da janela de cada dia da semana e fica congelado fora dela (noite, dias fechados).
 *
 * Fica na tabela `settings` (chave/valor), como o WhatsApp da empresa. Os instantes continuam em
 * UTC no banco; só a divisão em dias e horários usa o fuso operacional configurado aqui, sem
 * mexer no fuso global do app. Fonte única do cálculo: filtro sla, contador de críticos e o
 * tempo exibido na fila (a página recebe os segundos já calculados e os próximos períodos).
 * Feriados não são considerados.
 */
class WhatsappBusinessHours
{
    /**
     * Fallback enquanto nada foi salvo em settings (a tela avisa "horário padrão"): segunda a
     * sexta, 08:00–18:00, no fuso da equipe comercial. Não é o horário real da empresa.
     */
    public const DEFAULTS = [
        'timezone' => Lead::FOLLOW_UP_TIMEZONE,
        'days' => [
            1 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            2 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            3 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            4 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            5 => ['active' => true, 'opens_at' => '08:00', 'closes_at' => '18:00'],
            6 => ['active' => false, 'opens_at' => '08:00', 'closes_at' => '12:00'],
            7 => ['active' => false, 'opens_at' => '08:00', 'closes_at' => '12:00'],
        ],
    ];

    /** Dias ISO (1 = segunda … 7 = domingo). */
    public const DAY_LABELS = [
        1 => 'Seg',
        2 => 'Ter',
        3 => 'Qua',
        4 => 'Qui',
        5 => 'Sex',
        6 => 'Sáb',
        7 => 'Dom',
    ];

    /** HH:MM; 24:00 fecha no fim do dia (atendimento até a meia-noite). */
    public const TIME_PATTERN = '/^(([01]\d|2[0-3]):[0-5]\d|24:00)$/';

    /** Limite da busca para trás no cálculo do corte (evita laço sem fim com horários mínimos). */
    private const MAX_LOOKBACK_DAYS = 3660;

    /** Limite da busca para frente em addBusinessSeconds (mesmo motivo). */
    private const MAX_LOOKAHEAD_DAYS = 3660;

    /** @var array{timezone: string, days: array<int, array{active: bool, opens_at: string, closes_at: string}>, configured: bool}|null */
    private ?array $current = null;

    /**
     * Configuração em vigor. configured = false: nada salvo, vale o fallback DEFAULTS. Fuso ou dia
     * salvo inválido volta ao padrão daquele campo.
     *
     * @return array{timezone: string, days: array<int, array{active: bool, opens_at: string, closes_at: string}>, configured: bool}
     */
    public function current(): array
    {
        if ($this->current !== null) {
            return $this->current;
        }

        $values = Setting::query()
            ->whereIn('key', [Setting::WHATSAPP_SLA_TIMEZONE, Setting::WHATSAPP_SLA_WEEK])
            ->pluck('value', 'key');

        $timezone = (string) $values->get(Setting::WHATSAPP_SLA_TIMEZONE);
        $saved = json_decode((string) $values->get(Setting::WHATSAPP_SLA_WEEK), true);
        $days = [];

        foreach (self::DEFAULTS['days'] as $day => $default) {
            $days[$day] = $this->validDay(is_array($saved) ? ($saved[$day] ?? null) : null) ?? $default;
        }

        return $this->current = [
            'timezone' => in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : self::DEFAULTS['timezone'],
            'days' => $days,
            'configured' => $values->isNotEmpty(),
        ];
    }

    /**
     * @param  array{timezone: string, days: array<int, array{active: bool, opens_at: string, closes_at: string}>}  $data
     */
    public function save(array $data): void
    {
        Setting::query()->updateOrCreate(['key' => Setting::WHATSAPP_SLA_TIMEZONE], ['value' => $data['timezone']]);
        Setting::query()->updateOrCreate(['key' => Setting::WHATSAPP_SLA_WEEK], ['value' => json_encode($data['days'])]);

        $this->current = null;
    }

    /**
     * Segundos de atendimento entre dois instantes (businessElapsed). Frações de segundo são
     * descartadas, como no created_at gravado no banco. Início no futuro: zero.
     */
    public function elapsedSeconds(CarbonInterface $start, CarbonInterface $now): int
    {
        $start = CarbonImmutable::instance($start)->startOfSecond();
        $now = CarbonImmutable::instance($now)->startOfSecond();

        return array_sum(array_map(
            fn (array $period) => $period[1]->getTimestamp() - $period[0]->getTimestamp(),
            $this->periodsBetween($start, $now),
        ));
    }

    /** Minutos de atendimento completos entre dois instantes (businessMinutesBetween). */
    public function elapsedMinutes(CarbonInterface $start, CarbonInterface $now): int
    {
        return intdiv($this->elapsedSeconds($start, $now), 60);
    }

    /**
     * Instante mais recente em que uma espera precisa ter começado para somar ao menos $minutes
     * minutos de atendimento até $now: elapsed(inicio, now) >= minutos equivale a inicio <= corte.
     * É o que permite ao filtro sla continuar um EXISTS com created_at <= corte.
     * Nulo quando nenhuma espera alcança esse tempo (nenhum dia de atendimento ativo).
     */
    public function cutoff(CarbonInterface $now, int $minutes): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($now)->startOfSecond();
        $remaining = $minutes * 60;

        if ($remaining <= 0) {
            return $now;
        }

        if (! $this->hasActiveDays()) {
            return null;
        }

        $date = $now->setTimezone($this->current()['timezone'])->startOfDay();

        for ($day = 0; $day <= self::MAX_LOOKBACK_DAYS; $day++, $date = $date->subDay()) {
            foreach ($this->periodsOn($date) as [$start, $end]) {
                $end = $end->min($now);

                if ($end <= $start) {
                    continue;
                }

                $available = $end->getTimestamp() - $start->getTimestamp();

                if ($available >= $remaining) {
                    return $end->subSeconds($remaining)->setTimezone(config('app.timezone'));
                }

                $remaining -= $available;
            }
        }

        return null;
    }

    /**
     * Inverso de elapsedSeconds: instante real em que, a partir de $start, se acumulam $seconds
     * de atendimento (elapsedSeconds($start, resultado) === $seconds). Anda só pelos períodos de
     * atendimento, dia a dia no fuso operacional; noite e dias fechados não consomem tempo. Se o
     * tempo acaba exatamente no fechamento, o resultado é o fechamento (não a próxima abertura).
     * Frações de segundo do início são descartadas, como em elapsedSeconds. Nulo quando o tempo
     * nunca é alcançado (nenhum dia de atendimento ativo).
     */
    public function addBusinessSeconds(CarbonInterface $start, int $seconds): ?CarbonImmutable
    {
        $start = CarbonImmutable::instance($start)->startOfSecond();
        $remaining = $seconds;

        if ($remaining <= 0) {
            return $start->setTimezone(config('app.timezone'));
        }

        if (! $this->hasActiveDays()) {
            return null;
        }

        $date = $start->setTimezone($this->current()['timezone'])->startOfDay();

        for ($day = 0; $day <= self::MAX_LOOKAHEAD_DAYS; $day++, $date = $date->addDay()) {
            foreach ($this->periodsOn($date) as [$periodStart, $end]) {
                $periodStart = $periodStart->max($start);

                if ($end <= $periodStart) {
                    continue;
                }

                $available = $end->getTimestamp() - $periodStart->getTimestamp();

                if ($available >= $remaining) {
                    return $periodStart->addSeconds($remaining)->setTimezone(config('app.timezone'));
                }

                $remaining -= $available;
            }
        }

        return null;
    }

    /** Se o relógio do SLA está andando neste instante. */
    public function isOpen(CarbonInterface $at): bool
    {
        $at = CarbonImmutable::instance($at);

        foreach ($this->periodsOn($at->setTimezone($this->current()['timezone'])->startOfDay()) as [$start, $end]) {
            if ($at >= $start && $at < $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Períodos de atendimento (UTC) dentro de [from, to], recortados nas pontas. Percorre só os
     * dias do intervalo, uma janela por dia (nada de laço por minuto).
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    public function periodsBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $from = CarbonImmutable::instance($from);
        $to = CarbonImmutable::instance($to);
        $timezone = $this->current()['timezone'];
        $periods = [];

        if ($to <= $from) {
            return [];
        }

        $last = $to->setTimezone($timezone)->startOfDay();

        for ($date = $from->setTimezone($timezone)->startOfDay(); $date <= $last; $date = $date->addDay()) {
            foreach ($this->periodsOn($date) as [$start, $end]) {
                $start = $start->max($from);
                $end = $end->min($to);

                if ($start < $end) {
                    $periods[] = [$start->setTimezone(config('app.timezone')), $end->setTimezone(config('app.timezone'))];
                }
            }
        }

        return $periods;
    }

    /**
     * Resumo para a tela, ex.: "Seg a Sex, 08:00–18:00; Sáb, 08:00–12:00 (America/Sao_Paulo)".
     * Sem configuração salva, avisa que é o horário padrão.
     */
    public function description(): string
    {
        $config = $this->current();
        $groups = [];

        // Dias seguidos com o mesmo horário viram um grupo.
        foreach ($config['days'] as $day => ['active' => $active, 'opens_at' => $opensAt, 'closes_at' => $closesAt]) {
            if (! $active) {
                continue;
            }

            $hours = "{$opensAt}–{$closesAt}";
            $lastGroup = array_key_last($groups);

            if ($lastGroup !== null && $groups[$lastGroup]['hours'] === $hours && $groups[$lastGroup]['last'] === $day - 1) {
                $groups[$lastGroup]['last'] = $day;
            } else {
                $groups[] = ['first' => $day, 'last' => $day, 'hours' => $hours];
            }
        }

        if ($groups === []) {
            return 'Nenhum dia de atendimento configurado';
        }

        $text = implode('; ', array_map(fn (array $group) => match ($group['last'] - $group['first']) {
            0 => self::DAY_LABELS[$group['first']],
            1 => self::DAY_LABELS[$group['first']].' e '.self::DAY_LABELS[$group['last']],
            default => self::DAY_LABELS[$group['first']].' a '.self::DAY_LABELS[$group['last']],
        }.", {$group['hours']}", $groups));

        return $text.' ('.$config['timezone'].($config['configured'] ? '' : ', horário padrão').')';
    }

    private function hasActiveDays(): bool
    {
        return array_filter(array_column($this->current()['days'], 'active')) !== [];
    }

    /**
     * Janela de atendimento de um dia local (início do dia no fuso operacional).
     *
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function periodsOn(CarbonImmutable $date): array
    {
        $day = $this->current()['days'][$date->isoWeekday()];

        if (! $day['active']) {
            return [];
        }

        $at = fn (string $time): CarbonImmutable => $time === '24:00'
            ? $date->addDay()->startOfDay()
            : $date->setTimeFromTimeString($time);

        return [[$at($day['opens_at']), $at($day['closes_at'])]];
    }

    /** @return array{active: bool, opens_at: string, closes_at: string}|null */
    private function validDay(mixed $day): ?array
    {
        if (! is_array($day) || ! is_bool($day['active'] ?? null)) {
            return null;
        }

        $opensAt = $day['opens_at'] ?? null;
        $closesAt = $day['closes_at'] ?? null;

        if (! is_string($opensAt) || ! is_string($closesAt)
            || preg_match(self::TIME_PATTERN, $opensAt) !== 1 || preg_match(self::TIME_PATTERN, $closesAt) !== 1
            || $opensAt >= $closesAt) {
            return null;
        }

        return ['active' => $day['active'], 'opens_at' => $opensAt, 'closes_at' => $closesAt];
    }
}
