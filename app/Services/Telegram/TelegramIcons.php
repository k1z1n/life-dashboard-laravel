<?php

namespace App\Services\Telegram;

/**
 * Красивые Unicode emoji иконки для Telegram бота
 * Организованы по категориям для удобного использования
 */
class TelegramIcons
{
    // ═══════════════════════════════════════════
    // 📋 ЗАДАЧИ
    // ═══════════════════════════════════════════
    public const TASK = '📝';
    public const TASK_LIST = '📋';
    public const TASK_DONE = '✅';
    public const TASK_UNDONE = '⬜';
    public const TASK_NEW = '➕';
    public const TASK_DELETE = '🗑️';
    public const TASK_EDIT = '✏️';

    // ═══════════════════════════════════════════
    // ⚡ ПРИОРИТЕТЫ
    // ═══════════════════════════════════════════
    public const PRIORITY_HIGH = '🔴';
    public const PRIORITY_MEDIUM = '🟡';
    public const PRIORITY_LOW = '🟢';
    public const PRIORITY_NONE = '⚪';
    public const PRIORITY = '⚡';

    // ═══════════════════════════════════════════
    // 📁 ПРОЕКТЫ
    // ═══════════════════════════════════════════
    public const PROJECT = '📁';
    public const PROJECT_NEW = '📂';
    public const FOLDER = '🗂️';

    // ═══════════════════════════════════════════
    // 🏷️ ТЕГИ
    // ═══════════════════════════════════════════
    public const TAG = '🏷️';
    public const TAGS = '🔖';

    // ═══════════════════════════════════════════
    // 📅 ДАТЫ И ВРЕМЯ
    // ═══════════════════════════════════════════
    public const CALENDAR = '📅';
    public const TODAY = '🌅';
    public const TOMORROW = '🌄';
    public const WEEK = '📆';
    public const OVERDUE = '⚠️';
    public const TIME = '🕐';
    public const CLOCK = '⏰';

    // ═══════════════════════════════════════════
    // 📊 СТАТИСТИКА
    // ═══════════════════════════════════════════
    public const STATS = '📊';
    public const CHART_UP = '📈';
    public const CHART_DOWN = '📉';
    public const TROPHY = '🏆';
    public const FIRE = '🔥';
    public const STREAK = '⚡';

    // ═══════════════════════════════════════════
    // 👤 ПОЛЬЗОВАТЕЛЬ
    // ═══════════════════════════════════════════
    public const USER = '👤';
    public const PROFILE = '👤';
    public const SETTINGS = '⚙️';
    public const LINK = '🔗';
    public const UNLINK = '🔓';

    // ═══════════════════════════════════════════
    // 🎯 НАВИГАЦИЯ
    // ═══════════════════════════════════════════
    public const HOME = '🏠';
    public const MENU = '📱';
    public const BACK = '◀️';
    public const FORWARD = '▶️';
    public const REFRESH = '🔄';
    public const SEARCH = '🔍';
    public const INFO = 'ℹ️';
    public const HELP = '❓';

    // ═══════════════════════════════════════════
    // ✨ СТАТУСЫ
    // ═══════════════════════════════════════════
    public const SUCCESS = '✅';
    public const ERROR = '❌';
    public const WARNING = '⚠️';
    public const LOADING = '⏳';
    public const STAR = '⭐';
    public const SPARKLE = '✨';

    // ═══════════════════════════════════════════
    // 🔔 УВЕДОМЛЕНИЯ
    // ═══════════════════════════════════════════
    public const BELL = '🔔';
    public const BELL_OFF = '🔕';
    public const NOTIFICATION = '💬';

    // ═══════════════════════════════════════════
    // 🌐 РАЗНОЕ
    // ═══════════════════════════════════════════
    public const WEB = '🌐';
    public const ROBOT = '🤖';
    public const WAVE = '👋';
    public const PARTY = '🎉';
    public const ROCKET = '🚀';
    public const BULB = '💡';
    public const TARGET = '🎯';
    public const INBOX = '📥';
    public const OUTBOX = '📤';

    // ═══════════════════════════════════════════
    // 🔢 ЦИФРЫ
    // ═══════════════════════════════════════════
    public const NUM_1 = '1️⃣';
    public const NUM_2 = '2️⃣';
    public const NUM_3 = '3️⃣';
    public const NUM_4 = '4️⃣';
    public const NUM_5 = '5️⃣';

    /**
     * Получить иконку приоритета по order
     */
    public static function getPriorityIcon(int $order): string
    {
        return match($order) {
            3 => self::PRIORITY_HIGH,
            2 => self::PRIORITY_MEDIUM,
            1 => self::PRIORITY_LOW,
            default => self::PRIORITY_NONE
        };
    }

    /**
     * Получить иконку статуса задачи
     */
    public static function getTaskStatusIcon(bool $completed): string
    {
        return $completed ? self::TASK_DONE : self::TASK_UNDONE;
    }

    /**
     * Получить цифру как emoji
     */
    public static function getNumberIcon(int $number): string
    {
        $numbers = [
            1 => self::NUM_1,
            2 => self::NUM_2,
            3 => self::NUM_3,
            4 => self::NUM_4,
            5 => self::NUM_5,
        ];

        return $numbers[$number] ?? (string) $number;
    }
}

