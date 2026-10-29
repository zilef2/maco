<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE productos RENAME COLUMN precio_predicho TO costo_anticipado');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE productos RENAME COLUMN costo_anticipado TO precio_predicho');
    }
};
