<?php

namespace App\Http\Controllers;

use App\DTOs\PriorityDTO;
use App\Http\Requests\StorePriorityRequest;
use App\Http\Requests\UpdatePriorityRequest;
use App\Models\Priority;
use App\Services\PriorityService;
use Illuminate\Http\JsonResponse;

class PriorityController extends Controller
{
    public function __construct(
        private PriorityService $priorityService
    ) {}

    public function index(): JsonResponse
    {
        $priorities = $this->priorityService->getAllPriorities(auth()->id());
        return response()->json($priorities);
    }

    public function store(StorePriorityRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $dto = PriorityDTO::fromArray($data);
        $priority = $this->priorityService->createPriority($dto);

        return response()->json($priority, 201);
    }

    public function update(UpdatePriorityRequest $request, Priority $priority): JsonResponse
    {
        // Проверяем, что приоритет принадлежит текущему пользователю
        if ($priority->user_id !== auth()->id()) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        $dto = PriorityDTO::fromArray($request->validated());
        $priority = $this->priorityService->updatePriority($priority, $dto);

        return response()->json($priority);
    }

    public function destroy(Priority $priority): JsonResponse
    {
        // Проверяем, что приоритет принадлежит текущему пользователю
        if ($priority->user_id !== auth()->id()) {
            return response()->json(['error' => 'Доступ запрещен'], 403);
        }

        try {
            $this->priorityService->deletePriority($priority);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
