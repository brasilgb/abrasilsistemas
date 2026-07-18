<?php

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use App\Models\LeadActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(array_keys(LeadActivity::TYPES))],
            'status' => ['nullable', 'string', Rule::in(array_keys(Lead::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', Rule::in(array_keys(Lead::LOST_REASONS))],
            'contacted_at' => ['nullable', 'date'],
            'next_follow_up_at' => ['nullable', 'date'],
            'description' => ['required', 'string'],
        ];
    }
}
