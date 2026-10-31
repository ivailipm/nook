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
        Schema::create('promos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('store');
            $table->string('product')->nullable();
            $table->string('deal');
            $table->string('note')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_until');
            $table->string('photo_path')->nullable();
            $table->unsignedSmallInteger('tone');
            $table->timestamps();
        });

        // Deleting a promo unlinks its grocery items.
        Schema::table('grocery_items', function (Blueprint $table) {
            $table->foreign('promo_id')->references('id')->on('promos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grocery_items', function (Blueprint $table) {
            $table->dropForeign(['promo_id']);
        });

        Schema::dropIfExists('promos');
    }
};
