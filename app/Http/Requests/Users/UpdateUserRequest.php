<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->profileRules($this->route('user')->id),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ];
    }
}
