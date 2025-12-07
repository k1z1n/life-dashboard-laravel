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
        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('#3b82f6'); // Цвет для отображения
            $table->integer('order')->default(0); // Порядок сортировки
            $table->timestamps();
        });

        // Создаем начальные приоритеты
        DB::table('priorities')->insert([
            ['name' => 'Низкий', 'color' => '#10b981', 'order' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Средний', 'color' => '#f59e0b', 'order' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Высокий', 'color' => '#ef4444', 'order' => 3, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('priorities');
    }
};
