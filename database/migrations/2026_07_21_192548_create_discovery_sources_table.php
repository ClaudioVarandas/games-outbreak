<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind', 20);
            $table->string('purpose', 20);
            $table->string('status', 20)->default('pending');
            $table->string('origin', 20)->default('admin');
            $table->string('url', 500)->nullable();
            $table->string('locator')->unique();
            $table->string('confidence', 20)->nullable();
            $table->text('evidence')->nullable();
            $table->boolean('needs_enrichment')->default(false);
            $table->unsignedInteger('runs_count')->default(0);
            $table->unsignedInteger('items_found_total')->default(0);
            $table->unsignedInteger('items_promoted')->default(0);
            $table->unsignedInteger('items_rejected')->default(0);
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_sources');
    }
};
