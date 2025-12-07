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
        Schema::table('tasks', function (Blueprint $table) {
            // Удаляем foreign key constraint
            $table->dropForeign(['project_id']);
            // Изменяем project_id на обычное integer (без foreign key)
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Восстанавливаем foreign key
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
    }
};
