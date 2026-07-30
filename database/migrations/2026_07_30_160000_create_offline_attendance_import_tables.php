<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_attendance_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('attendance_form_id')->nullable()->constrained('attendance_forms')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_disk', 50)->default('local');
            $table->string('stored_path');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 32)->default('uploaded');
            $table->unsignedInteger('detected_rows_count')->default(0);
            $table->unsignedInteger('imported_rows_count')->default(0);
            $table->unsignedInteger('skipped_duplicate_count')->default(0);
            $table->string('default_work_unit', 64)->nullable();
            $table->text('notes')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'status']);
        });

        Schema::create('offline_attendance_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('offline_attendance_import_id')
                ->constrained('offline_attendance_imports')
                ->cascadeOnDelete();
            $table->unsignedInteger('row_number')->nullable();
            $table->string('detected_name')->nullable();
            $table->string('detected_nip')->nullable();
            $table->string('detected_position')->nullable();
            $table->string('detected_signature_path')->nullable();
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->boolean('is_selected')->default(true);
            $table->string('review_name')->nullable();
            $table->string('review_nip')->nullable();
            $table->string('review_position')->nullable();
            $table->string('review_signature_disk', 50)->nullable();
            $table->string('review_signature_path')->nullable();
            $table->string('review_notes')->nullable();
            $table->foreignId('imported_attendance_response_id')
                ->nullable()
                ->constrained('attendance_responses')
                ->nullOnDelete();
            $table->longText('raw_text')->nullable();
            $table->timestamps();

            $table->index('offline_attendance_import_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_attendance_import_rows');
        Schema::dropIfExists('offline_attendance_imports');
    }
};
