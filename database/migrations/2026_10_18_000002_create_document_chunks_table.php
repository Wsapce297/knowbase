<?php

use App\Ai\Embedder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();

            // Copied from the document so searches can filter by team directly.
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('position');
            $table->unsignedInteger('page')->nullable();
            $table->text('content');

            // vector(768) + an HNSW index (cosine distance) for fast nearest-neighbour search.
            $table->vector('embedding', dimensions: Embedder::DIMENSIONS)->index();

            $table->timestamps();

            $table->unique(['document_id', 'position']);
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
