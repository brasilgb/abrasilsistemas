<?php

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Sexta, 25/09/2026, 12:00 em Brasília (15:00 UTC).
    $this->travelTo(CarbonImmutable::parse('2026-09-25 12:00', 'America/Sao_Paulo'));
    Http::preventStrayRequests();
    $this->user = User::factory()->create(['email_verified_at' => now()]);
});

function followUpLead(array $attributes = []): Lead
{
    return Lead::query()->create([
        'user_id' => test()->user->id,
        'company_name' => 'Pet Follow-up',
        'product' => 'vetorpet',
        'status' => 'contacted',
        'whatsapp' => '51999990000',
        ...$attributes,
    ]);
}

function followUpNotes(Lead $lead): array
{
    return $lead->activities()
        ->where('description', 'like', 'Próximo contato%')
        ->orderBy('id')
        ->pluck('description')
        ->all();
}

test('follow up values are stored in UTC and typed values follow the Brasilia clock', function () {
    expect(Lead::normalizeFollowUpAt('2026-09-28T09:00'))->toBe('2026-09-28 12:00:00')
        ->and(Lead::normalizeFollowUpAt('2026-09-28'))->toBe('2026-09-28 12:00:00')
        ->and(Lead::normalizeFollowUpAt('2026-09-28T12:00:45.000Z'))->toBe('2026-09-28 12:00:00')
        ->and(Lead::normalizeFollowUpAt('2026-09-28T09:00:00-03:00'))->toBe('2026-09-28 12:00:00')
        ->and(Lead::normalizeFollowUpAt(null))->toBeNull()
        ->and(Lead::normalizeFollowUpAt(''))->toBeNull();
});

test('follow up state is overdue, today, upcoming or none for open prospects', function () {
    expect(followUpLead(['next_follow_up_at' => '2026-09-25T11:59'])->follow_up_state)->toBe('overdue')
        ->and(followUpLead(['next_follow_up_at' => '2026-09-24T18:00'])->follow_up_state)->toBe('overdue')
        ->and(followUpLead(['next_follow_up_at' => '2026-09-25T12:30'])->follow_up_state)->toBe('today')
        ->and(followUpLead(['next_follow_up_at' => '2026-09-25T23:30'])->follow_up_state)->toBe('today')
        ->and(followUpLead(['next_follow_up_at' => '2026-09-26T09:00'])->follow_up_state)->toBe('upcoming')
        ->and(followUpLead()->follow_up_state)->toBe('none');
});

test('late in the evening today still means the Brasilia day, not the UTC one', function () {
    // 22:00 em Brasília já é 01:00 do dia seguinte em UTC.
    $this->travelTo(CarbonImmutable::parse('2026-09-25 22:00', 'America/Sao_Paulo'));

    expect(followUpLead(['next_follow_up_at' => '2026-09-25T23:00'])->follow_up_state)->toBe('today')
        ->and(followUpLead(['next_follow_up_at' => '2026-09-26T09:00'])->follow_up_state)->toBe('upcoming');
});

test('converted and lost prospects are never pending follow ups', function () {
    $converted = followUpLead(['status' => 'converted', 'next_follow_up_at' => '2026-09-20T09:00']);
    $lost = followUpLead(['status' => 'lost', 'lost_reason' => 'price', 'next_follow_up_at' => '2026-09-25T15:00']);

    expect($converted->follow_up_state)->toBeNull()
        ->and($lost->follow_up_state)->toBeNull()
        // A data antiga fica gravada: reabrir o prospect recupera o agendamento.
        ->and($converted->fresh()->next_follow_up_at)->not->toBeNull();

    foreach (['overdue', 'today', 'upcoming', 'none'] as $state) {
        expect(Lead::query()->followUpState($state)->count())->toBe(0);
    }
});

test('changing the stage to converted keeps the date but removes it from pending follow ups', function () {
    $lead = followUpLead(['next_follow_up_at' => '2026-09-24T09:00']);

    $this->actingAs($this->user)
        ->patch(route('leads.status', $lead), ['status' => 'converted', 'origin' => 'whatsapp'])
        ->assertRedirect();

    $lead->refresh();

    expect($lead->next_follow_up_at)->not->toBeNull()
        ->and($lead->follow_up_state)->toBeNull()
        ->and(followUpNotes($lead))->toBe([]);

    $this->actingAs($this->user)->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.follow_ups.overdue', 0)
            ->where('taskTotals.overdue', 0));
});

test('the prospect list can be filtered by follow up situation', function () {
    followUpLead(['company_name' => 'Atrasado', 'next_follow_up_at' => '2026-09-24T09:00']);
    followUpLead(['company_name' => 'Hoje', 'next_follow_up_at' => '2026-09-25T17:00']);
    followUpLead(['company_name' => 'Proximo', 'next_follow_up_at' => '2026-09-30T09:00']);
    followUpLead(['company_name' => 'Sem data']);
    followUpLead(['company_name' => 'Convertido atrasado', 'status' => 'converted', 'next_follow_up_at' => '2026-09-24T09:00']);
    followUpLead(['company_name' => 'Perdido sem data', 'status' => 'lost', 'lost_reason' => 'price']);

    foreach (['overdue' => 'Atrasado', 'today' => 'Hoje', 'upcoming' => 'Proximo', 'none' => 'Sem data'] as $filter => $company) {
        $this->actingAs($this->user)
            ->get(route('leads.index', ['follow_up' => $filter]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('leads.data', 1)
                ->where('leads.data.0.company_name', $company)
                ->where('leads.data.0.follow_up_state', $filter));
    }

    $this->actingAs($this->user)->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('leads.data', 6)
            ->where('metrics.follow_ups', [
                'overdue' => 1,
                'today' => 1,
                'upcoming' => 1,
                'without_follow_up' => 1,
            ]));
});

test('the follow up can be scheduled, changed and removed with an audit trail', function () {
    $lead = followUpLead();

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => '2026-09-28T12:00:00.000Z'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->next_follow_up_at->toIso8601ZuluString())->toBe('2026-09-28T12:00:00Z');

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => '2026-09-30T14:30'])
        ->assertRedirect();

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => null])
        ->assertRedirect();

    expect($lead->fresh()->next_follow_up_at)->toBeNull()
        ->and(followUpNotes($lead))->toBe([
            'Próximo contato definido para 28/09/2026 às 09:00.',
            'Próximo contato alterado de 28/09/2026 às 09:00 para 30/09/2026 às 14:30.',
            'Próximo contato removido (estava em 30/09/2026 às 14:30).',
        ]);

    $notes = $lead->activities()->where('description', 'like', 'Próximo contato%')->get();

    expect($notes->pluck('type')->unique()->all())->toBe(['note'])
        ->and($notes->pluck('user_id')->unique()->all())->toBe([$this->user->id])
        ->and($notes->pluck('direction')->filter()->all())->toBe([]);
});

test('follow up notes never show up in the WhatsApp conversation nor as unread', function () {
    $lead = followUpLead();

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => '2026-09-28T09:00'])
        ->assertRedirect();

    expect($lead->activities()->count())->toBe(1)
        ->and($lead->unreadWhatsappMessages()->count())->toBe(0);

    $this->actingAs($this->user)
        ->getJson(route('leads.whatsapp.messages', $lead))
        ->assertOk()
        ->assertJsonCount(0, 'messages');

    $this->actingAs($this->user)->get(route('leads.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('leads.data.0.unread_whatsapp_messages_count', 0));
});

test('saving the same follow up again does not add history', function () {
    $lead = followUpLead(['next_follow_up_at' => '2026-09-28T09:00']);

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => '2026-09-28T12:00:00Z'])
        ->assertRedirect();

    expect(followUpNotes($lead))->toBe([]);
});

test('the lead form records follow up changes in the history', function () {
    $lead = followUpLead(['next_follow_up_at' => '2026-09-28T09:00']);

    $this->actingAs($this->user)->put(route('leads.update', $lead), [
        'company_name' => $lead->company_name,
        'product' => $lead->product,
        'status' => $lead->status,
        'whatsapp' => $lead->whatsapp,
        'next_follow_up_at' => '2026-10-02T10:15',
    ])->assertRedirect();

    expect(followUpNotes($lead))->toBe([
        'Próximo contato alterado de 28/09/2026 às 09:00 para 02/10/2026 às 10:15.',
    ]);
});

test('scheduling is refused for converted or lost prospects but removal is allowed', function () {
    $lead = followUpLead(['status' => 'lost', 'lost_reason' => 'price', 'next_follow_up_at' => '2026-09-20T09:00']);

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => '2026-09-28T09:00'])
        ->assertSessionHasErrors('next_follow_up_at');

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => null])
        ->assertSessionHasNoErrors();

    expect($lead->fresh()->next_follow_up_at)->toBeNull();
});

test('the follow up endpoint validates its input and never sends WhatsApp', function () {
    $lead = followUpLead();

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), [])
        ->assertSessionHasErrors('next_follow_up_at');

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => 'amanhã'])
        ->assertSessionHasErrors('next_follow_up_at');

    $this->actingAs($this->user)
        ->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => '2026-09-25T12:00'])
        ->assertSessionHasNoErrors();

    Http::assertNothingSent();
    expect(LeadActivity::query()->where('type', 'whatsapp')->count())->toBe(0);
});

test('guests cannot change the follow up', function () {
    $lead = followUpLead();

    $this->patch(route('leads.follow-up', $lead), ['next_follow_up_at' => '2026-09-28T09:00'])
        ->assertRedirect(route('login'));

    expect($lead->fresh()->next_follow_up_at)->toBeNull();
});
