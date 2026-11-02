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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('tag');
            $table->date('due_date')->nullable();
            $table->string('priority')->nullable();
            $table->unsignedSmallInteger('repeat_weekday')->nullable();
            $table->boolean('done')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->boolean('rolled_over')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
