<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Modules\Core\Rules\ValidNit;
use Modules\Core\Support\Nit;

/*
 * Criterion 10: the NIT is validated with its verification digit.
 * Public NITs of well-known entities are used only as algorithm fixtures.
 */

it('computes the DIAN verification digit', function (string $base, int $digit): void {
    expect(Nit::verificationDigit($base))->toBe($digit);
})->with([
    'DIAN' => ['800197268', 4],
    'Ecopetrol' => ['899999068', 1],
    'Bancolombia' => ['890903938', 8],
    'remainder 0 gives 0' => ['900000009', 0],
    'remainder 1 gives 1' => ['900000002', 1],
    'fictitious' => ['900123456', 8],
]);

it('accepts valid NITs in the usual formats', function (string $nit, string $normalized): void {
    expect(Nit::isValid($nit))->toBeTrue()
        ->and(Nit::normalize($nit))->toBe($normalized);
})->with([
    ['900123456-8', '900123456-8'],
    ['900.123.456-8', '900123456-8'],
    [' 900 123 456 - 8 ', '900123456-8'],
    ['800197268-4', '800197268-4'],
]);

it('rejects invalid NITs', function (string $nit): void {
    expect(Nit::isValid($nit))->toBeFalse();
})->with([
    'wrong digit' => '900123456-7',
    'without digit' => '900123456',
    'letters' => '90012345A-8',
    'too short' => '12345-1',
    'two digits' => '900123456-81',
    'empty' => '',
]);

it('explains what is wrong through the validation rule', function (string $nit, string $message): void {
    $validator = Validator::make(['nit' => $nit], ['nit' => [new ValidNit]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('nit'))->toContain($message);
})->with([
    ['900123456-7', 'dígito de verificación'],
    ['900123456', 'formato'],
]);

it('passes the validation rule with a valid NIT', function (): void {
    expect(Validator::make(['nit' => '900.123.456-8'], ['nit' => [new ValidNit]])->passes())->toBeTrue();
});
