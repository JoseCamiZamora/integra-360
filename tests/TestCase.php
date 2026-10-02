<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Tenancy\CompanyContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The active company is static: never carry it from one test to the next.
        CompanyContext::forget();
    }

    protected function tearDown(): void
    {
        CompanyContext::forget();

        parent::tearDown();
    }
}
