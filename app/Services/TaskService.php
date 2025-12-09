<?php

namespace App\Services;

use App\Contracts\Repositories\TaskRepositoryInterface;
use App\DTOs\TaskDTO;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class TaskService
{
    public function __construct(
        private TaskRepositoryInterface $repository
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

        return $task->load('tags');
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

        return $task->load('tags');
    }

    public function deleteTask(Task $task): bool
    {
        return $this->repository->delete($task);
    }

    public function toggleComplete(Task $task): Task
    {
        $newCompletedStatus = !$task->completed;

        return $this->repository->update($task, [
            'completed' => $newCompletedStatus,
            'completed_at' => $newCompletedStatus ? now() : null,
        ]);
    }

    public function reorderTasks(array $taskIds, int $userId): void
    {
        $this->repository->reorder($taskIds, $userId);
    }
}

