<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'band_role_type_id' => ['required', 'integer', 'exists:band_role_types,id'],
            'user_id' => ['nullable', 'required_without:manual_name', 'integer', 'exists:users,id'],
            'manual_name' => ['nullable', 'required_without:user_id', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('manual_name')) {
            $this->merge([
                'manual_name' => trim((string) $this->input('manual_name')),
            ]);
        }
    }
}
