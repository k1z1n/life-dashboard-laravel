<?php

namespace App\Contracts\Repositories;

use App\Models\Priority;
use Illuminate\Database\Eloquent\Collection;

interface PriorityRepositoryInterface
{
    public function getAll(int $userId): Collection;
    public function findById(int $id, int $userId): ?Priority;
    public function create(array $data): Priority;
    public function update(Priority $priority, array $data): Priority;
    public function delete(Priority $priority): bool;
    public function hasTasks(Priority $priority): bool;
}

