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
        $task = $this->repository->update($task, $dto->toArray());
        
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
        return $this->repository->update($task, [
            'completed' => !$task->completed,
        ]);
    }

    public function reorderTasks(array $taskIds, int $userId): void
    {
        $this->repository->reorder($taskIds, $userId);
    }
}

