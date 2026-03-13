<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDomainHostToReviewRequestsTables extends Migration
{
    public function up()
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->string('domain_host')->nullable()->after('notes');
            $table->index(['domain_host', 'status']);
        });

        Schema::table('user_edit_requests', function (Blueprint $table) {
            $table->string('domain_host')->nullable()->after('requester_whatsapp');
            $table->index(['domain_host', 'status']);
        });
    }

    public function down()
    {
        Schema::table('registration_requests', function (Blueprint $table) {
            $table->dropIndex(['domain_host', 'status']);
            $table->dropColumn('domain_host');
        });

        Schema::table('user_edit_requests', function (Blueprint $table) {
            $table->dropIndex(['domain_host', 'status']);
            $table->dropColumn('domain_host');
        });
    }
}
