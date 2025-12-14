<?php

namespace Tests\Unit;

use App\Exceptions\Ai\NotificationAgentParseException;
use App\Services\Ai\YandexGptClient;
use App\Services\Reminders\NotificationTimeAgent;
use Mockery;
use Tests\TestCase;

class NotificationTimeAgentParseTest extends TestCase
{
    public function test_parses_empty_response_as_empty_schedule(): void
    {
        $agent = new NotificationTimeAgent(Mockery::mock(YandexGptClient::class));

        $this->assertSame([], $agent->parseSchedule(''));
        $this->assertSame([], $agent->parseSchedule("\n\n"));
    }

    public function test_parses_valid_lines(): void
    {
        $agent = new NotificationTimeAgent(Mockery::mock(YandexGptClient::class));

        $times = $agent->parseSchedule(
            "Уведомление 1: 2025-12-14 10:00\n" .
            "Уведомление 2: 2025-12-14 12:30\n"
        );

        $this->assertCount(2, $times);
        $this->assertSame('2025-12-14 10:00', $times[0]->format('Y-m-d H:i'));
        $this->assertSame('2025-12-14 12:30', $times[1]->format('Y-m-d H:i'));
    }

    public function test_throws_on_invalid_format(): void
    {
        $agent = new NotificationTimeAgent(Mockery::mock(YandexGptClient::class));

        $this->expectException(NotificationAgentParseException::class);

        $agent->parseSchedule("Неправильный формат 2025-12-14 10:00\n");
    }
}


