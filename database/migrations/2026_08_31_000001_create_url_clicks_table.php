<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('url_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('urls')->cascadeOnDelete();
            $table->timestamp('accessed_at');
            // No IP address, no persistent identifiers — user_agent and
            // referrer are enough for the aggregate stats this project
            // exposes (§23), and collecting anything more identifying
            // isn't justified by that use case.
            $table->string('user_agent', 512)->nullable();
            $table->string('referrer', 2048)->nullable();

            // Composite index: every analytics query in UrlController is
            // "clicks for this url_id, filtered/grouped by accessed_at",
            // so this index directly matches the actual query shape rather
            // than indexing each column separately.
            $table->index(['url_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('url_clicks');
    }
};
