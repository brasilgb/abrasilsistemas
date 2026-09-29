<?php

use App\Models\Lead;
use App\Models\LeadAutomationExecution;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
 * CRM-WA-18: reserva idempotente de execução de automação por lead + automation_key,
 * protegida por prospect.token. A garantia é o UNIQUE (lead_id, automation_key) do banco.
 */

beforeEach(function () {
    // 28/09/2026 12:00 em São Paulo (15:00 UTC).
    $this->travelTo(now()->setDate(2026, 9, 28)->setTime(15, 0, 0));
});

function automationExecutionToken(): array
{
    return ['Authorization' => 'Bearer '.config('services.ab_prospect.token')];
}

/** @param  array<string, mixed>  $attributes */
function automationExecutionLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'company_name' => 'Lead reserva '.uniqid(),
        'product' => 'vetoros',
        'status' => 'meeting',
        'whatsapp' => '5551995179173',
        ...$attributes,
    ]);
}

/**
 * SQL executado a partir de agora, inclusive o que falha (o query log só guarda os que dão certo).
 *
 * @return Collection<int, string>
 */
function automationExecutionRecordQueries(): Collection
{
    $queries = new Collection;
    DB::connection()->beforeExecuting(fn (string $sql) => $queries->push($sql));

    return $queries;
}

function automationExecutionUrl(Lead|int $lead): string
{
    return route('api.prospects.automation-executions.reserve', $lead);
}

test('a valid token reserves the automation key once', function () {
    $lead = automationExecutionLead();

    $this->postJson(automationExecutionUrl($lead), ['automation_key' => 'trial_expired:2026-10-05'], automationExecutionToken())
        ->assertCreated()
        ->assertExactJson(['reserved' => true]);

    $execution = LeadAutomationExecution::query()->sole();

    expect($execution->lead_id)->toBe($lead->id)
        ->and($execution->automation_key)->toBe('trial_expired:2026-10-05')
        ->and($execution->executed_at->toIso8601ZuluString())->toBe('2026-09-28T15:00:00Z');
});

test('repeating the same key is recognized without creating another record', function () {
    $lead = automationExecutionLead();
    $payload = ['automation_key' => 'follow_up:2026-09-30T17:00:00Z'];

    $this->postJson(automationExecutionUrl($lead), $payload, automationExecutionToken())
        ->assertCreated()
        ->assertExactJson(['reserved' => true]);

    $this->travel(10)->minutes();

    $this->postJson(automationExecutionUrl($lead), $payload, automationExecutionToken())
        ->assertOk()
        ->assertExactJson(['reserved' => false]);
    $this->postJson(automationExecutionUrl($lead), $payload, automationExecutionToken())
        ->assertOk()
        ->assertExactJson(['reserved' => false]);

    $execution = LeadAutomationExecution::query()->sole();

    // A repetição não sobrescreve a reserva original.
    expect($execution->executed_at->toIso8601ZuluString())->toBe('2026-09-28T15:00:00Z');
});

test('the same key can be reserved for different leads', function () {
    $first = automationExecutionLead();
    $second = automationExecutionLead();
    $payload = ['automation_key' => 'no_reply:2026-10-03'];

    $this->postJson(automationExecutionUrl($first), $payload, automationExecutionToken())->assertExactJson(['reserved' => true]);
    $this->postJson(automationExecutionUrl($second), $payload, automationExecutionToken())->assertExactJson(['reserved' => true]);

    expect(LeadAutomationExecution::query()->orderBy('lead_id')->pluck('lead_id')->all())->toBe([$first->id, $second->id]);
});

test('different keys can be reserved for the same lead', function () {
    $lead = automationExecutionLead();

    foreach (['trial_expired:2026-10-05', 'no_reply:2026-10-03', 'no_reply:2026-10-04', 'NO_REPLY:2026-10-03'] as $key) {
        $this->postJson(automationExecutionUrl($lead), ['automation_key' => $key], automationExecutionToken())
            ->assertExactJson(['reserved' => true]);
    }

    expect(LeadAutomationExecution::query()->where('lead_id', $lead->id)->count())->toBe(4);
});

test('the reservation rejects a missing or invalid token before resolving the lead', function () {
    $lead = automationExecutionLead();
    $payload = ['automation_key' => 'trial_expired:2026-10-05'];

    $this->postJson(automationExecutionUrl($lead), $payload)->assertUnauthorized();
    $this->postJson(automationExecutionUrl($lead), $payload, ['Authorization' => 'Bearer token-errado'])->assertUnauthorized();
    $this->postJson(automationExecutionUrl(999999), $payload)->assertUnauthorized();

    expect(LeadAutomationExecution::query()->count())->toBe(0);
});

test('the reservation returns not found for an unknown lead', function () {
    $this->postJson(automationExecutionUrl(999999), ['automation_key' => 'trial_expired:2026-10-05'], automationExecutionToken())
        ->assertNotFound();

    expect(LeadAutomationExecution::query()->count())->toBe(0);
});

test('the reservation returns not found for a soft-deleted lead', function () {
    $lead = automationExecutionLead();
    $lead->delete();

    $this->postJson(automationExecutionUrl($lead->id), ['automation_key' => 'trial_expired:2026-10-05'], automationExecutionToken())
        ->assertNotFound();

    expect(LeadAutomationExecution::query()->count())->toBe(0);
});

test('the automation key is validated', function (array $payload) {
    $lead = automationExecutionLead();

    $this->postJson(automationExecutionUrl($lead), $payload, automationExecutionToken())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('automation_key');

    expect(LeadAutomationExecution::query()->count())->toBe(0);
})->with([
    'missing' => [[]],
    'empty' => [['automation_key' => '']],
    'blank' => [['automation_key' => '   ']],
    'null' => [['automation_key' => null]],
    'not a string' => [['automation_key' => ['trial_expired']]],
    'too long' => [['automation_key' => str_repeat('a', LeadAutomationExecution::KEY_MAX_LENGTH + 1)]],
    'free text' => [['automation_key' => 'Cliente parece interessado, mandar oferta']],
    'line break' => [['automation_key' => "trial_expired:2026-10-05\nx"]],
]);

test('a key at the maximum length is accepted', function () {
    $lead = automationExecutionLead();

    $this->postJson(automationExecutionUrl($lead), ['automation_key' => str_repeat('a', LeadAutomationExecution::KEY_MAX_LENGTH)], automationExecutionToken())
        ->assertCreated()
        ->assertExactJson(['reserved' => true]);
});

test('reserving changes nothing on the lead and sends nothing', function () {
    Http::fake();
    Http::preventStrayRequests();

    $lead = automationExecutionLead([
        'status' => 'converted',
        'trial_started_at' => '2026-09-20',
        'trial_ends_at' => '2026-10-05',
        'next_follow_up_at' => '2026-09-30 14:00',
        'last_contacted_at' => '2026-09-27 11:00:00',
    ]);

    $leadRow = fn () => (array) DB::table('leads')->where('id', $lead->id)->first();
    $leadBefore = $leadRow();
    $activitiesBefore = DB::table('lead_activities')->count();

    $this->travel(5)->minutes();

    $queries = automationExecutionRecordQueries();
    $this->postJson(automationExecutionUrl($lead), ['automation_key' => 'trial_expired:2026-10-05'], automationExecutionToken())
        ->assertExactJson(['reserved' => true]);
    $this->postJson(automationExecutionUrl($lead), ['automation_key' => 'trial_expired:2026-10-05'], automationExecutionToken())
        ->assertExactJson(['reserved' => false]);
    $writes = $queries
        ->filter(fn (string $sql) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $sql) === 1)
        ->reject(fn (string $sql) => str_contains($sql, 'cache'))
        ->values();

    $leadAfter = $leadRow();

    // Lead encerrado também reserva: a elegibilidade comercial não é decidida nesta camada.
    expect($leadAfter)->toBe($leadBefore)
        ->and($leadAfter['status'])->toBe('converted')
        ->and($leadAfter['last_contacted_at'])->toBe($leadBefore['last_contacted_at'])
        ->and($leadAfter['next_follow_up_at'])->toBe($leadBefore['next_follow_up_at'])
        ->and($leadAfter['trial_started_at'])->toBe($leadBefore['trial_started_at'])
        ->and($leadAfter['trial_ends_at'])->toBe($leadBefore['trial_ends_at'])
        ->and($leadAfter['updated_at'])->toBe($leadBefore['updated_at'])
        ->and(DB::table('lead_activities')->count())->toBe($activitiesBefore)
        // Cada tentativa faz só o INSERT na tabela de reservas (o segundo colide); nada é atualizado.
        ->and($writes)->toHaveCount(2)
        ->and($writes->every(fn (string $sql) => str_contains($sql, 'lead_automation_executions')))->toBeTrue();

    Http::assertNothingSent();
});

test('the unique constraint blocks duplicates independently of the PHP layer', function () {
    $lead = automationExecutionLead();
    $row = ['lead_id' => $lead->id, 'automation_key' => 'trial_expired:2026-10-05', 'executed_at' => now()];

    DB::table('lead_automation_executions')->insert($row);

    expect(fn () => DB::table('lead_automation_executions')->insert($row))
        ->toThrow(UniqueConstraintViolationException::class);

    expect(DB::table('lead_automation_executions')->count())->toBe(1);
});

test('reserve does not check before inserting: a pre-existing row makes it return false', function () {
    $lead = automationExecutionLead();

    // Simula a outra requisição concorrente que gravou entre o início desta e o INSERT.
    DB::table('lead_automation_executions')->insert([
        'lead_id' => $lead->id,
        'automation_key' => 'trial_expired:2026-10-05',
        'executed_at' => now(),
    ]);

    $queries = automationExecutionRecordQueries();
    $reserved = LeadAutomationExecution::reserve($lead, 'trial_expired:2026-10-05');

    expect($reserved)->toBeFalse()
        ->and($queries)->toHaveCount(1)
        ->and($queries->first())->toStartWith('insert')
        ->and(DB::table('lead_automation_executions')->count())->toBe(1);
});

test('soft-deleting a lead keeps its reservations and force-deleting removes them', function () {
    $lead = automationExecutionLead();
    LeadAutomationExecution::reserve($lead, 'trial_expired:2026-10-05');

    $lead->delete();

    expect(DB::table('lead_automation_executions')->where('lead_id', $lead->id)->count())->toBe(1);

    $lead->forceDelete();

    expect(DB::table('lead_automation_executions')->where('lead_id', $lead->id)->count())->toBe(0);
});
