<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reminder_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('deadline')->nullable();
            $table->boolean('deadline_has_time')->default(false);
            $table->unsignedInteger('remind_before')->nullable();
            $table->dateTime('remind_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'completed_at', 'deadline']);
            $table->index(['user_id', 'remind_at', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
