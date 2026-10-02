<?php

declare(strict_types=1);

namespace Modules\Core\Support;

/**
 * Colombian NIT with its DIAN verification digit ("dígito de verificación").
 * Stored normalised as "900123456-7".
 */
final class Nit
{
    /**
     * DIAN weights, applied from the rightmost digit of the base number.
     */
    private const array WEIGHTS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    /**
     * Accepts "900123456-7", "900.123.456-7" or "900 123 456 - 7".
     */
    public static function normalize(string $nit): ?string
    {
        $clean = (string) preg_replace('/[\s.]/', '', $nit);

        if (! preg_match('/^(\d{6,10})-(\d)$/', $clean, $parts)) {
            return null;
        }

        return $parts[1].'-'.$parts[2];
    }

    public static function isValid(string $nit): bool
    {
        $normalized = self::normalize($nit);

        if ($normalized === null) {
            return false;
        }

        [$base, $digit] = explode('-', $normalized);

        return self::verificationDigit($base) === (int) $digit;
    }

    public static function verificationDigit(string $base): int
    {
        $sum = 0;

        foreach (array_reverse(str_split($base)) as $position => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$position];
        }

        $remainder = $sum % 11;

        return $remainder > 1 ? 11 - $remainder : $remainder;
    }

    /**
     * "900123456" => "900123456-8".
     */
    public static function withVerificationDigit(string $base): string
    {
        return $base.'-'.self::verificationDigit($base);
    }
}
