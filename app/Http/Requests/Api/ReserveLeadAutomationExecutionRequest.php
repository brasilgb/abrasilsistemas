<?php

namespace App\Http\Requests\Api;

use App\Models\LeadAutomationExecution;
use Illuminate\Foundation\Http\FormRequest;

class ReserveLeadAutomationExecutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'automation_key' => [
                'required',
                'string',
                'max:'.LeadAutomationExecution::KEY_MAX_LENGTH,
                'regex:'.LeadAutomationExecution::KEY_PATTERN,
            ],
        ];
    }
}
