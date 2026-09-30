<?php

namespace App\Console\Commands;

use App\Services\MapsPlace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Preenche google_place_id, maps_feature_id, latitude e longitude dos leads antigos a partir do
 * próprio `maps_url` (CRM-LEAD-02). Só lê o texto da URL: nenhuma chamada externa. Sem --apply
 * só relata. Com --apply, grava apenas as colunas vazias (nunca sobrescreve) e não mexe em
 * updated_at. Inclui os leads excluídos (soft delete), para que uma recaptura os reconheça.
 */
class BackfillLeadMapsPlace extends Command
{
    protected $signature = 'leads:backfill-maps-place {--apply : Grava as colunas vazias (sem a opção, só relata)}';

    protected $description = 'Extrai Place ID, FTID e coordenadas do link do Google Maps dos leads, sem chamadas externas';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $stats = [
            'with_url' => 0,
            'place_id' => 0,
            'feature_id' => 0,
            'coordinates' => 0,
            'no_identity' => 0,
            'to_fill' => 0,
            'complete' => 0,
            'conflicts' => 0,
        ];
        $placeIds = [];

        DB::table('leads')
            ->whereNotNull('maps_url')
            ->where('maps_url', '<>', '')
            ->select(['id', 'maps_url', 'google_place_id', 'maps_feature_id', 'latitude', 'longitude'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($apply, &$stats, &$placeIds): void {
                foreach ($rows as $row) {
                    $stats['with_url']++;
                    $place = MapsPlace::fromUrl($row->maps_url);

                    $stats['place_id'] += (int) ($place->placeId !== null);
                    $stats['feature_id'] += (int) ($place->featureId !== null);
                    $stats['coordinates'] += (int) ($place->latitude !== null);
                    $stats['no_identity'] += (int) ! $place->hasIdentity();

                    if ($place->placeId !== null) {
                        $placeIds[$place->placeId][] = $row->id;
                    }

                    $changes = [];

                    foreach ($place->toAttributes() as $column => $value) {
                        if ($value === null) {
                            continue;
                        }

                        if ($row->{$column} === null) {
                            $changes[$column] = $value;
                        } elseif (! self::sameValue($row->{$column}, $value)) {
                            $stats['conflicts']++;
                            $this->warn("Lead #{$row->id}: {$column} gravado difere do link do Maps (mantido).");
                        }
                    }

                    if ($changes === []) {
                        $stats['complete']++;

                        continue;
                    }

                    $stats['to_fill']++;

                    if ($apply) {
                        DB::table('leads')->where('id', $row->id)->update($changes);
                    }
                }
            });

        $repeated = array_filter($placeIds, fn (array $ids) => count($ids) > 1);

        $this->table(['Leads (inclusive excluídos)', 'Total'], [
            ['Com link do Maps', $stats['with_url']],
            ['… com Place ID no link', $stats['place_id']],
            ['… com FTID no link', $stats['feature_id']],
            ['… com coordenadas no link', $stats['coordinates']],
            ['… sem identificador (ex.: URL de busca)', $stats['no_identity']],
            [$apply ? 'Preenchidos agora' : 'A preencher', $stats['to_fill']],
            ['Já completos ou nada a extrair', $stats['complete']],
            ['Valor gravado diferente do link (mantido)', $stats['conflicts']],
            ['Place IDs repetidos em mais de um lead', count($repeated)],
        ]);

        foreach ($repeated as $ids) {
            $this->line('Mesmo Place ID nos leads #'.implode(', #', $ids).' (não mesclados).');
        }

        $this->info($apply
            ? "{$stats['to_fill']} lead(s) atualizado(s). Nenhuma chamada externa foi feita."
            : 'Simulação (dry-run): nada foi gravado. Use --apply para gravar.');

        return self::SUCCESS;
    }

    private static function sameValue(mixed $stored, string|float $value): bool
    {
        return is_float($value)
            ? abs((float) $stored - $value) < 0.0000001
            : $stored === $value;
    }
}
