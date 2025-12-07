<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Сначала создаем mapping для миграции данных
        $priorityMap = [
            'low' => 1,    // Низкий
            'medium' => 2, // Средний
            'high' => 3,   // Высокий
        ];

        // Мигрируем данные: сохраняем старые значения priority во временную колонку
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority_old')->nullable()->after('priority');
        });

        // Копируем старые значения
        DB::statement('UPDATE tasks SET priority_old = priority');

        // Удаляем старую колонку enum
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('priority');
        });

        // Добавляем новую колонку priority_id
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('priority_id')->nullable()->after('completed')->constrained('priorities')->onDelete('set null');
        });

        // Мигрируем данные: преобразуем старые значения в новые ID
        foreach ($priorityMap as $oldValue => $newId) {
            DB::table('tasks')
                ->where('priority_old', $oldValue)
                ->update(['priority_id' => $newId]);
        }

        // Удаляем временную колонку
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('priority_old');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Создаем mapping для отката
        $priorityMap = [
            1 => 'low',
            2 => 'medium',
            3 => 'high',
        ];

        // Сохраняем priority_id во временную колонку
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority_old')->nullable()->after('priority_id');
        });

        // Копируем ID в строковые значения
        foreach ($priorityMap as $id => $value) {
            DB::table('tasks')
                ->where('priority_id', $id)
                ->update(['priority_old' => $value]);
        }

        // Удаляем foreign key колонку
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['priority_id']);
            $table->dropColumn('priority_id');
        });

        // Восстанавливаем enum колонку
        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium')->after('completed');
        });

        // Восстанавливаем данные
        DB::statement('UPDATE tasks SET priority = priority_old WHERE priority_old IS NOT NULL');
        DB::statement('UPDATE tasks SET priority = "medium" WHERE priority_old IS NULL');

        // Удаляем временную колонку
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('priority_old');
        });
    }
};
