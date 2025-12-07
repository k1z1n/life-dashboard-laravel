<?php

namespace App\Http\Controllers;

use App\DTOs\TaskDTO;
use App\Http\Requests\ReorderTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Models\Task;
use App\Services\TaskService;

class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService
    ) {}

    public function store(StoreTaskRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        // Обработка project_id - если пустая строка, то null
        if (isset($data['project_id']) && $data['project_id'] === '') {
            $data['project_id'] = null;
        }
        // Обработка tag_ids - если не передано, то пустой массив
        if (!isset($data['tag_ids'])) {
            $data['tag_ids'] = [];
        }

        $dto = TaskDTO::fromArray($data);
        $task = $this->taskService->createTask($dto);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Задача создана!',
                'task' => $task->load(['priority', 'tags', 'project'])
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Задача создана!');
    }

    public function update(UpdateTaskRequest $request, Task $task)
    {
        // Проверяем, что задача принадлежит текущему пользователю
        if ($task->user_id !== auth()->id()) {
            abort(403, 'Доступ запрещен');
        }

        $data = $request->validated();
        
        // Обработка boolean поля
        if (isset($data['completed'])) {
            $data['completed'] = filter_var($data['completed'], FILTER_VALIDATE_BOOLEAN);
        }

        // Обработка project_id - если пустая строка, то null
        if (isset($data['project_id']) && $data['project_id'] === '') {
            $data['project_id'] = null;
        }
        // Обработка tag_ids - если не передано, то пустой массив
        if (!isset($data['tag_ids'])) {
            $data['tag_ids'] = [];
        }

        $dto = TaskDTO::fromArray($data);
        $task = $this->taskService->updateTask($task, $dto);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Задача обновлена!',
                'task' => $task->load(['priority', 'tags', 'project'])
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Задача обновлена!');
    }

    public function toggleComplete(Task $task)
    {
        // Проверяем, что задача принадлежит текущему пользователю
        if ($task->user_id !== auth()->id()) {
            abort(403, 'Доступ запрещен');
        }

        $task = $this->taskService->toggleComplete($task);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Статус задачи изменен!',
                'task' => $task->load(['priority', 'tags', 'project'])
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Статус задачи изменен!');
    }

    public function destroy(Task $task)
    {
        // Проверяем, что задача принадлежит текущему пользователю
        if ($task->user_id !== auth()->id()) {
            abort(403, 'Доступ запрещен');
        }

        $this->taskService->deleteTask($task);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Задача удалена!'
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Задача удалена!');
    }

    public function updateOrder(ReorderTasksRequest $request)
    {
        $taskIds = $request->validated()['task_ids'];
        $userId = auth()->id();
        
        \Log::info('Updating task order', ['task_ids' => $taskIds, 'user_id' => $userId]);
        
        $this->taskService->reorderTasks($taskIds, $userId);

        return response()->json(['success' => true]);
    }
}
