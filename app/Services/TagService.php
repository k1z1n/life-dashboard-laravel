<?php

namespace App\Services;

use App\Contracts\Repositories\TagRepositoryInterface;
use App\DTOs\TagDTO;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class TagService
{
    public function __construct(
        private TagRepositoryInterface $repository
    ) {}

    public function getAllTags(int $userId): Collection
    {
        return $this->repository->getAll($userId);
    }

    public function getTagById(int $id, int $userId): ?Tag
    {
        return $this->repository->findById($id, $userId);
    }

    public function createTag(TagDTO $dto): Tag
    {
        $data = $dto->toArray();
        
        // Если order не указан, устанавливаем максимальный + 1
        if (!isset($data['order']) && isset($data['user_id'])) {
            $allTags = $this->repository->getAll($data['user_id']);
            $maxOrder = $allTags->max('order') ?? 0;
            $data['order'] = $maxOrder + 1;
        }
        
        return $this->repository->create($data);
    }

    public function updateTag(Tag $tag, TagDTO $dto): Tag
    {
        return $this->repository->update($tag, $dto->toArray());
    }

    public function deleteTag(Tag $tag): bool
    {
        // Проверяем, используется ли тег
        if ($this->repository->hasTasks($tag)) {
            throw new \Exception('Нельзя удалить тег, который используется в задачах');
        }
        
        return $this->repository->delete($tag);
    }
}

