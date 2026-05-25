<?php

namespace App\Actions\Songs;

use Illuminate\Support\Str;

class NormalizeSongName
{
    public function execute(string $name): string
    {
        $ascii = Str::ascii(mb_strtolower($name));
        $clean = preg_replace('/[^a-z0-9\s]/', '', $ascii);

        return trim(preg_replace('/\s+/', ' ', $clean));
    }
}
