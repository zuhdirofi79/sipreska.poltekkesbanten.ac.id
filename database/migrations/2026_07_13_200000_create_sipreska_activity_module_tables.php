<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->date('activity_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location');
            $table->string('status')->default('terdaftar');
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->string('pic_name');
            $table->string('pic_contact')->nullable();
            $table->text('description')->nullable();
            $table->text('objective')->nullable();
            $table->text('target_participants')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('unit_id');
            $table->index('category_id');
            $table->index('status');
            $table->index('activity_date');
            $table->index('created_at');
        });

        Schema::create('invitation_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->unique()->constrained('activities')->cascadeOnDelete();
            $table->string('letter_number')->nullable();
            $table->date('letter_date')->nullable();
            $table->string('disk')->default('sipreska');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('minutes_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->unique()->constrained('activities')->cascadeOnDelete();
            $table->date('minutes_date')->nullable();
            $table->string('notulist_name')->nullable();
            $table->string('disk')->default('sipreska');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('documentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('type')->default('foto_kegiatan');
            $table->text('description')->nullable();
            $table->string('disk')->default('sipreska');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['activity_id', 'type']);
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->string('platform');
            $table->string('title');
            $table->string('url');
            $table->date('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('activity_id');
        });

        Schema::create('attendance_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->unique()->constrained('activities')->cascadeOnDelete();
            $table->string('public_token', 64)->unique();
            $table->boolean('is_open')->default(false);
            $table->boolean('signature_required')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_form_id')->constrained('attendance_forms')->cascadeOnDelete();
            $table->string('name');
            $table->string('nip_nik');
            $table->string('nip_nik_normalized');
            $table->string('position')->nullable();
            $table->string('work_unit');
            $table->string('institution_name')->nullable();
            $table->string('signature_disk')->default('sipreska');
            $table->string('signature_path');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['attendance_form_id', 'nip_nik_normalized']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_responses');
        Schema::dropIfExists('attendance_forms');
        Schema::dropIfExists('publications');
        Schema::dropIfExists('documentations');
        Schema::dropIfExists('minutes_documents');
        Schema::dropIfExists('invitation_documents');
        Schema::dropIfExists('activities');
    }
};
