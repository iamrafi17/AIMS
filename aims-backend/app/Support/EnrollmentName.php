<?php

namespace App\Support;

final class EnrollmentName
{
    public static function fromOfficialName(string $fullName): array
    {
        $name = preg_replace('/\s+/u', ' ', trim($fullName));
        if (str_contains($name, ',')) {
            [$last, $given] = array_map('trim', explode(',', $name, 2));
        } else {
            $parts = explode(' ', $name);
            $last = count($parts) > 1 ? array_pop($parts) : '';
            $given = implode(' ', $parts);
        }

        $givenParts = preg_split('/\s+/u', $given, -1, PREG_SPLIT_NO_EMPTY);
        // The official CSV sometimes ends the given names with a middle initial.
        // A full trailing word is ambiguous, so keep it with the given names.
        $middle = count($givenParts) > 1 && preg_match('/^[A-Za-z]\\.?$/', end($givenParts))
            ? array_pop($givenParts) : null;

        return [
            'first_name' => implode(' ', $givenParts),
            'middle_name' => $middle,
            'last_name' => $last,
        ];
    }
}
