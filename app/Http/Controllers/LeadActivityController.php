<?php

namespace App\Http\Controllers;

use App\Http\Requests\Leads\LeadActivityRequest;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class LeadActivityController extends Controller
{
    public function store(LeadActivityRequest $request, Lead $lead): RedirectResponse
    {
        $data = $request->validated();
        $activityData = $data;
        unset($activityData['lost_reason']);

        $activity = $lead->activities()->create([
            ...$activityData,
            'user_id' => $request->user()->id,
        ]);

        $leadUpdates = [];

        if ($activity->status !== null) {
            $leadUpdates['status'] = $activity->status;
            $leadUpdates['lost_reason'] = $activity->status === 'lost' ? ($data['lost_reason'] ?? $lead->lost_reason) : null;
        }

        if ($activity->contacted_at !== null) {
            $leadUpdates['last_contacted_at'] = $activity->contacted_at;
        }

        if ($activity->next_follow_up_at !== null) {
            $leadUpdates['next_follow_up_at'] = $activity->next_follow_up_at;
        }

        if ($leadUpdates !== []) {
            $lead->forceFill($leadUpdates)->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Histórico registrado.']);

        return back();
    }
}
