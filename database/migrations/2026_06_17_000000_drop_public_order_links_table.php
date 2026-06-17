<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class DropPublicOrderLinksTable extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('public_order_links');
    }

    public function down(): void
    {
        // Table removed intentionally; restore from 2026_06_16_000001 if needed.
    }
}
