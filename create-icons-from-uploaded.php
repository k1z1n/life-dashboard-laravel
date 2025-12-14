<?php
/**
 * Создание иконок PWA из загруженного PNG файла
 */

$sourcePath = __DIR__ . '/public/free-icon-handmade-18524645.png';
$outputDir = __DIR__ . '/public';

if (!file_exists($sourcePath)) {
    echo "❌ Файл free-icon-handmade-18524645.png не найден\n";
    exit(1);
}

if (!extension_loaded('gd')) {
    echo "❌ Расширение GD не установлено\n";
    exit(1);
}

$image = @imagecreatefrompng($sourcePath);

if (!$image) {
    echo "❌ Не удалось загрузить изображение\n";
    exit(1);
}

$sizes = [
    ['size' => 192, 'name' => 'icon-192.png'],
    ['size' => 512, 'name' => 'icon-512.png'],
];

echo "📦 Создание иконок из free-icon-handmade-18524645.png...\n\n";

$sourceWidth = imagesx($image);
$sourceHeight = imagesy($image);

foreach ($sizes as $config) {
    $size = $config['size'];
    $filename = $config['name'];

    // Создаём новое изображение нужного размера
    $resized = imagecreatetruecolor($size, $size);

    // Включаем прозрачность
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
    imagefill($resized, 0, 0, $transparent);

    // Масштабируем с сохранением пропорций
    imagecopyresampled(
        $resized, $image,
        0, 0, 0, 0,
        $size, $size,
        $sourceWidth, $sourceHeight
    );

    $outputPath = "{$outputDir}/{$filename}";
    $success = imagepng($resized, $outputPath, 9);
    imagedestroy($resized);

    if ($success && file_exists($outputPath)) {
        $fileSize = filesize($outputPath);
        echo "✅ Создана иконка: {$filename} ({$size}x{$size}, " . round($fileSize/1024, 1) . " KB)\n";
    } else {
        echo "❌ Ошибка при сохранении {$filename}\n";
    }
}

imagedestroy($image);
echo "\n🎉 Готово! Иконки созданы в папке public/\n";
echo "📱 Теперь приложение должно корректно работать на Android/Xiaomi\n";

