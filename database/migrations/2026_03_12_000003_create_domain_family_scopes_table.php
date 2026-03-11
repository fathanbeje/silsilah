<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainFamilyScopesTable extends Migration
{
    public function up()
    {
        Schema::create('domain_family_scopes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('host')->unique();
            $table->uuid('core_user_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('core_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('domain_family_scopes');
    }
}
