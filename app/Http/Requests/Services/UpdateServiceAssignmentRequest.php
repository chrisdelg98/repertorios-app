<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'band_role_type_id' => ['required', 'integer', 'exists:band_role_types,id'],
            'manual_name' => ['nullable', 'string', 'max:50'],
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
