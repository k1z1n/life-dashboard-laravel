<?php

namespace App\Services;

use App\Contracts\Repositories\PriorityRepositoryInterface;
use App\DTOs\PriorityDTO;
use App\Models\Priority;
use Illuminate\Database\Eloquent\Collection;

class PriorityService
{
    public function __construct(
        private PriorityRepositoryInterface $repository
    ) {}

    public function getAllPriorities(int $userId): Collection
    {
        return $this->repository->getAll($userId);
    }

    public function getPriorityById(int $id, int $userId): ?Priority
    {
        return $this->repository->findById($id, $userId);
    }

    public function createPriority(PriorityDTO $dto): Priority
    {
        $data = $dto->toArray();
        
        // Если order не указан, устанавливаем максимальный + 1
        if (!isset($data['order']) && isset($data['user_id'])) {
            $allPriorities = $this->repository->getAll($data['user_id']);
            $maxOrder = $allPriorities->max('order') ?? 0;
            $data['order'] = $maxOrder + 1;
        }
        
        return $this->repository->create($data);
    }

    public function updatePriority(Priority $priority, PriorityDTO $dto): Priority
    {
        return $this->repository->update($priority, $dto->toArray());
    }

    public function deletePriority(Priority $priority): bool
    {
        // Проверяем, используется ли приоритет
        if ($this->repository->hasTasks($priority)) {
            throw new \Exception('Нельзя удалить приоритет, который используется в задачах');
        }
        
        return $this->repository->delete($priority);
    }
}

