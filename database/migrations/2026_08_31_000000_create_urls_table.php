<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('urls', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            // 10 chars is plenty of headroom over the 6-char default codes;
            // varchar keeps the index small and fast compared to text.
            $table->string('short_code', 10)->unique();
            $table->unsignedBigInteger('access_count')->default(0);
            $table->timestamps();

            // Laravel's ->unique() already creates a unique index under the
            // hood in PostgreSQL, so we do NOT add a second explicit index
            // here — see the README section "Index vs Unique Constraint"
            // for why that would be redundant.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('urls');
    }
};
