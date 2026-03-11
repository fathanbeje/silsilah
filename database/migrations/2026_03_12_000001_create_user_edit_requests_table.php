<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserEditRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('user_edit_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('target_user_id');
            $table->string('requester_name');
            $table->string('requester_whatsapp', 50);
            $table->string('status', 20)->default('pending');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->json('proposed_profile')->nullable();
            $table->json('proposed_metadata')->nullable();
            $table->json('proposed_new_spouses')->nullable();
            $table->json('proposed_new_children')->nullable();
            $table->string('proposed_photo_path')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_edit_requests');
    }
}
