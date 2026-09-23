<?php

namespace App\Http\Requests\Api;

use App\Models\LeadActivity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWhatsappMessageStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('status'))) {
            $this->merge(['status' => strtoupper(trim($this->input('status')))]);
        }
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(LeadActivity::MESSAGE_STATUSES)],
        ];
    }
}
