<?php

namespace App\Services;

use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\DTOs\ProjectDTO;
use App\Models\Project;

class ProjectService
{
    public function __construct(
        private ProjectRepositoryInterface $repository
    ) {}

    public function getAllProjects(int $userId): array
    {
        return $this->repository->getAll($userId)->all();
    }

    public function getProjectById(int $id, int $userId): ?Project
    {
        return $this->repository->findById($id, $userId);
    }

    public function createProject(ProjectDTO $dto): Project
    {
        return $this->repository->create($dto->toArray());
    }

    public function updateProject(Project $project, ProjectDTO $dto): Project
    {
        return $this->repository->update($project, $dto->toArray());
    }

    public function deleteProject(Project $project): bool
    {
        return $this->repository->delete($project);
    }
}

