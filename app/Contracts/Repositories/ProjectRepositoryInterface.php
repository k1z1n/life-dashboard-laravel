<?php

namespace App\Contracts\Repositories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

interface ProjectRepositoryInterface
{
    public function getAll(int $userId): Collection;
    public function findById(int $id, int $userId): ?Project;
    public function create(array $data): Project;
    public function update(Project $project, array $data): Project;
    public function delete(Project $project): bool;
}

