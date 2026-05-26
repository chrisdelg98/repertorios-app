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

            'versions'                 => ['array'],
            'versions.*.id'            => ['required', 'integer'],
            'versions.*.name'          => ['required', 'string', 'max:100'],
            'versions.*.key'           => ['nullable', 'string', 'max:10'],
            'versions.*.bpm'           => ['nullable', 'integer', 'min:20', 'max:300'],
            'versions.*.notes'         => ['nullable', 'string', 'max:1000'],
            'versions.*.youtube_url'   => ['nullable', 'url', 'max:500'],
        ];
    }
}
