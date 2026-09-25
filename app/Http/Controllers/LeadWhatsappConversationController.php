<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\LeadWhatsappMessagesRequest;
use App\Http\Requests\Leads\MarkLeadWhatsappReadRequest;
use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class LeadWhatsappConversationController extends Controller
{
    /** Tamanho da página: a abertura e cada "Carregar mensagens anteriores". */
    public const MESSAGES_LIMIT = 100;

    /** Máximo de mensagens alteradas num ciclo de polling; acima disso, a tela recarrega a página recente. */
    public const CHANGES_LIMIT = 200;

    /**
     * Folga (s) do cursor: updated_at tem precisão de segundo e uma gravação pode terminar
     * logo depois da consulta. Repetir mensagens é inofensivo (a tela faz merge por id).
     */
    public const CURSOR_OVERLAP_SECONDS = 2;

    private const FIELDS = ['id', 'direction', 'description', 'created_at', 'message_status', 'read_at'];

    /**
     * Mensagens da conversa do lead, em ordem cronológica. Sem efeito colateral:
     * a leitura interna (read_at) só muda pelo markRead, com o que a tela de fato exibiu.
     *
     * - sem parâmetros: as mensagens mais recentes;
     * - before_id: a página anterior a essa mensagem;
     * - since: mensagens criadas ou alteradas (ACK, leitura) desde o cursor anterior.
     */
    public function index(LeadWhatsappMessagesRequest $request, Lead $lead): JsonResponse
    {
        // Calculado antes da consulta: o que for gravado durante ela entra no próximo ciclo.
        $cursor = now()->subSeconds(self::CURSOR_OVERLAP_SECONDS)->getTimestamp();

        if ($request->filled('since')) {
            return $this->changes($lead, $request->integer('since'), $cursor);
        }

        $query = $lead->activities()->conversation();

        if ($request->filled('before_id')) {
            $anchor = LeadActivity::query()->findOrFail($request->integer('before_id'));

            $query->where(fn (Builder $older) => $older
                ->where('created_at', '<', $anchor->created_at)
                ->orWhere(fn (Builder $sameSecond) => $sameSecond
                    ->where('created_at', $anchor->created_at)
                    ->where('id', '<', $anchor->id)));
        }

        $page = $query
            ->latest()
            ->latest('id')
            ->limit(self::MESSAGES_LIMIT + 1)
            ->get(self::FIELDS);

        return response()->json([
            'messages' => $page->take(self::MESSAGES_LIMIT)->reverse()->values(),
            'has_more' => $page->count() > self::MESSAGES_LIMIT,
            'cursor' => $cursor,
        ]);
    }

    /**
     * Marca como lidas as mensagens recebidas exibidas na conversa (id <= up_to_id).
     * Idempotente e restrito ao lead da rota; message_status (ACK) nunca é tocado.
     */
    public function markRead(MarkLeadWhatsappReadRequest $request, Lead $lead): JsonResponse
    {
        $marked = $lead->unreadWhatsappMessages()
            ->where('id', '<=', $request->integer('up_to_id'))
            ->update(['read_at' => now()]);

        return response()->json(['marked' => $marked]);
    }

    /**
     * Polling incremental: tudo o que mudou na conversa desde o cursor, inclusive o ACK de
     * mensagens já exibidas (o update do status toca o updated_at).
     */
    private function changes(Lead $lead, int $since, int $cursor): JsonResponse
    {
        $changes = $lead->activities()
            ->conversation()
            ->where('updated_at', '>=', Carbon::createFromTimestamp($since, config('app.timezone')))
            ->oldest()
            ->oldest('id')
            ->limit(self::CHANGES_LIMIT + 1)
            ->get(self::FIELDS);

        // Mudança em massa (ex.: muitas mensagens marcadas como lidas): a tela recarrega a página recente.
        if ($changes->count() > self::CHANGES_LIMIT) {
            return response()->json(['messages' => [], 'reset' => true, 'cursor' => $cursor]);
        }

        return response()->json(['messages' => $changes, 'reset' => false, 'cursor' => $cursor]);
    }
}
