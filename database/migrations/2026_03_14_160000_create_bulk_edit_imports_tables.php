<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBulkEditImportsTables extends Migration
{
    public function up()
    {
        Schema::create('bulk_edit_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_host')->nullable();
            $table->string('source_type', 40);
            $table->string('source_name');
            $table->uuid('uploaded_by')->nullable();
            $table->string('status', 30)->default('reviewing');
            $table->json('summary_json')->nullable();
            $table->timestamps();

            $table->index(['tenant_host', 'status']);
            $table->index(['uploaded_by', 'created_at']);
        });

        Schema::create('bulk_edit_import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('bulk_edit_import_id');
            $table->string('sheet_name', 40);
            $table->unsignedInteger('row_number')->nullable();
            $table->string('row_key', 100)->nullable();
            $table->string('row_type', 40);
            $table->uuid('target_user_id')->nullable();
            $table->json('payload_json')->nullable();
            $table->json('normalized_json')->nullable();
            $table->json('resolution_json')->nullable();
            $table->string('status', 30)->default('invalid');
            $table->json('error_messages_json')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['bulk_edit_import_id', 'status']);
            $table->index(['bulk_edit_import_id', 'sheet_name']);
            $table->index(['bulk_edit_import_id', 'row_type']);
            $table->index(['target_user_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bulk_edit_import_rows');
        Schema::dropIfExists('bulk_edit_imports');
    }
}
