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

    /**
     * An empty selection is not a selection.
     *
     * The form sends [] when every box is cleared, and storing that would
     * leave a service claiming to cover no gathering at all. It means the same
     * as never having been asked.
     */
    protected function prepareForValidation(): void
    {
        $gatherings = $this->input('gatherings');

        if (is_array($gatherings)) {
            $clean = array_values(array_unique(array_map('intval', $gatherings)));
            sort($clean);

            $this->merge(['gatherings' => $clean ?: null]);
        }
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'type' => ['required', 'string', 'max:20'],
            'color' => ['nullable', Rule::in(Service::COLORS)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'playlist_url' => ['nullable', 'url', 'max:500'],

            /*
             * Which of the day's gatherings this covers, as ordinals.
             *
             * Nothing chosen means nothing to say, so it stores null rather
             * than an empty list — see prepareForValidation below.
             */
            'gatherings' => ['nullable', 'array', 'max:' . Service::MAX_GATHERINGS],
            'gatherings.*' => ['integer', 'between:1,' . Service::MAX_GATHERINGS],
        ];
    }
}
