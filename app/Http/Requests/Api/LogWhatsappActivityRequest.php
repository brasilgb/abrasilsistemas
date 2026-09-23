<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LogWhatsappActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message_id' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }
}
