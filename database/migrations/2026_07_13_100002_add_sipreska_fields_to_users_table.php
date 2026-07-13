<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->nullable()->after('password');
            $table->foreignId('unit_id')->nullable()->after('role')->constrained('units')->nullOnDelete();
            $table->boolean('is_active')->default(true)->after('unit_id');
            $table->boolean('must_change_password')->default(false)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->index('role');
            $table->index('unit_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropIndex(['role']);
            $table->dropIndex(['unit_id']);
            $table->dropIndex(['is_active']);
            $table->dropColumn([
                'role',
                'unit_id',
                'is_active',
                'must_change_password',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
