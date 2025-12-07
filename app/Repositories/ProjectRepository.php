<?php

namespace App\Repositories;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository implements ProjectRepositoryInterface
{
    public function getAll(int $userId): Collection
    {
        return Project::where('user_id', $userId)->orderBy('created_at', 'desc')->get();
    }

    public function findById(int $id, int $userId): ?Project
    {
        return Project::where('user_id', $userId)->find($id);
    }

    public function create(array $data): Project
    {
        return Project::create($data);
    }

    public function update(Project $project, array $data): Project
    {
        $project->update($data);
        return $project->fresh();
    }

    public function delete(Project $project): bool
    {
        return $project->delete();
    }
}

