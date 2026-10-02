<?php

declare(strict_types=1);

namespace Modules\Core\Support;

/**
 * Temporary passwords handed over in person (or by WhatsApp in phase 2):
 * easy to read aloud and type on a phone, no ambiguous characters
 * (0/O, 1/I/l). Example: "KXPM-4729".
 */
final class TemporaryPassword
{
    private const string LETTERS = 'ABCDEFGHJKMNPQRSTUVWXYZ';

    private const string DIGITS = '23456789';

    public static function generate(): string
    {
        return self::pick(self::LETTERS, 4).'-'.self::pick(self::DIGITS, 4);
    }

    private static function pick(string $alphabet, int $length): string
    {
        $result = '';

        for ($i = 0; $i < $length; $i++) {
            $result .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $result;
    }
}
