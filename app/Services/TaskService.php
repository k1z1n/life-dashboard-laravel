<?php

namespace App\Services;

use App\Contracts\Repositories\TaskRepositoryInterface;
use App\DTOs\TaskDTO;
use App\Models\Task;
use App\Jobs\ScheduleTaskRemindersJob;
use App\Services\Reminders\TaskReminderScheduler;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class TaskService
{
    public function __construct(
        private TaskRepositoryInterface $repository,
        private TaskReminderScheduler $reminderScheduler
    ) {}

    public function getAllTasks(int $userId): Collection
    {
        return $this->repository->getAll($userId);
    }

    public function getTasksByProjectId(?int $projectId, int $userId): Collection
    {
        return $this->repository->getByProjectId($projectId, $userId);
    }

    public function getTaskById(int $id, int $userId): ?Task
    {
        return $this->repository->findById($id, $userId);
    }

    public function createTask(TaskDTO $dto): Task
    {
        $task = $this->repository->create($dto->toArray());

        // Синхронизируем теги
        if (!empty($dto->tagIds)) {
            $task->tags()->sync($dto->tagIds);
        }

        $task = $task->load('tags');

        // Планирование напоминаний в фоне (не блокирует UI)
        // Используем обычный dispatch - job выполнится асинхронно через queue
        ScheduleTaskRemindersJob::dispatch($task->id);

        return $task;
    }

    public function updateTask(Task $task, TaskDTO $dto): Task
    {
        $data = $dto->toArray();

        // Управление completed_at при изменении статуса выполнения
        if (isset($data['completed'])) {
            if ($data['completed'] && !$task->completed) {
                // Задача помечается как выполненная
                $data['completed_at'] = now();
            } elseif (!$data['completed'] && $task->completed) {
                // Задача помечается как невыполненная
                $data['completed_at'] = null;
            }
        }

        $task = $this->repository->update($task, $data);

        // Синхронизируем теги
        $task->tags()->sync($dto->tagIds ?? []);

        $task = $task->load('tags');

        // Планирование напоминаний в фоне (не блокирует UI)
        // Используем обычный dispatch - job выполнится асинхронно через queue
        ScheduleTaskRemindersJob::dispatch($task->id);

        return $task;
    }

    public function deleteTask(Task $task): bool
    {
        $this->reminderScheduler->cancelPending($task);
        return $this->repository->delete($task);
    }

    public function toggleComplete(Task $task): Task
    {
        $newCompletedStatus = !$task->completed;

        $task = $this->repository->update($task, [
            'completed' => $newCompletedStatus,
            'completed_at' => $newCompletedStatus ? now() : null,
        ]);

        if ($newCompletedStatus) {
            // Отмена напоминаний — быстро, можно синхронно
            $this->reminderScheduler->cancelPending($task);
        } else {
            // Планирование напоминаний в фоне
            // Используем обычный dispatch - job выполнится асинхронно через queue
            ScheduleTaskRemindersJob::dispatch($task->id);
        }

        return $task;
    }

    public function reorderTasks(array $taskIds, int $userId): void
    {
        $this->repository->reorder($taskIds, $userId);
    }

}

