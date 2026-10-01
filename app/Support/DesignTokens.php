<?php

declare(strict_types=1);

namespace App\Support;

/**
 * PHP mirror of resources/css/tokens.css for the values Filament needs at
 * runtime. tests/Feature/DesignTokensTest.php fails if they drift apart.
 */
final class DesignTokens
{
    public const string PRIMARY = '#0e6b6a';

    public const string FONT_SANS = 'IBM Plex Sans';

    public const string FONT_MONO = 'IBM Plex Mono';
}
