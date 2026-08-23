<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_activities', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('terdaftar');
            $table->string('public_token', 64)->unique();
            $table->string('short_code', 12)->unique();
            $table->boolean('is_open')->default(true);
            $table->boolean('signature_required')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('created_at');
        });

        Schema::create('external_activity_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_activity_id')->constrained('external_activities')->cascadeOnDelete();
            $table->date('event_date');
            $table->timestamps();

            $table->unique(['external_activity_id', 'event_date'], 'external_activity_dates_unique');
            $table->index('event_date');
        });

        Schema::create('external_attendance_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_activity_id')->constrained('external_activities')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('institution');
            $table->string('email');
            $table->string('email_normalized');
            $table->string('signature_disk')->default('sipreska');
            $table->string('signature_path');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->unique(['external_activity_id', 'email_normalized'], 'external_attendance_email_unique');
            $table->index('submitted_at');
            $table->index('institution');
        });

        Schema::create('external_attendance_participant_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('external_attendance_participants')->cascadeOnDelete();
            $table->foreignId('external_activity_date_id')->constrained('external_activity_dates')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['participant_id', 'external_activity_date_id'], 'external_attendance_participant_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_attendance_participant_dates');
        Schema::dropIfExists('external_attendance_participants');
        Schema::dropIfExists('external_activity_dates');
        Schema::dropIfExists('external_activities');
    }
};
