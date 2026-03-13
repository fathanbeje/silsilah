<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeAppSettingsHostAware extends Migration
{
    public function up()
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('host')->nullable()->after('id');
            $table->dropUnique('app_settings_key_unique');
            $table->unique(['host', 'key']);
        });
    }

    public function down()
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropUnique(['host', 'key']);
            $table->unique('key');
            $table->dropColumn('host');
        });
    }
}
