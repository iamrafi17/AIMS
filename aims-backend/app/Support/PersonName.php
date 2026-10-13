<?php

namespace App\Support;

final class PersonName
{
    public static function split(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $firstName = array_shift($parts) ?: null;
        $lastName = $parts ? array_pop($parts) : null;

        return [
            'first_name' => $firstName,
            'middle_name' => $parts ? implode(' ', $parts) : null,
            'last_name' => $lastName,
        ];
    }

    public static function full(?string $first, ?string $middle, ?string $last): string
    {
        return implode(' ', array_filter([$first, $middle, $last], fn ($part) => filled($part)));
    }
}
