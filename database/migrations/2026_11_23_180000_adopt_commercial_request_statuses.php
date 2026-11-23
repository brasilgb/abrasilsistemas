<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Situações comerciais das solicitações (ABRASIL-COMERCIAL-02): novo, em atendimento, proposta,
 * negociação, ganho, perdido e arquivada. "Respondida" vira "Em atendimento" e "Concluída" vira
 * "Arquivada": concluída não quer dizer venda, então nenhum ganho é presumido.
 *
 * Nenhuma coluna muda. A situação anterior fica no histórico da solicitação e é o que o down() usa.
 */
return new class extends Migration
{
    private const MAP = [
        'answered' => ['to' => 'in_progress', 'from_label' => 'Respondida', 'to_label' => 'Em atendimento'],
        'done' => ['to' => 'archived', 'from_label' => 'Concluída', 'to_label' => 'Arquivada'],
    ];

    /** Situações novas sem equivalente no painel anterior, usadas só ao reverter. */
    private const ROLLBACK = ['proposal' => 'in_progress', 'negotiation' => 'in_progress', 'won' => 'done', 'lost' => 'archived'];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (self::MAP as $from => $map) {
                DB::table('leads')->where('source', 'site')->where('request_status', $from)->orderBy('id')->select('id')
                    ->chunkById(500, function ($leads) use ($from, $map) {
                        DB::table('lead_activities')->insert($leads->map(fn ($lead) => [
                            'lead_id' => $lead->id,
                            'type' => 'status',
                            'status' => 'migrated:'.$from,
                            'description' => "Situação ajustada na atualização do painel: {$map['from_label']} → {$map['to_label']}",
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])->all());
                        DB::table('leads')->whereIn('id', $leads->pluck('id'))->update(['request_status' => $map['to']]);
                    });
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            foreach (self::MAP as $from => $map) {
                $activities = DB::table('lead_activities')->where('type', 'status')->where('status', 'migrated:'.$from)->pluck('lead_id', 'id');

                // Só volta quem não mudou de situação depois da migração.
                foreach ($activities->chunk(500) as $chunk) {
                    DB::table('leads')->whereIn('id', $chunk->values())->where('request_status', $map['to'])->update(['request_status' => $from]);
                    DB::table('lead_activities')->whereIn('id', $chunk->keys())->delete();
                }
            }

            foreach (self::ROLLBACK as $status => $previous) {
                DB::table('leads')->where('source', 'site')->where('request_status', $status)->update(['request_status' => $previous]);
            }
        });
    }
};
