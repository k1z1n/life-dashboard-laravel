<?php

namespace App\Contracts\Repositories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

interface TagRepositoryInterface
{
    public function getAll(int $userId): Collection;
    public function findById(int $id, int $userId): ?Tag;
    public function create(array $data): Tag;
    public function update(Tag $tag, array $data): Tag;
    public function delete(Tag $tag): bool;
    public function hasTasks(Tag $tag): bool;
}

