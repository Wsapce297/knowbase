<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a conversation come from the website widget instead of a signed-in user.
 *
 *   private chat:  user_id set,  widget_id null, visitor_token_hash null
 *   widget chat:   user_id null, widget_id set,  visitor_token_hash set
 *
 * The CHECK constraint makes the database refuse anything in between.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // The existing foreign key (cascade on user delete) stays as it is.
            $table->unsignedBigInteger('user_id')->nullable()->change();

            $table->foreignId('widget_id')->nullable()->constrained()->cascadeOnDelete();

            // sha256 of the visitor token (64 hex characters). The token itself is never stored.
            $table->string('visitor_token_hash', 64)->nullable()->unique();

            // For analytics later ("latest widget conversations").
            $table->index(['widget_id', 'updated_at']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE conversations ADD CONSTRAINT conversations_owner_check CHECK (
                (user_id IS NOT NULL AND widget_id IS NULL AND visitor_token_hash IS NULL)
                OR (user_id IS NULL AND widget_id IS NOT NULL AND visitor_token_hash IS NOT NULL)
            )
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE conversations DROP CONSTRAINT conversations_owner_check');

        // Widget conversations can't exist without these columns (user_id becomes required again).
        DB::table('conversations')->whereNull('user_id')->delete();

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('widget_id'); // also drops the index on it
            $table->dropColumn('visitor_token_hash');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
