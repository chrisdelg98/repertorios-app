<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'string', 'email:rfc', 'max:150', 'unique:users,email'],
            'password'  => ['required', 'confirmed', Password::min(8)],
            'band_name' => ['required', 'string', 'max:100'],
        ];
    }
}
