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
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->foreignId('station_id')
            ->constrained()
            ->onDelete('cascade');
            $table->enum('role', ['worker', 'supervisor'])
            ->default('worker');
            $table->enum('status', ['active', 'inactive'])
            ->default('active');
            $table->timestamp('last_login')->nullable();
            $table->timestamps();
            $table->index(['username', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
