<?php

namespace App\Repositories;

use App\Contracts\Repositories\PriorityRepositoryInterface;
use App\Models\Priority;
use Illuminate\Database\Eloquent\Collection;

class PriorityRepository implements PriorityRepositoryInterface
{
    public function getAll(int $userId): Collection
    {
        return Priority::where('user_id', $userId)->orderBy('order')->get();
    }

    public function findById(int $id, int $userId): ?Priority
    {
        return Priority::where('user_id', $userId)->find($id);
    }

    public function create(array $data): Priority
    {
        return Priority::create($data);
    }

    public function update(Priority $priority, array $data): Priority
    {
        $priority->update($data);
        return $priority->fresh();
    }

    public function delete(Priority $priority): bool
    {
        return $priority->delete();
    }

    public function hasTasks(Priority $priority): bool
    {
        return $priority->tasks()->count() > 0;
    }
}

