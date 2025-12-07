<?php

namespace App\Repositories;

use App\Contracts\Repositories\TagRepositoryInterface;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Collection;

class TagRepository implements TagRepositoryInterface
{
    public function getAll(int $userId): Collection
    {
        return Tag::where('user_id', $userId)->orderBy('order')->get();
    }

    public function findById(int $id, int $userId): ?Tag
    {
        return Tag::where('user_id', $userId)->find($id);
    }

    public function create(array $data): Tag
    {
        return Tag::create($data);
    }

    public function update(Tag $tag, array $data): Tag
    {
        $tag->update($data);
        return $tag->fresh();
    }

    public function delete(Tag $tag): bool
    {
        return $tag->delete();
    }

    public function hasTasks(Tag $tag): bool
    {
        return $tag->tasks()->count() > 0;
    }
}

