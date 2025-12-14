<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('channel', 32)->default('telegram');
            $table->dateTime('send_at');
            $table->string('status', 16)->default('pending'); // pending|sent|cancelled|failed
            $table->dateTime('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->text('source_text')->nullable();

            $table->timestamps();

            $table->index(['task_id', 'status']);
            $table->index(['channel', 'status', 'send_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reminders');
    }
};


