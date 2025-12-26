<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CRITICAL: Force tests to ALWAYS use the test database
        // This prevents tests from accidentally using the application's production database
        // phpunit.xml sets DB_CONNECTION=sqlite and DB_DATABASE=:memory:
        // but we enforce it here as well to be absolutely sure
        
        // Override any database config that might have been loaded from .env
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');

        // Ensure we're using the test database connection
        DB::setDefaultConnection('sqlite');

        // Safety check: Verify we're using the in-memory database
        $database = Config::get('database.connections.sqlite.database');
        if ($database !== ':memory:' && !str_contains($database, 'test')) {
            throw new \RuntimeException(
                "SAFETY CHECK FAILED: Tests are trying to use a non-test database: {$database}. " .
                "Tests must use ':memory:' SQLite database or a database with 'test' in the name."
            );
        }
    }
}
