<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();

            // One widget per team for now; unique() also gives us the index for lookups by team.
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();

            // Public, not secret: it appears in the embed snippet on the team's website.
            $table->string('public_key', 64)->unique();

            // Websites allowed to show the widget, e.g. ["https://acme.com"].
            $table->json('allowed_origins');

            // New widgets start switched off until the team has set it up.
            $table->boolean('enabled')->default(false);

            $table->string('greeting', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
