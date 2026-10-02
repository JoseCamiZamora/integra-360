<?php

declare(strict_types=1);

use App\Support\Status\StatusTone;
use Illuminate\Support\Facades\Blade;
use Tests\Fixtures\FakeInspectionResult;

it('always renders a symbol and a text label, never colour alone', function (StatusTone $tone, string $symbol, string $label): void {
    $html = Blade::render('<x-status-badge :status="$status" />', ['status' => $tone]);

    expect($html)
        ->toContain('<span aria-hidden="true" class="font-mono font-semibold">'.$symbol.'</span>')
        ->toContain('<span>'.$label.'</span>')
        ->toContain('data-tone="'.$tone->value.'"');
})->with([
    [StatusTone::Success, '✓', 'Al día'],
    [StatusTone::Warning, '!', 'Atención'],
    [StatusTone::Danger, '✕', 'Crítico'],
    [StatusTone::Neutral, '–', 'Pendiente'],
]);

it('renders business statuses with their own label', function (): void {
    $html = Blade::render('<x-status-badge :status="$status" />', ['status' => FakeInspectionResult::FitWithFinding]);

    expect($html)->toContain('!')->toContain('Apto con novedad')->toContain('text-warning');
});

it('accepts an explicit label', function (): void {
    $html = Blade::render('<x-status-badge :status="$status" label="Vence en 5 días" />', ['status' => StatusTone::Warning]);

    expect($html)->toContain('Vence en 5 días')->not->toContain('Atención');
});
