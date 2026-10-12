<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pc_ports', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('game_title');
            $table->string('original_platform')->nullable();
            $table->string('project_name')->nullable();
            $table->string('previous_project_name')->nullable();
            $table->string('framework')->nullable();
            $table->string('technique')->nullable();
            $table->string('category')->index();
            $table->string('status')->index();
            $table->string('windows_support')->nullable();
            $table->string('linux_support')->nullable();
            $table->string('macos_support')->nullable();
            $table->string('latest_version')->nullable();
            $table->string('latest_public_version')->nullable();
            $table->date('last_activity')->nullable();
            $table->date('last_checked_at')->nullable();
            $table->date('added_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('repo_url', 500)->nullable();
            $table->string('homepage_url', 500)->nullable();
            $table->string('primary_source', 500)->nullable();
            $table->string('framework_repo_url', 500)->nullable();
            $table->string('cover_source_url', 500)->nullable();
            $table->string('cover_path')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pc_ports');
    }
};
