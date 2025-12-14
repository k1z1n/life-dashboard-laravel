<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Notification Time Agent
    |--------------------------------------------------------------------------
    |
    | Deterministic agent that converts free-form reminder text into exact
    | Telegram notification times.
    |
    */

    'mode' => env('NOTIFICATION_AGENT_MODE', 'completion'), // completion|agent|responses_prompt

    'agent_id' => env('NOTIFICATION_AGENT_ID'),

    // For Responses API (rest-assistant) prompt mode.
    // This is the "prompt.id" you got in the Python example (e.g. fvtut7d...).
    'prompt_id' => env('NOTIFICATION_AGENT_PROMPT_ID'),

    'rest_assistant_base_url' => env('YANDEX_REST_ASSISTANT_BASE_URL', 'https://rest-assistant.api.cloud.yandex.net/v1'),

    // Used in completion mode. If empty, falls back to yandex-gpt folder_id/model.
    'model_uri' => env('NOTIFICATION_AGENT_MODEL_URI'),

    'timeout' => env('NOTIFICATION_AGENT_TIMEOUT', env('YANDEX_GPT_TIMEOUT', 30)),

    // Determinism: keep temperature at 0.
    'temperature' => (float) env('NOTIFICATION_AGENT_TEMPERATURE', 0),

    'max_tokens' => (int) env('NOTIFICATION_AGENT_MAX_TOKENS', 3000), // Увеличено для 100 уведомлений

    'system_prompt' => env('NOTIFICATION_AGENT_SYSTEM_PROMPT', <<<'PROMPT'
Ты — детерминированный планировщик уведомлений.

Тебе дают:
- CREATED_AT (дата/время создания)
- EXPIRES_AT (дата/время дедлайна или "none")
- DESCRIPTION (свободный текст пользователя с правилами напоминаний)

Твоя задача: вычислить точные времена уведомлений и вернуть ТОЛЬКО список строк.

СТРОГИЙ ФОРМАТ ВЫВОДА:
Уведомление 1: YYYY-MM-DD HH:MM
Уведомление 2: YYYY-MM-DD HH:MM
...

ТРЕБОВАНИЯ:
- Никакого другого текста, объяснений, markdown, JSON, заголовков.
- Если уведомления не нужны — верни пустой ответ (0 строк).
- Даты/время должны быть реальными и соответствовать запросу пользователя.
- Не планируй уведомления раньше CREATED_AT.
- Если EXPIRES_AT не "none", не планируй уведомления позже EXPIRES_AT.
- Сортируй уведомления по времени по возрастанию.
- Максимум 100 уведомлений.
PROMPT),
];


