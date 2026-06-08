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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained();
            $table->decimal('limit_amount', 15, 2);
            $table->integer('month'); // месяц (1-12)
            $table->integer('year');
            $table->timestamps();

            $table->unique(['user_id', 'category_id', 'month', 'year'], 'budgets_unique');
            $table->index('user_id');
            $table->index('category_id');
            $table->index(['month', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
