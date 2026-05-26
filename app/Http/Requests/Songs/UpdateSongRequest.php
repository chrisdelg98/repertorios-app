<?php

namespace App\Http\Requests\Songs;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'   => ['required', 'string', 'max:255'],
            'artist' => ['nullable', 'string', 'max:50'],
        ];
    }
}
