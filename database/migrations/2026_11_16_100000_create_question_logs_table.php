<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per answered question, for analytics now and usage limits later.
 *
 * Kept apart from conversations on purpose: deleting a conversation must not
 * change a team's statistics or its monthly question count. There is no user_id,
 * so analytics can never show who asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            // The saved answer; becomes null when its conversation is deleted (the log stays).
            $table->foreignId('conversation_message_id')->nullable()->unique()
                ->constrained('conversation_messages')->nullOnDelete();

            $table->string('channel', 20); // chat | widget
            $table->text('question');
            $table->text('normalized_question'); // for grouping "top questions"
            $table->string('outcome', 20); // answered | not_found | uncited
            $table->unsignedSmallInteger('sources_count')->default(0);
            $table->string('provider', 50)->nullable();

            // Logs never change, so there is no updated_at.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['team_id', 'created_at']);
            $table->index(['team_id', 'outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_logs');
    }
};
