<?php
/**
 * Генератор иконок PWA из favicon.ico
 *
 * Использование: php generate-pwa-icons.php
 */

$faviconPath = __DIR__ . '/public/favicon.ico';
$outputDir = __DIR__ . '/public';

if (!file_exists($faviconPath)) {
    echo "❌ Файл favicon.ico не найден в public/\n";
    exit(1);
}

if (!extension_loaded('gd')) {
    echo "❌ Расширение GD не установлено в PHP\n";
    echo "💡 Используй онлайн-инструменты из PWA-ICONS-README.md\n";
    exit(1);
}

// Пытаемся загрузить favicon
$faviconData = file_get_contents($faviconPath);
$image = @imagecreatefromstring($faviconData);

if (!$image) {
    echo "❌ Не удалось загрузить favicon.ico (формат ICO не поддерживается GD напрямую)\n";
    echo "\n💡 Рекомендуемый способ:\n";
    echo "   1. Открой https://favicon.io/favicon-converter/\n";
    echo "   2. Загрузи свой favicon.ico или логотип\n";
    echo "   3. Скачай icon-192.png и icon-512.png\n";
    echo "   4. Помести их в папку public/\n";
    echo "\n📖 Подробная инструкция: PWA-ICONS-README.md\n";
    exit(1);
}

$sizes = [192, 512];

foreach ($sizes as $size) {
    $resized = imagescale($image, $size, $size, IMG_BICUBIC);
    if (!$resized) {
        echo "❌ Ошибка при создании иконки {$size}x{$size}\n";
        continue;
    }

    $outputPath = "{$outputDir}/icon-{$size}.png";
    $success = imagepng($resized, $outputPath, 9);
    imagedestroy($resized);

    if ($success) {
        echo "✅ Создана иконка: icon-{$size}.png\n";
    } else {
        echo "❌ Ошибка при сохранении icon-{$size}.png\n";
    }
}

imagedestroy($image);
echo "\n🎉 Готово! Иконки созданы в папке public/\n";

