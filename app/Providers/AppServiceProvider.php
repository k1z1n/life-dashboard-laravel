<?php

namespace App\Providers;

use App\Contracts\Repositories\PriorityRepositoryInterface;
use App\Contracts\Repositories\ProjectRepositoryInterface;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\TaskRepositoryInterface;
use App\Repositories\PriorityRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TagRepository;
use App\Repositories\TaskRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(TaskRepositoryInterface::class, TaskRepository::class);
        $this->app->bind(PriorityRepositoryInterface::class, PriorityRepository::class);
        $this->app->bind(TagRepositoryInterface::class, TagRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
