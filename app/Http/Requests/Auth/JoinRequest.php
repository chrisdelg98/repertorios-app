<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class JoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim($this->string('code'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6', 'exists:bands,code'],
            'pin' => ['required', 'string', 'min:4', 'max:8'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.exists' => 'Band code not found.',
            'code.size' => 'Band code must be exactly 6 characters.',
        ];
    }
}
