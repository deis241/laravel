<?php

namespace App\Traits;

use Illuminate\Support\Str;

class PasswordGeneratorServiceTrait
{
    public static function generate(int $length = 12, bool $includeSpecialChars = false): string
    {
        $uppercase   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $lowercase   = 'abcdefghijklmnopqrstuvwxyz';
        $numbers     = '0123456789';
        $specialChars = '!@#$%^&*()-_=+[]{}';

        $charset = $uppercase . $lowercase . $numbers;
        if ($includeSpecialChars) {
            $charset .= $specialChars;
        }

        $password  = $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];

        $remainingLength = $includeSpecialChars ? $length - 4 : $length - 3;

        if ($includeSpecialChars) {
            $password .= $specialChars[random_int(0, strlen($specialChars) - 1)];
        }

        for ($i = 0; $i < $remainingLength; $i++) {
            $password .= $charset[random_int(0, strlen($charset) - 1)];
        }

        return str_shuffle($password);
    }

    public static function generateMemorable(int $wordCount = 4, string $separator = '-'): string
    {
        $words = [];
        for ($i = 0; $i < $wordCount; $i++) {
            $words[] = ucfirst(Str::random(5));
        }

        return implode($separator, $words) . random_int(10, 99);
    }
}
