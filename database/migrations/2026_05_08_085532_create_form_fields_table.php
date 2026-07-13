<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_form_id')->constrained('event_forms')->cascadeOnDelete();
            $table->string('label');
            $table->string('name');
            $table->string('type')->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_form_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};