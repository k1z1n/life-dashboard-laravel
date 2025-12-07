<?php

namespace App\Http\Controllers;

use App\DTOs\TagDTO;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    public function __construct(
        private TagService $tagService
    ) {}

    public function index(): JsonResponse
    {
        $tags = $this->tagService->getAllTags(auth()->id());
        return response()->json($tags);
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $dto = TagDTO::fromArray($data);
        $tag = $this->tagService->createTag($dto);

        return response()->json($tag, 201);
    }

    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        // Проверяем, что тег принадлежит текущему пользователю
        if ($tag->user_id !== auth()->id()) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $dto = TagDTO::fromArray($request->validated());
        $tag = $this->tagService->updateTag($tag, $dto);

        return response()->json($tag);
    }

    public function destroy(Tag $tag): JsonResponse
    {
        // Проверяем, что тег принадлежит текущему пользователю
        if ($tag->user_id !== auth()->id()) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        try {
            $this->tagService->deleteTag($tag);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
}

