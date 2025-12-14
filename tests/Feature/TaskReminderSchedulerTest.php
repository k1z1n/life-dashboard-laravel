<?php

namespace Tests\Feature;

use App\Jobs\SendTelegramTaskReminderJob;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Models\User;
use App\Services\Reminders\NotificationTimeAgent;
use App\Services\Reminders\TaskReminderScheduler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskReminderSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reschedule_creates_pending_reminders_and_dispatches_jobs(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Test task',
            'reminder_text' => 'каждый час до 18:00',
            'completed' => false,
        ]);

        $agent = new class extends NotificationTimeAgent {
            public function __construct() {}

            public function computeSchedule(Carbon $createdAt, string $description, ?Carbon $expiresAt = null): array
            {
                return [
                    now()->addMinutes(10),
                    now()->addMinutes(20),
                ];
            }
        };

        $scheduler = new TaskReminderScheduler($agent);
        $scheduler->reschedule($task);

        $this->assertDatabaseCount('task_reminders', 2);
        $this->assertDatabaseHas('task_reminders', [
            'task_id' => $task->id,
            'user_id' => $user->id,
            'status' => TaskReminder::STATUS_PENDING,
            'channel' => 'telegram',
        ]);

        Queue::assertPushed(SendTelegramTaskReminderJob::class, 2);
    }

    public function test_reschedule_cancels_previous_pending_reminders(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Test task',
            'reminder_text' => 'x',
            'completed' => false,
        ]);

        TaskReminder::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'channel' => 'telegram',
            'send_at' => now()->addHour(),
            'status' => TaskReminder::STATUS_PENDING,
            'source_text' => 'x',
        ]);

        $agent = new class extends NotificationTimeAgent {
            public function __construct() {}

            public function computeSchedule(Carbon $createdAt, string $description, ?Carbon $expiresAt = null): array
            {
                return [now()->addMinutes(30)];
            }
        };

        $scheduler = new TaskReminderScheduler($agent);
        $scheduler->reschedule($task);

        $this->assertDatabaseCount('task_reminders', 2);
        $this->assertDatabaseHas('task_reminders', [
            'task_id' => $task->id,
            'status' => TaskReminder::STATUS_CANCELLED,
        ]);
        $this->assertDatabaseHas('task_reminders', [
            'task_id' => $task->id,
            'status' => TaskReminder::STATUS_PENDING,
        ]);
    }

    public function test_completed_task_cancels_pending_reminders(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'user_id' => $user->id,
            'title' => 'Test task',
            'reminder_text' => 'x',
            'completed' => true,
        ]);

        TaskReminder::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'channel' => 'telegram',
            'send_at' => now()->addHour(),
            'status' => TaskReminder::STATUS_PENDING,
            'source_text' => 'x',
        ]);

        $agent = new class extends NotificationTimeAgent {
            public function __construct() {}
        };

        $scheduler = new TaskReminderScheduler($agent);
        $scheduler->reschedule($task);

        $this->assertDatabaseHas('task_reminders', [
            'task_id' => $task->id,
            'status' => TaskReminder::STATUS_CANCELLED,
        ]);
    }
}


