<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile with completed tasks.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Получаем выполненные задачи, сгруппированные по дате выполнения
        $completedTasks = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->whereNotNull('completed_at')
            ->with(['priority', 'project', 'tags'])
            ->orderBy('completed_at', 'desc')
            ->get()
            ->groupBy(function ($task) {
                return $task->completed_at->format('Y-m-d');
            });

        // Статистика
        $totalCompleted = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->count();

        $completedThisWeek = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->where('completed_at', '>=', now()->startOfWeek())
            ->count();

        $completedThisMonth = Task::where('user_id', $user->id)
            ->where('completed', true)
            ->where('completed_at', '>=', now()->startOfMonth())
            ->count();

        return view('profile.index', [
            'user' => $user,
            'completedTasks' => $completedTasks,
            'totalCompleted' => $totalCompleted,
            'completedThisWeek' => $completedThisWeek,
            'completedThisMonth' => $completedThisMonth,
        ]);
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
