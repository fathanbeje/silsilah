<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_deceased')->default(false)->after('yod');
        });

        DB::table('users')
            ->whereNotNull('dod')
            ->orWhereNotNull('yod')
            ->update(['is_deceased' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_deceased');
        });
    }
};
