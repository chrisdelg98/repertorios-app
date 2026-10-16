<?php

namespace App\Http\Requests\Songs;

use App\Support\LyricSections;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * What a saved set of words is allowed to be.
 *
 * Two shapes travel in the same array. A section carries an id, a kind and its
 * text; a repeat carries nothing but the id of the section it stands for. The
 * rules below are written so each shape only has to satisfy its own.
 */
class UpdateLyricsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'blocks'         => ['present', 'array', 'max:' . LyricSections::MAX_BLOCKS],

            // A section: identified, typed, and carrying its words.
            'blocks.*.id'    => ['required_without:blocks.*.ref', 'string', 'max:24'],
            'blocks.*.kind'  => ['required_without:blocks.*.ref', 'string', Rule::in(LyricSections::KINDS)],
            'blocks.*.text'  => ['nullable', 'string', 'max:' . LyricSections::MAX_BLOCK_CHARS],
            'blocks.*.label' => ['nullable', 'string', 'max:' . LyricSections::MAX_LABEL_CHARS],

            // A repeat: nothing but a pointer.
            'blocks.*.ref'   => ['required_without:blocks.*.id', 'string', 'max:24'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $blocks = $this->input('blocks', []);

            if (!is_array($blocks)) {
                return;
            }

            // A pointer to a section that is not in this payload would render
            // as a hole in the song, so it is refused here rather than being
            // dropped quietly on the way in.
            $ids = [];

            foreach ($blocks as $block) {
                if (is_array($block) && filled($block['id'] ?? null)) {
                    $ids[] = $block['id'];
                }
            }

            foreach ($blocks as $i => $block) {
                $ref = is_array($block) ? ($block['ref'] ?? null) : null;

                if (filled($ref) && !in_array($ref, $ids, true)) {
                    $validator->errors()->add("blocks.{$i}.ref", 'This repeat points at a section that is not here.');
                }
            }

            // One cap over the whole song, so eighty blocks just under the
            // per-block limit cannot add up to something nobody meant to store.
            $total = 0;

            foreach ($blocks as $block) {
                $total += mb_strlen((string) (is_array($block) ? ($block['text'] ?? '') : ''));
            }

            if ($total > LyricSections::MAX_TOTAL_CHARS) {
                $validator->errors()->add('blocks', 'These lyrics are too long to store.');
            }
        });
    }
}
