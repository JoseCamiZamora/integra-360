<?php

declare(strict_types=1);

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests (tests/Feature) and every module's tests (modules/*\/tests)
| run on the Laravel TestCase against the MySQL testing database
| (integra360_testing, see phpunit.xml). Tests that touch the database
| must use RefreshDatabase explicitly.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature', '../modules/*/tests');
