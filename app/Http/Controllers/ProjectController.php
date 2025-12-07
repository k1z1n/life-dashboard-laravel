<?php

namespace App\Http\Controllers;

use App\DTOs\ProjectDTO;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Services\ProjectService;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projectService
    ) {}

    public function store(StoreProjectRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $dto = ProjectDTO::fromArray($data);
        $project = $this->projectService->createProject($dto);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Проект успешно создан!',
                'project' => $project
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Проект успешно создан!');
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        // Проверяем, что проект принадлежит текущему пользователю
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Доступ запрещен');
        }

        $dto = ProjectDTO::fromArray($request->validated());
        $project = $this->projectService->updateProject($project, $dto);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Проект обновлен!',
                'project' => $project
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Проект обновлен!');
    }

    public function destroy(Project $project)
    {
        // Проверяем, что проект принадлежит текущему пользователю
        if ($project->user_id !== auth()->id()) {
            abort(403, 'Доступ запрещен');
        }

        $this->projectService->deleteProject($project);

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Проект удален!'
            ]);
        }

        return redirect()->route('dashboard')->with('success', 'Проект удален!');
    }
}
