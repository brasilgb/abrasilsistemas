<?php

namespace App\Http\Requests\Leads;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'phone' => $this->filled('phone') ? (preg_replace('/\D+/', '', (string) $this->input('phone')) ?? '') : null,
            'state' => $this->filled('state') ? strtoupper(trim((string) $this->input('state'))) : null,
            'whatsapp' => $this->filled('whatsapp') ? (preg_replace('/\D+/', '', (string) $this->input('whatsapp')) ?? '') : null,
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'product' => ['required', 'string', Rule::in(array_keys(Lead::PRODUCTS))],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:40'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(array_keys(Lead::STATUSES))],
            'lost_reason' => ['nullable', 'required_if:status,lost', 'string', Rule::in(array_keys(Lead::LOST_REASONS))],
            'next_follow_up_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
