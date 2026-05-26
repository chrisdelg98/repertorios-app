<?php

namespace App\Http\Requests\Services;

use Illuminate\Foundation\Http\FormRequest;

class AddSongToServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'song_version_id' => ['nullable', 'integer', 'exists:song_versions,id'],
            'song_name'       => ['nullable', 'string', 'max:255'],
            'artist'          => ['nullable', 'string', 'max:50'],
            'version_name'    => ['nullable', 'string', 'max:100'],
            'key'             => ['nullable', 'string', 'max:10'],
            'bpm'             => ['nullable', 'integer', 'min:20', 'max:300'],
            'youtube_url'     => ['nullable', 'url', 'max:500'],
            'notes'           => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (!$this->filled('song_version_id') && !$this->filled('song_name')) {
                $v->errors()->add('song_name', 'Provide a song or select an existing one.');
            }
        });
    }
}
