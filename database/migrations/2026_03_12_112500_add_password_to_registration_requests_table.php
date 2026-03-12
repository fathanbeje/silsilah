<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPasswordToRegistrationRequestsTable extends Migration
{
    public function up()
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
        });
    }

    public function down()
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropColumn('password');
        });
    }
}
