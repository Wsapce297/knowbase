<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enable the pgvector extension in the current database.
     *
     * This runs "CREATE EXTENSION IF NOT EXISTS vector", so it is safe to run
     * more than once. It works for both `knowbase` (php artisan migrate) and
     * `knowbase_testing` (migrated automatically by RefreshDatabase in tests).
     */
    public function up(): void
    {
        Schema::ensureVectorExtensionExists();
    }

    /**
     * Disable the extension again. Tables with vector columns are created in
     * later migrations, so a rollback removes them before reaching this one.
     */
    public function down(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS vector');
    }
};
