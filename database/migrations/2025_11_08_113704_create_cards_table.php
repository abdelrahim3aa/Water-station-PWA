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
        Schema::create('cards', function (Blueprint $table) {
            $$table->id();
            $table->string('card_number')->unique();
            $table->string('qr_code')->unique();
            $table->string('family_name');
            $table->string('phone')->nullable();
            $table->foreignId('station_id')
            ->constrained()
            ->onDelete('cascade');
            $table->integer('balance')->default(0);
            $table->enum('status', ['active', 'inactive', 'blocked'])
            ->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['qr_code', 'status']);
            $table->index(['card_number', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
