<?php

declare(strict_types=1);

use App\Support\Status\StatusTone;
use Carbon\CarbonImmutable;
use Modules\Core\Enums\DocumentStatus;

/*
 * Criterion 6: document status at the edges, in Bogotá time. A document is
 * valid until the end of its expiry day; "expiring soon" starts warning_days
 * days before it.
 */

function bogota(string $dateTime): CarbonImmutable
{
    return CarbonImmutable::parse($dateTime, 'America/Bogota');
}

function onDate(string $date): CarbonImmutable
{
    return CarbonImmutable::parse($date, 'America/Bogota');
}

it('is still in force during the whole last day', function (): void {
    expect(DocumentStatus::evaluate(onDate('2026-10-10'), 30, bogota('2026-10-10 00:00:00')))->toBe(DocumentStatus::ExpiringSoon)
        ->and(DocumentStatus::evaluate(onDate('2026-10-10'), 30, bogota('2026-10-10 23:59:59')))->toBe(DocumentStatus::ExpiringSoon);
});

it('expires at midnight of the next day', function (): void {
    expect(DocumentStatus::evaluate(onDate('2026-10-10'), 30, bogota('2026-10-11 00:00:00')))->toBe(DocumentStatus::Expired)
        ->and(DocumentStatus::evaluate(onDate('2026-10-10'), 30, bogota('2027-01-01 12:00:00')))->toBe(DocumentStatus::Expired);
});

it('uses the Bogotá calendar day, not the UTC one', function (): void {
    // 2026-10-11 03:00 UTC is still 2026-10-10 22:00 in Bogotá (UTC-5).
    $lateNightUtc = CarbonImmutable::parse('2026-10-11 03:00:00', 'UTC');

    expect(DocumentStatus::evaluate(onDate('2026-10-10'), 30, $lateNightUtc))->toBe(DocumentStatus::ExpiringSoon);

    // 2026-10-11 05:00 UTC is 2026-10-11 00:00 in Bogotá.
    expect(DocumentStatus::evaluate(onDate('2026-10-10'), 30, CarbonImmutable::parse('2026-10-11 05:00:00', 'UTC')))->toBe(DocumentStatus::Expired);
});

it('becomes "expiring soon" exactly warning_days days before', function (): void {
    $today = bogota('2026-10-01 08:00:00');

    expect(DocumentStatus::evaluate(onDate('2026-10-31'), 30, $today))->toBe(DocumentStatus::ExpiringSoon)
        ->and(DocumentStatus::evaluate(onDate('2026-11-01'), 30, $today))->toBe(DocumentStatus::Valid)
        ->and(DocumentStatus::evaluate(onDate('2026-10-11'), 10, $today))->toBe(DocumentStatus::ExpiringSoon)
        ->and(DocumentStatus::evaluate(onDate('2026-10-12'), 10, $today))->toBe(DocumentStatus::Valid);
});

it('with zero warning days is only "expiring soon" on its last day', function (): void {
    $today = bogota('2026-10-01 08:00:00');

    expect(DocumentStatus::evaluate(onDate('2026-10-01'), 0, $today))->toBe(DocumentStatus::ExpiringSoon)
        ->and(DocumentStatus::evaluate(onDate('2026-10-02'), 0, $today))->toBe(DocumentStatus::Valid);
});

it('has no expiry when there is no date', function (): void {
    expect(DocumentStatus::evaluate(null, 30))->toBe(DocumentStatus::NoExpiry);
});

it('always pairs a tone with a label', function (DocumentStatus $status, StatusTone $tone, string $label): void {
    expect($status->statusTone())->toBe($tone)
        ->and($status->statusLabel())->toBe($label);
})->with([
    [DocumentStatus::Valid, StatusTone::Success, 'Vigente'],
    [DocumentStatus::ExpiringSoon, StatusTone::Warning, 'Por vencer'],
    [DocumentStatus::Expired, StatusTone::Danger, 'Vencido'],
    [DocumentStatus::NoExpiry, StatusTone::Neutral, 'Sin vencimiento'],
]);
