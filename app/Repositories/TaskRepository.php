<?php

namespace App\Repositories;

use App\Contracts\Repositories\TaskRepositoryInterface;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

class TaskRepository implements TaskRepositoryInterface
{
    public function getAll(int $userId): Collection
    {
        return Task::with(['priority', 'tags'])
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->where('completed', false)
                    ->orWhere(function ($q) {
                        $q->where('completed', true)
                          ->whereDate('completed_at', '>=', now()->startOfDay());
                    });
            })
            ->orderBy('completed')
            ->orderBy('order', 'asc')
            ->orderByRaw('(SELECT `order` FROM priorities WHERE priorities.id = tasks.priority_id) DESC')
            ->orderBy('due_date', 'asc')
            ->get();
    }

    public function getByProjectId(?int $projectId, int $userId): Collection
    {
        $query = Task::with(['priority', 'tags'])
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->where('completed', false)
                    ->orWhere(function ($q) {
                        $q->where('completed', true)
                          ->whereDate('completed_at', '>=', now()->startOfDay());
                    });
            })
            ->orderBy('completed')
            ->orderBy('order', 'asc')
            ->orderByRaw('(SELECT `order` FROM priorities WHERE priorities.id = tasks.priority_id) DESC')
            ->orderBy('due_date', 'asc');

        if ($projectId === null) {
            $query->whereNull('project_id');
        } else {
            $query->where('project_id', $projectId);
        }

        return $query->get();
    }

    public function findById(int $id, int $userId): ?Task
    {
        return Task::with(['priority', 'tags'])->where('user_id', $userId)->find($id);
    }

    public function create(array $data): Task
    {
        return Task::create($data);
    }

    public function update(Task $task, array $data): Task
    {
        $task->update($data);
        return $task->fresh();
    }

    public function delete(Task $task): bool
    {
        return $task->delete();
    }

    public function reorder(array $taskIds, int $userId): void
    {
        \Log::info('TaskRepository::reorder called', ['task_ids' => $taskIds, 'user_id' => $userId]);

        foreach ($taskIds as $index => $taskId) {
            $order = $index + 1;
            Task::where('id', $taskId)->where('user_id', $userId)->update(['order' => $order]);
            \Log::info('Updated task order', ['task_id' => $taskId, 'order' => $order]);
        }
    }
}

