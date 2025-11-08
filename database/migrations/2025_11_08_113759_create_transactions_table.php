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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('temp_id')->unique()->nullable();
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
            $table->integer('previous_balance');
            $table->integer('new_balance');
            $table->enum('transaction_type', ['debit', 'credit'])
            ->default('debit');
            $table->enum('status', ['pending', 'completed', 'failed'])
            ->default('completed');
            $table->text('notes')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->index(['card_id', 'created_at']);
            $table->index(['station_id', 'created_at']);
            $table->index('temp_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
