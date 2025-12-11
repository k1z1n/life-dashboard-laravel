<?php

namespace App\Http\Controllers;

use App\Services\PriorityService;
use App\Services\ProjectService;
use App\Services\TagService;
use App\Services\TaskService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private ProjectService $projectService,
        private TaskService $taskService,
        private PriorityService $priorityService,
        private TagService $tagService
    ) {}

    public function index()
    {
        $userId = auth()->id();

        // Получаем все проекты для вкладок
        $projects = $this->projectService->getAllProjects($userId);

        // Получаем все приоритеты
        $priorities = $this->priorityService->getAllPriorities($userId);

        // Получаем все теги
        $tags = $this->tagService->getAllTags($userId);

        // Получаем все задачи
        $allTasks = $this->taskService->getAllTasks($userId);

        // Группируем задачи по project_id для отображения в вкладках
        $tasksByProject = [];
        foreach ($projects as $project) {
            $tasksByProject[$project->id] = $this->taskService->getTasksByProjectId($project->id, $userId);
        }

        // Задачи без проекта (null project_id)
        $tasksWithoutProject = $this->taskService->getTasksByProjectId(null, $userId);

        $viewData = compact('projects', 'priorities', 'tags', 'allTasks', 'tasksByProject', 'tasksWithoutProject');

        // Если это AJAX запрос, возвращаем только HTML контент
        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'html' => view('dashboard', $viewData)->render()
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
              ->header('Pragma', 'no-cache')
              ->header('Expires', '0');
        }

        return response()->view('dashboard', $viewData)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
