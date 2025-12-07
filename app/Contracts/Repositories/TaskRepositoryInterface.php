<?php

namespace App\Contracts\Repositories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;

interface TaskRepositoryInterface
{
    public function getAll(int $userId): Collection;
    public function getByProjectId(?int $projectId, int $userId): Collection;
    public function findById(int $id, int $userId): ?Task;
    public function create(array $data): Task;
    public function update(Task $task, array $data): Task;
    public function delete(Task $task): bool;
    public function reorder(array $taskIds, int $userId): void;
}

