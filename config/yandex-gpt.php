<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Yandex GPT API Configuration
    |--------------------------------------------------------------------------
    |
    | Настройки для работы с Yandex GPT API
    | Получить API Key и Folder ID можно в Yandex Cloud Console:
    | https://console.cloud.yandex.ru/
    |
    */

    'api_key' => env('YANDEX_GPT_API_KEY'),
    
    'folder_id' => env('YANDEX_GPT_FOLDER_ID'),
    
    'model' => env('YANDEX_GPT_MODEL', 'yandexgpt/latest'),
    
    'endpoint' => env('YANDEX_GPT_ENDPOINT', 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion'),
    
    'timeout' => env('YANDEX_GPT_TIMEOUT', 30),
    
    'max_tokens' => env('YANDEX_GPT_MAX_TOKENS', 2000),
    
    'temperature' => env('YANDEX_GPT_TEMPERATURE', 0.3), // Низкая для детерминированности
    
];



