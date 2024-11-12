<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('super')->default(false);
            $table->string('avatar')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->string('password')->nullable()->change();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_id');
        });

        Schema::create('group_user', function (Blueprint $table) {
            $table->id('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('group_id');
        });

        Schema::create('password_activation_tokens', function (Blueprint $table) {
            $table->string('email')->index();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');

        Schema::dropIfExists('role_user');
        Schema::dropIfExists('group_user');

        Schema::dropIfExists('password_activation_tokens');
    }
};
