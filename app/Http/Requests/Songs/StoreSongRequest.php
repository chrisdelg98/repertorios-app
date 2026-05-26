<?php

namespace App\Http\Requests\Songs;

use Illuminate\Foundation\Http\FormRequest;

class StoreSongRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'artist'       => ['nullable', 'string', 'max:50'],
            'version_name' => ['required', 'string', 'max:100'],
            'key' => ['nullable', 'string', 'max:10'],
            'bpm' => ['nullable', 'integer', 'min:20', 'max:300'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'youtube_url' => ['nullable', 'url', 'max:500'],
        ];
    }
}
