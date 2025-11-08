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
        Schema::create('topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')
            ->constrained()
            ->onDelete('cascade');
            $table->foreignId('station_id')
            ->constrained()
            ->onDelete('cascade');
            $table->foreignId('worker_id')
            ->constrained()
            ->onDelete('cascade');
            $table->integer('amount');
            $table->enum('method', ['cash', 'transfer', 'other'])
            ->default('cash');
            $table->decimal('price', 8, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['card_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topups');
    }
};
