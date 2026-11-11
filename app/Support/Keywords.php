<?php

namespace App\Support;

class Keywords
{
    /**
     * Turn a comma separated string into a unique, trimmed, non-empty list.
     *
     * @return list<string>
     */
    public static function parse(?string $raw): array
    {
        return collect(explode(',', (string) $raw))
            ->map(fn ($keyword) => trim($keyword))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
