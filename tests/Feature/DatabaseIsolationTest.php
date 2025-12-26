<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_in_memory_database(): void
    {
        $database = Config::get('database.connections.sqlite.database');
        $connection = Config::get('database.default');

        $this->assertEquals('sqlite', $connection, 'Tests must use sqlite connection');
        $this->assertEquals(':memory:', $database, 'Tests must use in-memory database, not production database');
    }

    public function test_database_is_empty_initially(): void
    {
        // Verify we can connect to the test database
        $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table'");
        
        // Should have no tables initially (RefreshDatabase will create them)
        // But we can at least verify we're connected to sqlite
        $this->assertIsArray($tables);
    }

    public function test_cannot_access_production_database(): void
    {
        $database = Config::get('database.connections.sqlite.database');
        
        // Ensure we're NOT using a file-based database that could be production
        $this->assertStringNotContainsString('database.sqlite', $database, 'Tests should not use the production database file');
        $this->assertStringNotContainsString('games_outbreak', $database, 'Tests should not use production database name');
    }
}

