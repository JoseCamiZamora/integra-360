<?php

declare(strict_types=1);

use App\Support\DesignTokens;

/**
 * @return array<string, string> token name => hex colour, read from tokens.css
 */
function designTokens(): array
{
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/resources/css/tokens.css');

    preg_match_all('/--color-([a-z-]+):\s*(#[0-9a-f]{6});/i', $css, $matches);

    return array_combine($matches[1], array_map(strtolower(...), $matches[2]));
}

function relativeLuminance(string $hex): float
{
    $channels = array_map(
        fn (string $channel): float => hexdec($channel) / 255,
        str_split(ltrim($hex, '#'), 2),
    );

    [$r, $g, $b] = array_map(
        fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
        $channels,
    );

    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

function contrastRatio(string $foreground, string $background): float
{
    $l1 = relativeLuminance($foreground);
    $l2 = relativeLuminance($background);

    return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
}

it('defines the Opción A palette in tokens.css', function (): void {
    expect(designTokens())->toMatchArray([
        'primary' => '#0e6b6a',
        'sidebar' => '#0f2a3a',
        'background' => '#f3f5f4',
        'surface' => '#ffffff',
        'border' => '#e1e6e8',
        'text' => '#14212b',
        'text-muted' => '#56636e',
        'success' => '#1f7a4d',
        'success-bg' => '#e3f2ea',
        'warning' => '#9a4a06',
        'warning-bg' => '#fef1dc',
        'danger' => '#b42318',
        'danger-bg' => '#fde8e7',
        'neutral' => '#56636e',
        'neutral-bg' => '#eef1f2',
    ]);
});

it('keeps the PHP primary colour in sync with tokens.css', function (): void {
    expect(strtolower(DesignTokens::PRIMARY))->toBe(designTokens()['primary']);
});

it('has a contrast of at least 4.5:1 for every text colour', function (string $foreground, string $background): void {
    $tokens = designTokens();

    expect(contrastRatio($tokens[$foreground], $tokens[$background]))->toBeGreaterThanOrEqual(4.5);
})->with([
    'text on background' => ['text', 'background'],
    'text on surface' => ['text', 'surface'],
    'muted text on background' => ['text-muted', 'background'],
    'muted text on surface' => ['text-muted', 'surface'],
    'primary on surface' => ['primary', 'surface'],
    'white on primary' => ['surface', 'primary'],
    'sidebar text on sidebar' => ['sidebar-text', 'sidebar'],
    'sidebar muted text on sidebar' => ['sidebar-text-muted', 'sidebar'],
    'success' => ['success', 'success-bg'],
    'warning' => ['warning', 'warning-bg'],
    'danger' => ['danger', 'danger-bg'],
    'neutral' => ['neutral', 'neutral-bg'],
]);
