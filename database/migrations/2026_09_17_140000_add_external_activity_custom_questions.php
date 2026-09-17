<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_activities', function (Blueprint $table) {
            $table->boolean('use_custom_questions')->default(false)->after('signature_required');
        });

        Schema::create('external_activity_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_activity_id')->constrained('external_activities')->cascadeOnDelete();
            $table->string('label');
            $table->string('type')->default('text');
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('options')->nullable();
            $table->string('default_key', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['external_activity_id', 'is_active', 'sort_order'], 'external_activity_questions_active_order');
            $table->index(['external_activity_id', 'default_key'], 'external_activity_questions_default_key');
        });

        Schema::create('external_attendance_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('external_attendance_participants')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('external_activity_questions')->restrictOnDelete();
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['participant_id', 'question_id'], 'external_attendance_answers_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_attendance_answers');
        Schema::dropIfExists('external_activity_questions');

        Schema::table('external_activities', function (Blueprint $table) {
            $table->dropColumn('use_custom_questions');
        });
    }
};
