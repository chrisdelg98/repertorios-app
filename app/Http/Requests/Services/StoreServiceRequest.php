<?php

namespace App\Http\Requests\Services;

use App\Models\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'type' => ['required', 'string', 'max:20'],
            'color' => ['nullable', Rule::in(Service::COLORS)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
