<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'type' => ['required', 'string', 'in:sunday_am,sunday_pm,wednesday,rehearsal,other'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
