<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('external_activities', function (Blueprint $table) {
            $table->text('location_address')->nullable()->after('title');
            $table->string('online_url', 2048)->nullable()->after('location_address');
        });
    }

    public function down(): void
    {
        Schema::table('external_activities', function (Blueprint $table) {
            $table->dropColumn(['location_address', 'online_url']);
        });
    }
};
