<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#3b82f6">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Life Dashboard">
    <title>Life Dashboard - Управление задачами</title>
    <link rel="manifest" href="/manifest.json">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script>
        // Восстанавливаем выбранный проект ДО загрузки DOM, чтобы избежать визуального переключения
        (function() {
            try {
                var savedTabId = localStorage.getItem('selectedProjectTab');
                if (savedTabId && savedTabId !== 'undefined' && savedTabId !== 'null') {
                    document.documentElement.setAttribute('data-selected-tab', savedTabId);
                } else {
                    document.documentElement.setAttribute('data-selected-tab', 'all');
                }
            } catch (e) {
                document.documentElement.setAttribute('data-selected-tab', 'all');
            }
        })();
    </script>
    <style>
        /* Скрываем все вкладки по умолчанию, кроме выбранной */
        .tab-content {
            display: none !important;
        }
        html[data-selected-tab="all"] #tab-content-all { display: block !important; }
        @foreach($projects as $project)
        html[data-selected-tab="{{ $project->id }}"] #tab-content-{{ $project->id }} { display: block !important; }
        @endforeach

        /* Стили для активной кнопки */
        html[data-selected-tab="all"] #tab-all,
        @foreach($projects as $project)
        html[data-selected-tab="{{ $project->id }}"] #tab-{{ $project->id }},
        @endforeach
        .tab-button.active {
            background-color: rgb(37, 99, 235) !important;
            color: white !important;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
        }
    </style>
    <style>
        html, body {
            overflow-x: hidden;
            max-width: 100vw;
        }
        * {
            box-sizing: border-box;
        }
        .task-item-wrapper.dragging {
            opacity: 0.6 !important;
            transform: scale(0.96) !important;
            z-index: 50;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        }
        .task-item-wrapper:not(.dragging) {
            transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.4s ease-out, box-shadow 0.4s ease-out;
        }
        /* Анимация для мобильных устройств при touch */
        .task-item-wrapper.touch-dragging {
            opacity: 0.6 !important;
            transform: scale(0.96) !important;
            z-index: 50;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
            transition: none !important;
        }
        .task-item-wrapper:not(.touch-dragging):not(.dragging) {
            transition: transform 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.35s ease-out, box-shadow 0.35s ease-out;
        }
        .task-item-wrapper.drag-over-top {
            transform: translateY(8px) scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.1), 0 4px 6px -2px rgba(59, 130, 246, 0.05);
            transition: transform 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94), box-shadow 0.35s ease-out;
        }
        .task-item-wrapper.drag-over-bottom {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.1), 0 4px 6px -2px rgba(59, 130, 246, 0.05);
            transition: transform 0.35s cubic-bezier(0.25, 0.46, 0.45, 0.94), box-shadow 0.35s ease-out;
        }
        .task-item.drag-target {
            border-color: rgb(59, 130, 246) !important;
            background-color: rgb(239, 246, 255) !important;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.2), 0 4px 6px -2px rgba(59, 130, 246, 0.1) !important;
        }
        @media (max-width: 640px) {
            .task-item-wrapper.drag-over-top {
                transform: translateY(6px) scale(1.01);
                transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), box-shadow 0.4s ease-out;
            }
            .task-item-wrapper.drag-over-bottom {
                transform: translateY(-6px) scale(1.01);
                transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), box-shadow 0.4s ease-out;
            }
            /* Плавная анимация для всех элементов при перемещении на мобильных */
            .task-item-wrapper:not(.touch-dragging):not(.dragging) {
                transition: transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.5s ease-out, box-shadow 0.5s ease-out;
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50/30 to-purple-50/30 min-h-screen overflow-x-hidden">
    <div class="container mx-auto px-3 sm:px-4 lg:px-8 py-6 sm:py-8 max-w-7xl w-full">
        <!-- Header -->
        <header class="mb-6 sm:mb-8">
            <div class="flex items-center gap-3 sm:gap-4 mb-3">
                <div class="p-3 bg-gradient-to-br from-blue-500 to-purple-600 rounded-xl shadow-lg flex-shrink-0">
                    <svg class="w-7 h-7 sm:w-8 sm:h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="text-3xl sm:text-4xl font-bold text-slate-900 leading-tight">Life Dashboard</h1>
                    <p class="text-sm text-slate-600 mt-1">{{ auth()->user()->name }}</p>
                </div>
                <form action="{{ route('logout') }}" method="POST" class="flex-shrink-0">
                    @csrf
                    <button type="submit" class="inline-flex items-center justify-center gap-2 bg-slate-600 hover:bg-slate-700 text-white px-4 sm:px-5 py-2 sm:py-2.5 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 text-sm sm:text-base">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Выйти
                    </button>
                </form>
            </div>
        </header>

        <!-- Success Message -->
        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-500 text-green-800 p-4 mb-6 rounded-lg flex items-start sm:items-center gap-3 shadow-sm">
                <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span class="flex-1">{{ session('success') }}</span>
            </div>
        @endif

        <!-- Mobile Actions Bar (только на мобильных) -->
        <div id="mobileActionsBar" class="mb-4 sm:hidden flex items-center justify-between gap-3">
            <button onclick="window.openTaskModal()" class="flex-shrink-0 inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </button>
            <button onclick="toggleMobileActions()" class="flex-1 inline-flex items-center justify-center gap-2 bg-slate-600 hover:bg-slate-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                Действия
            </button>
        </div>

        <!-- Mobile Actions Menu (скрыто по умолчанию) -->
        <div id="mobileActionsMenu" class="sm:hidden hidden">
            <!-- Create Project Modal Trigger -->
            <div class="mb-4 flex flex-col gap-3">
                <button onclick="window.openProjectModal(); closeMobileActions();" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 mb-0">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Новый проект
                </button>
                <button onclick="window.openTaskModal(); closeMobileActions();" class="inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Новая задача
                </button>
                <button onclick="window.openPriorityModal(); closeMobileActions();" class="inline-flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    Приоритеты
                </button>
                <button onclick="window.openTagModal(); closeMobileActions();" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2.5 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                    </svg>
                    Теги
                </button>
            </div>

            <!-- Кнопка скрыть (после кнопок действий) -->
            <button onclick="closeMobileActions()" class="w-full inline-flex items-center justify-center gap-2 bg-slate-500 hover:bg-slate-600 text-white px-5 py-2.5 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Скрыть
            </button>
        </div>

        <!-- Tabs Navigation (всегда видимы на мобильных) -->
        <div class="bg-white rounded-xl shadow-md mb-6 overflow-hidden w-full sm:hidden">
            <div class="flex items-center gap-1 p-2 overflow-x-auto scrollbar-hide w-full">
                <!-- Tab: Все задачи -->
                <button onclick="switchTab('all')"
                        id="tab-all-mobile"
                        class="tab-button flex items-center gap-2 px-4 py-2.5 rounded-lg font-medium text-sm whitespace-nowrap transition-all duration-200 text-slate-700 hover:bg-slate-100 hover:text-slate-900">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <span>Все задачи</span>
                    @if($allTasks->count() > 0)
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-700">{{ $allTasks->count() }}</span>
                    @endif
                </button>

                <!-- Tabs: Проекты -->
                @foreach($projects as $project)
                    <button onclick="switchTab({{ $project->id }})"
                            id="tab-{{ $project->id }}-mobile"
                            class="tab-button flex items-center gap-2 px-4 py-2.5 rounded-lg font-medium text-sm whitespace-nowrap transition-all duration-200 text-slate-700 hover:bg-slate-100 hover:text-slate-900">
                        <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $project->color }}"></div>
                        <span>{{ $project->name }}</span>
                        @if(isset($tasksByProject[$project->id]) && $tasksByProject[$project->id]->count() > 0)
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-700">{{ $tasksByProject[$project->id]->count() }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Create Project Modal Trigger (только на десктопе) -->
        <div class="mb-6 sm:mb-8 hidden sm:flex flex-col sm:flex-row gap-3">
            <button onclick="window.openProjectModal()" class="inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-sm sm:text-base">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Новый проект
            </button>
            <button onclick="window.openTaskModal()" class="inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 text-sm sm:text-base">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Новая задача
            </button>
            <button onclick="window.openPriorityModal()" class="inline-flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 text-sm sm:text-base">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                Приоритеты
            </button>
            <button onclick="window.openTagModal()" class="inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-5 sm:px-6 py-2.5 sm:py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 text-sm sm:text-base">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                </svg>
                Теги
            </button>
        </div>

        <!-- Tabs Navigation (только на десктопе) -->
        <div class="bg-white rounded-xl shadow-md mb-6 overflow-hidden w-full hidden sm:block">
            <div class="flex items-center gap-1 p-2 overflow-x-auto scrollbar-hide w-full">
                <!-- Tab: Все задачи -->
                <button onclick="switchTab('all')"
                        id="tab-all"
                        class="tab-button flex items-center gap-2 px-4 py-2.5 rounded-lg font-medium text-sm sm:text-base whitespace-nowrap transition-all duration-200 text-slate-700 hover:bg-slate-100 hover:text-slate-900">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <span>Все задачи</span>
                    @if($allTasks->count() > 0)
                        <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-white/20">{{ $allTasks->count() }}</span>
                    @endif
                </button>

                <!-- Tabs: Проекты -->
                @foreach($projects as $project)
                    <button onclick="switchTab({{ $project->id }})"
                            id="tab-{{ $project->id }}"
                            class="tab-button flex items-center gap-2 px-4 py-2.5 rounded-lg font-medium text-sm sm:text-base whitespace-nowrap transition-all duration-200 text-slate-700 hover:bg-slate-100 hover:text-slate-900">
                        <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $project->color }}"></div>
                        <span>{{ $project->name }}</span>
                        @if(isset($tasksByProject[$project->id]) && $tasksByProject[$project->id]->count() > 0)
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-700">{{ $tasksByProject[$project->id]->count() }}</span>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

        <!-- Tab Content: Все задачи -->
        <div id="tab-content-all" class="tab-content">
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 mb-5 sm:mb-6 flex items-center gap-3">
                <div class="p-2 bg-blue-100 rounded-lg flex-shrink-0">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                Все задачи
            </h2>
            @if($allTasks->count() > 0)
                    <div class="tasks-container space-y-0 w-full" data-tab="all">
                    @foreach($allTasks as $task)
                        @include('partials.task-card', ['task' => $task])
                    @endforeach
                </div>
            @else
                <div class="text-center py-12 sm:py-16">
                    <svg class="w-16 h-16 sm:w-20 sm:h-20 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <p class="text-slate-400 text-base sm:text-lg mb-5 sm:mb-6">Нет задач. Создайте первую задачу!</p>
                    <button onclick="window.openTaskModal()" class="inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl text-sm font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Создать задачу
                    </button>
                </div>
            @endif
        </div>

        <!-- Tab Content: Проекты -->
        @foreach($projects as $project)
            <div id="tab-content-{{ $project->id }}" class="tab-content">
                <div class="flex items-center justify-between mb-5 sm:mb-6">
                    <h2 class="text-xl sm:text-2xl font-bold text-slate-900 flex items-center gap-3">
                        <div class="p-2 rounded-lg flex-shrink-0" style="background-color: {{ $project->color }}20">
                            <div class="w-5 h-5 sm:w-6 sm:h-6 rounded-full" style="background-color: {{ $project->color }}"></div>
                        </div>
                        {{ $project->name }}
                    </h2>
                    <div class="flex gap-2">
                        <button onclick="window.openEditProjectModal({{ $project->id }}, '{{ addslashes($project->name) }}', '{{ addslashes($project->description ?? '') }}', '{{ $project->color }}')"
                                class="p-2 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                        </button>
                        <button type="button" onclick="deleteProject({{ $project->id }})" class="p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                @if(isset($tasksByProject[$project->id]) && $tasksByProject[$project->id]->count() > 0)
                    <div class="tasks-container space-y-0 w-full" data-tab="{{ $project->id }}">
                        @foreach($tasksByProject[$project->id] as $task)
                            @include('partials.task-card', ['task' => $task])
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-12 sm:py-16">
                        <svg class="w-16 h-16 sm:w-20 sm:h-20 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        <p class="text-slate-400 text-base sm:text-lg mb-5 sm:mb-6">Нет задач в этом проекте</p>
                        <button onclick="window.openTaskModal({{ $project->id }})" class="inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl text-sm font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Создать задачу
                        </button>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <!-- Project Modal -->
    <div id="projectModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto" onclick="if(event.target === this) window.closeProjectModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-5 sm:p-6 w-full max-w-md my-4 transform transition-all" onclick="event.stopPropagation()">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-blue-100 rounded-lg flex-shrink-0">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                    </svg>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-900 leading-tight" id="projectModalTitle">Новый проект</h3>
            </div>
            <form id="projectForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="projectMethod" value="POST">
                <input type="hidden" name="project_id" id="projectId">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Название</label>
                    <input type="text" name="name" id="projectName" required
                           class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Описание</label>
                    <textarea name="description" id="projectDescription" rows="3"
                              class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"></textarea>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Цвет</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="color" id="projectColor" value="#3b82f6"
                               class="w-16 h-12 border-2 border-slate-300 rounded-xl cursor-pointer">
                        <input type="text" value="#3b82f6" id="projectColorText"
                               class="flex-1 px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors font-mono text-sm">
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mt-6 pt-4 border-t border-slate-200">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Сохранить
                    </button>
                    <button type="button" onclick="window.closeProjectModal()"
                            class="px-6 py-3 border border-slate-300 rounded-xl hover:bg-slate-50 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 font-medium text-slate-700">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Task Modal -->
    <div id="taskModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto" onclick="if(event.target === this) window.closeTaskModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-5 sm:p-6 w-full max-w-md my-4 transform transition-all max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-green-100 rounded-lg flex-shrink-0">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-900 leading-tight" id="taskModalTitle">Новая задача</h3>
            </div>
            <form id="taskForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="taskMethod" value="POST">
                <input type="hidden" name="task_id" id="taskId">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Проект</label>
                    <select name="project_id" id="taskProjectId"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <option value="">Без проекта</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Название</label>
                    <input type="text" name="title" id="taskTitle" required
                           class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Описание</label>
                    <textarea name="description" id="taskDescription" rows="3"
                              class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors resize-none"></textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Приоритет</label>
                    <select name="priority_id" id="taskPriority"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        <option value="">Без приоритета</option>
                        @foreach($priorities as $priority)
                            <option value="{{ $priority->id }}" style="color: {{ $priority->color }}">{{ $priority->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Теги</label>
                    <div class="space-y-2 max-h-32 overflow-y-auto border border-slate-300 rounded-xl p-3">
                        @foreach($tags as $tag)
                            <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-2 rounded-lg transition-colors">
                                <input type="checkbox" name="tag_ids[]" value="{{ $tag->id }}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium text-white" style="background-color: {{ $tag->color }}">
                                    {{ $tag->name }}
                                </span>
                            </label>
                        @endforeach
                        @if(count($tags) === 0)
                            <p class="text-sm text-slate-500 text-center py-2">Нет тегов. Создайте теги в меню "Теги"</p>
                        @endif
                    </div>
                </div>

                <div class="mb-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Срок (дата)</label>
                        <input type="date" name="due_date" id="taskDueDate"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Время (если срок сегодня)</label>
                        <input type="time" name="due_time" id="taskDueTime"
                               class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mt-6 pt-4 border-t border-slate-200">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Сохранить
                    </button>
                    <button type="button" onclick="window.closeTaskModal()"
                            class="px-6 py-3 border border-slate-300 rounded-xl hover:bg-slate-50 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 font-medium text-slate-700">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Project Modal
        // Блокировка скролла body при открытии модального окна
        function lockBodyScroll() {
            document.body.style.overflow = 'hidden';
        }

        // Разблокировка скролла body при закрытии модального окна
        function unlockBodyScroll() {
            document.body.style.overflow = '';
        }

        window.openProjectModal = function() {
            document.getElementById('projectModal').classList.remove('hidden');
            lockBodyScroll();
            document.getElementById('projectForm').action = '{{ route("projects.store") }}';
            document.getElementById('projectMethod').value = 'POST';
            document.getElementById('projectModalTitle').textContent = 'Новый проект';
            document.getElementById('projectForm').reset();
            document.getElementById('projectColor').value = '#3b82f6';
            document.getElementById('projectColorText').value = '#3b82f6';
        };

        window.openEditProjectModal = function(id, name, description, color, type) {
            document.getElementById('projectModal').classList.remove('hidden');
            lockBodyScroll();
            document.getElementById('projectForm').action = `/projects/${id}`;
            document.getElementById('projectMethod').value = 'PUT';
            document.getElementById('projectId').value = id;
            document.getElementById('projectModalTitle').textContent = 'Редактировать проект';
            document.getElementById('projectName').value = name;
            document.getElementById('projectDescription').value = description || '';
            document.getElementById('projectColor').value = color;
            document.getElementById('projectColorText').value = color;
        };

        window.closeProjectModal = function() {
            document.getElementById('projectModal').classList.add('hidden');
            unlockBodyScroll();
        };

        // Task Modal
        // Tab switching
        // Функции для управления мобильным меню
        function toggleMobileActions() {
            const menu = document.getElementById('mobileActionsMenu');
            const bar = document.getElementById('mobileActionsBar');

            if (menu && bar) {
                const isHidden = menu.classList.contains('hidden');
                if (isHidden) {
                    // Открываем меню
                    menu.classList.remove('hidden');
                    bar.classList.add('hidden');
                } else {
                    // Закрываем меню
                    menu.classList.add('hidden');
                    bar.classList.remove('hidden');
                }
            }
        }

        function closeMobileActions() {
            const menu = document.getElementById('mobileActionsMenu');
            const bar = document.getElementById('mobileActionsBar');

            if (menu && bar) {
                menu.classList.add('hidden');
                bar.classList.remove('hidden');
            }
        }

        window.switchTab = function(tabId) {
            // Сохраняем выбранный проект в localStorage
            try {
                localStorage.setItem('selectedProjectTab', tabId);
                console.log('Сохранена вкладка в localStorage:', tabId);
            } catch (e) {
                console.warn('Не удалось сохранить выбранный проект в localStorage:', e);
            }

            // Обновляем data-атрибут на html для синхронизации с CSS
            document.documentElement.setAttribute('data-selected-tab', tabId);

            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(function(content) {
                content.style.display = 'none';
            });

            // Remove active state from all tabs (десктоп и мобильные)
            document.querySelectorAll('.tab-button').forEach(function(button) {
                button.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
                button.classList.add('text-slate-700', 'hover:bg-slate-100', 'hover:text-slate-900');
            });

            // Show selected tab content
            const content = document.getElementById('tab-content-' + tabId);
            if (content) {
                content.style.display = 'block';
            }

            // Activate selected tab button (десктоп)
            const button = document.getElementById('tab-' + tabId);
            if (button) {
                button.classList.remove('text-slate-700', 'hover:bg-slate-100', 'hover:text-slate-900');
                button.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
            }

            // Activate selected tab button (мобильный)
            const mobileButton = document.getElementById('tab-' + tabId + '-mobile');
            if (mobileButton) {
                mobileButton.classList.remove('text-slate-700', 'hover:bg-slate-100', 'hover:text-slate-900');
                mobileButton.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
            }

            // Reinitialize drag and drop for the active tab
            setTimeout(function() {
                initDragAndDrop();
            }, 100);
        };

        // Восстанавливаем выбранный проект из localStorage при загрузке страницы
        // Примечание: data-атрибут уже установлен в <head>, CSS обработает отображение
        document.addEventListener('DOMContentLoaded', function() {
            try {
                const savedTabId = localStorage.getItem('selectedProjectTab');
                console.log('Восстановление вкладки из localStorage:', savedTabId);

                if (savedTabId && savedTabId !== 'undefined' && savedTabId !== 'null') {
                    // Проверяем, существует ли вкладка с таким ID
                    const tabButton = document.getElementById('tab-' + savedTabId);
                    const tabContent = document.getElementById('tab-content-' + savedTabId);

                    if (tabButton && tabContent) {
                        // Просто применяем визуальные стили, так как CSS уже показал контент
                        tabButton.classList.remove('text-slate-700', 'hover:bg-slate-100', 'hover:text-slate-900');
                        tabButton.classList.add('bg-blue-600', 'text-white', 'shadow-sm');

                        // Активируем мобильную кнопку тоже
                        const mobileButton = document.getElementById('tab-' + savedTabId + '-mobile');
                        if (mobileButton) {
                            mobileButton.classList.remove('text-slate-700', 'hover:bg-slate-100', 'hover:text-slate-900');
                            mobileButton.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
                        }

                        // Убираем активное состояние с других кнопок
                        document.querySelectorAll('.tab-button').forEach(function(button) {
                            if (button.id !== 'tab-' + savedTabId && button.id !== 'tab-' + savedTabId + '-mobile') {
                                button.classList.remove('bg-blue-600', 'text-white', 'shadow-sm');
                                button.classList.add('text-slate-700', 'hover:bg-slate-100', 'hover:text-slate-900');
                            }
                        });

                        // Инициализируем drag and drop
                        setTimeout(function() {
                            initDragAndDrop();
                        }, 100);
                        return;
                    }
                }
            } catch (e) {
                console.warn('Не удалось восстановить выбранный проект из localStorage:', e);
            }

            // Если не удалось восстановить из localStorage, переключаемся на 'all'
            if (document.getElementById('tab-all')) {
                const allButton = document.getElementById('tab-all');
                allButton.classList.remove('text-slate-700', 'hover:bg-slate-100', 'hover:text-slate-900');
                allButton.classList.add('bg-blue-600', 'text-white', 'shadow-sm');

                // Активируем мобильную кнопку тоже
                const mobileAllButton = document.getElementById('tab-all-mobile');
                if (mobileAllButton) {
                    mobileAllButton.classList.remove('text-slate-700', 'hover:bg-slate-100', 'hover:text-slate-900');
                    mobileAllButton.classList.add('bg-blue-600', 'text-white', 'shadow-sm');
                }

                setTimeout(function() {
                    initDragAndDrop();
                }, 100);
            } else {
                initDragAndDrop();
            }
        });

        window.openTaskModal = function(projectId = null) {
            document.getElementById('taskModal').classList.remove('hidden');
            lockBodyScroll();
            document.getElementById('taskForm').action = '{{ route("tasks.store") }}';
            document.getElementById('taskMethod').value = 'POST';
            document.getElementById('taskModalTitle').textContent = 'Новая задача';

            // Сбрасываем форму
            document.getElementById('taskForm').reset();

            // Устанавливаем projectId после reset, чтобы он не сбросился
            if (projectId) {
                const projectSelect = document.getElementById('taskProjectId');
                if (projectSelect) {
                    // Преобразуем projectId в строку для сравнения
                    const projectIdStr = String(projectId);
                    // Проверяем, существует ли опция с таким значением
                    const optionExists = Array.from(projectSelect.options).some(option => String(option.value) === projectIdStr);
                    if (optionExists) {
                        projectSelect.value = projectIdStr;
                    } else {
                        console.warn('Project with ID', projectId, 'not found in select options');
                    }
                }
            }

            // Сброс тегов
            document.querySelectorAll('input[name="tag_ids[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
        };

        window.openEditTaskModal = function(id, title, description, priorityId, dueDate, projectId, dueTime = '', tagIds = []) {
            document.getElementById('taskModal').classList.remove('hidden');
            lockBodyScroll();
            document.getElementById('taskForm').action = `/tasks/${id}`;
            document.getElementById('taskMethod').value = 'PUT';
            document.getElementById('taskModalTitle').textContent = 'Редактировать задачу';
            document.getElementById('taskId').value = id;
            document.getElementById('taskTitle').value = title;
            document.getElementById('taskDescription').value = description || '';
            document.getElementById('taskPriority').value = priorityId || '';
            document.getElementById('taskDueDate').value = dueDate || '';
            document.getElementById('taskDueTime').value = dueTime || '';
            document.getElementById('taskProjectId').value = projectId || '';
            // Установка тегов
            const tagIdsArray = Array.isArray(tagIds) ? tagIds : (tagIds ? [tagIds] : []);
            document.querySelectorAll('input[name="tag_ids[]"]').forEach(checkbox => {
                checkbox.checked = tagIdsArray.includes(parseInt(checkbox.value));
            });
        };


        window.closeTaskModal = function() {
            document.getElementById('taskModal').classList.add('hidden');
            unlockBodyScroll();
        };

        // Task Details Modal
        let currentTaskDetails = null;

        window.openTaskDetailsModal = function(id, title, description, priorityId, dueDate, projectId, dueTime, completed, priorityName, priorityColor, projectName, projectColor) {
            currentTaskDetails = { id, title, description, priorityId, dueDate, projectId, dueTime, completed };

            document.getElementById('taskDetailsModal').classList.remove('hidden');
            lockBodyScroll();

            // Заполняем данные
            document.getElementById('taskDetailsTitleText').textContent = title || 'Без названия';

            // Описание
            const descriptionSection = document.getElementById('taskDetailsDescriptionSection');
            const descriptionEl = document.getElementById('taskDetailsDescription');
            if (description && description.trim() !== '') {
                descriptionEl.textContent = description;
                descriptionSection.style.display = 'block';
            } else {
                descriptionSection.style.display = 'none';
            }

            // Статус
            const statusEl = document.getElementById('taskDetailsStatus');
            if (completed) {
                statusEl.className = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium bg-green-100 text-green-700';
                statusEl.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Выполнено
                `;
            } else {
                statusEl.className = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium bg-amber-100 text-amber-700';
                statusEl.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    В работе
                `;
            }

            // Приоритет
            const prioritySection = document.getElementById('taskDetailsPrioritySection');
            const priorityEl = document.getElementById('taskDetailsPriority');
            if (priorityName && priorityName !== '' && priorityColor && priorityColor !== '') {
                priorityEl.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white" style="background-color: ${priorityColor}">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        ${priorityName}
                    </span>
                `;
                prioritySection.style.display = 'block';
            } else {
                prioritySection.style.display = 'none';
            }

            // Проект
            const projectSection = document.getElementById('taskDetailsProjectSection');
            const projectEl = document.getElementById('taskDetailsProject');
            if (projectName && projectName !== '' && projectColor && projectColor !== '') {
                projectEl.innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white" style="background-color: ${projectColor}">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                        </svg>
                        ${projectName}
                    </span>
                `;
                projectSection.style.display = 'block';
            } else {
                projectSection.style.display = 'none';
            }

            // Срок выполнения
            const dueDateSection = document.getElementById('taskDetailsDueDateSection');
            const dueDateEl = document.getElementById('taskDetailsDueDate');
            if (dueDate && dueDate !== '') {
                const date = new Date(dueDate);
                const formattedDate = date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric' });
                let dueText = formattedDate;
                if (dueTime && dueTime !== '') {
                    dueText += ` в ${dueTime}`;
                }
                dueDateEl.textContent = dueText;
                dueDateSection.style.display = 'block';
            } else {
                dueDateSection.style.display = 'none';
            }
        };

        window.closeTaskDetailsModal = function() {
            document.getElementById('taskDetailsModal').classList.add('hidden');
            unlockBodyScroll();
            currentTaskDetails = null;
        };

        window.editTaskFromDetails = function() {
            if (currentTaskDetails) {
                window.closeTaskDetailsModal();
                // Получаем теги задачи из DOM
                const taskElement = document.querySelector(`[data-task-id="${currentTaskDetails.id}"]`);
                let tagIds = [];
                if (taskElement) {
                    const tagElements = taskElement.querySelectorAll('[data-tag-id]');
                    tagIds = Array.from(tagElements).map(el => parseInt(el.getAttribute('data-tag-id')));
                }
                window.openEditTaskModal(
                    currentTaskDetails.id,
                    currentTaskDetails.title,
                    currentTaskDetails.description,
                    currentTaskDetails.priorityId,
                    currentTaskDetails.dueDate,
                    currentTaskDetails.projectId,
                    currentTaskDetails.dueTime || '',
                    tagIds
                );
            }
        };

        // Color picker sync
        document.getElementById('projectColor').addEventListener('input', function(e) {
            document.getElementById('projectColorText').value = e.target.value;
        });

        document.getElementById('projectColorText').addEventListener('input', function(e) {
            if (/^#[0-9A-F]{6}$/i.test(e.target.value)) {
                document.getElementById('projectColor').value = e.target.value;
            }
        });

        // Закрытие модальных окон по клику вне их и по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (!document.getElementById('projectModal').classList.contains('hidden')) {
                    window.closeProjectModal();
                } else if (!document.getElementById('taskModal').classList.contains('hidden')) {
                    window.closeTaskModal();
                } else if (!document.getElementById('priorityModal').classList.contains('hidden')) {
                    window.closePriorityModal();
                } else if (!document.getElementById('priorityEditModal').classList.contains('hidden')) {
                    window.closeEditPriorityModal();
                } else if (!document.getElementById('tagModal').classList.contains('hidden')) {
                    window.closeTagModal();
                } else if (!document.getElementById('tagEditModal').classList.contains('hidden')) {
                    window.closeEditTagModal();
                } else if (!document.getElementById('taskDetailsModal').classList.contains('hidden')) {
                    window.closeTaskDetailsModal();
                }
            }
        });

        // Обновление времени до выполнения в реальном времени
        function updateHoursLeft() {
            document.querySelectorAll('[data-due-date]').forEach(function(element) {
                const dueDateStr = element.getAttribute('data-due-date');
                const dueTimeStr = element.getAttribute('data-due-time');
                const isToday = element.getAttribute('data-is-today') === '1';

                if (!dueDateStr) return;

                const now = new Date();
                let dueDate;

                // Если срок сегодня и указано время, используем точное время
                if (isToday && dueTimeStr) {
                    const [hours, minutes] = dueTimeStr.split(':');
                    dueDate = new Date(dueDateStr);
                    dueDate.setHours(parseInt(hours), parseInt(minutes), 0, 0);
                } else {
                    dueDate = new Date(dueDateStr + 'T23:59:59');
                }

                const diffMs = dueDate - now;

                if (diffMs <= 0) {
                    // Просрочено
                    const timeLeftEl = element.querySelector('.time-left');
                    if (timeLeftEl) {
                        timeLeftEl.innerHTML = '<span class="days-left">0</span> дней';
                    }
                    element.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-200';
                    return;
                }

                const daysLeft = Math.floor(diffMs / (1000 * 60 * 60 * 24));
                const hoursLeft = Math.floor(diffMs / (1000 * 60 * 60));
                const minutesLeft = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

                // Определяем цвет
                let colorClass = 'bg-green-100 text-green-700 border border-green-200';
                if (daysLeft < 1) {
                    colorClass = 'bg-red-100 text-red-700 border border-red-200';
                } else if (daysLeft < 3) {
                    colorClass = 'bg-amber-100 text-amber-700 border border-amber-200';
                }
                element.className = 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold ' + colorClass;

                const timeLeftEl = element.querySelector('.time-left');
                if (!timeLeftEl) return;

                // Показываем дни, часы или минуты в зависимости от оставшегося времени
                if (daysLeft >= 1) {
                    // Показываем дни
                    const daysEl = timeLeftEl.querySelector('.days-left');
                    if (daysEl) {
                        daysEl.textContent = daysLeft;
                        const suffix = daysLeft === 1 ? ' день' : (daysLeft >= 2 && daysLeft <= 4 ? ' дня' : ' дней');
                        timeLeftEl.innerHTML = '<span class="days-left">' + daysLeft + '</span>' + suffix;
                    }
                } else if (hoursLeft >= 1) {
                    // Показываем часы
                    const hoursEl = timeLeftEl.querySelector('.hours-left');
                    if (hoursEl) {
                        hoursEl.textContent = hoursLeft;
                        const suffix = hoursLeft === 1 ? ' час' : (hoursLeft >= 2 && hoursLeft <= 4 ? ' часа' : ' часов');
                        timeLeftEl.innerHTML = '<span class="hours-left">' + hoursLeft + '</span>' + suffix;
                    }
                } else {
                    // Показываем минуты
                    const minutesEl = timeLeftEl.querySelector('.minutes-left');
                    if (minutesEl) {
                        minutesEl.textContent = minutesLeft;
                        const suffix = minutesLeft === 1 ? ' минута' : (minutesLeft >= 2 && minutesLeft <= 4 ? ' минуты' : ' минут');
                        timeLeftEl.innerHTML = '<span class="minutes-left">' + minutesLeft + '</span>' + suffix;
                    }
                }
            });
        }

        // Обновляем каждую минуту
        updateHoursLeft();
        setInterval(updateHoursLeft, 60000); // Каждую минуту

        // Drag and Drop для задач с улучшенной визуализацией и живым перемещением
        let draggedElement = null;
        let draggedElementWrapper = null;
        let lastTargetWrapper = null;
        let lastInsertPosition = null;

        function initDragAndDrop() {
            // Find all visible task containers (only in active tab)
            const visibleContainers = document.querySelectorAll('.tasks-container:not(.hidden)');
            if (visibleContainers.length === 0) {
                console.warn('initDragAndDrop: No visible containers found');
                return;
            }
            console.log('initDragAndDrop: Initializing drag and drop for', visibleContainers.length, 'container(s)');

            visibleContainers.forEach(function(container) {
                const taskItems = container.querySelectorAll('.task-item');
                console.log('initDragAndDrop: Found', taskItems.length, 'task items in container');

                taskItems.forEach(function(item) {
                    const wrapper = item.closest('.task-item-wrapper');
                    if (!wrapper) {
                        console.warn('initDragAndDrop: No wrapper found for task item');
                        return;
                    }

                    item.addEventListener('dragstart', function(e) {
                        draggedElement = this;
                        draggedElementWrapper = wrapper;
                        wrapper.classList.add('dragging');
                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/html', '');
                        console.log('Drag started for task:', this.getAttribute('data-task-id'));
                    });

                    item.addEventListener('dragend', function(e) {
                    // Убираем все классы у всех задач с плавной анимацией
                    document.querySelectorAll('.task-item-wrapper').forEach(function(w) {
                        const taskItem = w.querySelector('.task-item');
                        if (taskItem) {
                            taskItem.classList.remove('drag-target');
                        }
                        w.style.transition = 'transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.4s ease-out, box-shadow 0.4s ease-out';
                        w.classList.remove('dragging', 'drag-over-top', 'drag-over-bottom');

                        // Убираем transition после завершения анимации
                        setTimeout(function() {
                            w.style.transition = '';
                        }, 400);
                    });

                        draggedElement = null;
                        draggedElementWrapper = null;
                        lastTargetWrapper = null;
                        lastInsertPosition = null;
                    });

                    // Добавляем обработчик dragover на wrapper
                    wrapper.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        e.dataTransfer.dropEffect = 'move';

                        console.log('🟡 WRAPPER dragover triggered', {
                            hasDraggedElement: !!draggedElement,
                            draggedId: draggedElement ? draggedElement.getAttribute('data-task-id') : null,
                            targetId: item.getAttribute('data-task-id'),
                            isSame: draggedElement === item
                        });

                        if (!draggedElement || draggedElement === item) {
                            console.log('⏭️ WRAPPER dragover: Skipping - no draggedElement or same item');
                            return false;
                        }

                        // Find the container that contains this wrapper
                        const container = this.closest('.tasks-container');
                        if (!container) {
                            return false;
                        }

                        const draggedWrapper = draggedElement.closest('.task-item-wrapper');
                        const targetWrapper = this;

                        if (!draggedWrapper || draggedWrapper === targetWrapper) {
                            return false;
                        }

                        // Очищаем все предыдущие подсветки перед добавлением новой
                        document.querySelectorAll('.task-item-wrapper').forEach(function(w) {
                            if (w !== draggedWrapper && w !== targetWrapper) {
                                const ti = w.querySelector('.task-item');
                                if (ti) ti.classList.remove('drag-target');
                                w.classList.remove('drag-over-top', 'drag-over-bottom');
                            }
                        });

                        const rect = this.getBoundingClientRect();
                        const mouseY = e.clientY;
                        const middleY = rect.top + rect.height / 2;
                        const taskItem = this.querySelector('.task-item');
                        const insertBefore = mouseY < middleY;

                        // Визуальные эффекты только для текущей целевой задачи
                        if (taskItem) {
                            taskItem.classList.add('drag-target');
                        }
                        if (insertBefore) {
                            targetWrapper.classList.add('drag-over-top');
                            targetWrapper.classList.remove('drag-over-bottom');
                        } else {
                            targetWrapper.classList.add('drag-over-bottom');
                            targetWrapper.classList.remove('drag-over-top');
                        }

                        // Живое перемещение задач в реальном времени
                        if (lastTargetWrapper !== targetWrapper || lastInsertPosition !== insertBefore) {
                            lastTargetWrapper = targetWrapper;
                            lastInsertPosition = insertBefore;

                            // Проверяем базовые условия
                            if (!container.contains(draggedWrapper) || !container.contains(targetWrapper)) {
                                return false;
                            }

                            // Получаем все элементы контейнера для определения позиций
                            // Теперь container.children содержит .task-item-wrapper напрямую
                            const allWrappers = Array.from(container.children).filter(function(child) {
                                return child.classList.contains('task-item-wrapper');
                            });
                            const draggedIndex = allWrappers.indexOf(draggedWrapper);
                            const targetIndex = allWrappers.indexOf(targetWrapper);

                            console.log('🔵 dragover: Checking move', {
                                draggedIndex,
                                targetIndex,
                                draggedId: draggedElement.getAttribute('data-task-id'),
                                targetId: item.getAttribute('data-task-id'),
                                insertBefore,
                                containerChildren: allWrappers.length,
                                draggedWrapperInList: draggedIndex !== -1,
                                targetWrapperInList: targetIndex !== -1
                            });

                            if (draggedIndex === -1 || targetIndex === -1) {
                                console.warn('⚠️ dragover: Invalid indices', {
                                    draggedIndex,
                                    targetIndex,
                                    containerChildren: Array.from(container.children).map(c => c.className),
                                    draggedWrapperClass: draggedWrapper.className,
                                    targetWrapperClass: targetWrapper.className
                                });
                                return false;
                            }

                            // Определяем желаемую позицию
                            const desiredIndex = insertBefore ? targetIndex : targetIndex + 1;

                            console.log('🔵 dragover: Desired position', {
                                desiredIndex,
                                needsMove: draggedIndex !== desiredIndex && (draggedIndex + 1) !== desiredIndex
                            });

                            // Перемещаем только если позиция действительно изменилась
                            if (draggedIndex !== desiredIndex && (draggedIndex + 1) !== desiredIndex) {
                                try {
                                    console.log('🟢 dragover: MOVING element from', draggedIndex, 'to', desiredIndex);

                                    // Получаем текущий порядок ДО перемещения
                                    const orderBefore = Array.from(container.children)
                                        .filter(w => w.classList.contains('task-item-wrapper'))
                                        .map(w => {
                                            const ti = w.querySelector('.task-item');
                                            return ti ? ti.getAttribute('data-task-id') : null;
                                        }).filter(id => id !== null);
                                    console.log('📋 Order BEFORE move:', orderBefore);

                                    // Удаляем элемент из текущей позиции
                                    draggedWrapper.remove();

                                    // Получаем обновленный список элементов (без draggedWrapper)
                                    const updatedWrappers = Array.from(container.children).filter(function(child) {
                                        return child.classList.contains('task-item-wrapper');
                                    });

                                    // Вставляем в новую позицию
                                    if (desiredIndex >= updatedWrappers.length) {
                                        container.appendChild(draggedWrapper);
                                        console.log('✅ Appended to end');
                                    } else {
                                        // Находим элемент, который теперь находится в желаемой позиции
                                        const referenceIndex = desiredIndex > draggedIndex ? desiredIndex - 1 : desiredIndex;
                                        const referenceElement = updatedWrappers[referenceIndex];

                                        console.log('🔍 Looking for reference element', {
                                            referenceIndex,
                                            referenceElement: referenceElement ? 'found' : 'not found',
                                            updatedWrappersLength: updatedWrappers.length
                                        });

                                        if (referenceElement && referenceElement.parentNode === container) {
                                            container.insertBefore(draggedWrapper, referenceElement);
                                            console.log('✅ Inserted before reference element');
                                        } else {
                                            container.appendChild(draggedWrapper);
                                            console.log('✅ Appended to end (fallback)');
                                        }
                                    }

                                    // Проверяем новый порядок ПОСЛЕ перемещения
                                    const orderAfter = Array.from(container.children)
                                        .filter(w => w.classList.contains('task-item-wrapper'))
                                        .map(w => {
                                            const ti = w.querySelector('.task-item');
                                            return ti ? ti.getAttribute('data-task-id') : null;
                                        }).filter(id => id !== null);
                                    console.log('📋 Order AFTER move:', orderAfter);
                                    console.log('🔄 Order changed:', JSON.stringify(orderBefore) !== JSON.stringify(orderAfter));
                                } catch (error) {
                                    console.error('❌ Error moving element in dragover:', error);
                                }
                            } else {
                                console.log('⏭️ dragover: No move needed - already in position');
                            }
                        }

                        return false;
                    });

                    // Также добавляем dragover на сам item для надежности
                    item.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        if (e.dataTransfer) {
                            e.dataTransfer.dropEffect = 'move';
                        }

                        console.log('🟢 ITEM dragover triggered', {
                            hasDraggedElement: !!draggedElement,
                            draggedId: draggedElement ? draggedElement.getAttribute('data-task-id') : null,
                            targetId: this.getAttribute('data-task-id'),
                            isSame: draggedElement === this
                        });

                        if (!draggedElement || draggedElement === this) {
                            console.log('⏭️ ITEM dragover: Skipping - no draggedElement or same item');
                            return false;
                        }

                        console.log('✅ ITEM dragover: Passed first check, continuing...');

                        const wrapper = this.closest('.task-item-wrapper');
                        if (!wrapper) {
                            console.warn('⚠️ ITEM dragover: No wrapper found');
                            return false;
                        }
                        console.log('✅ ITEM dragover: Wrapper found');

                        // Находим контейнер
                        const container = wrapper.closest('.tasks-container');
                        if (!container) {
                            console.warn('⚠️ ITEM dragover: No container found');
                            return false;
                        }
                        console.log('✅ ITEM dragover: Container found');

                        const draggedWrapper = draggedElement.closest('.task-item-wrapper');
                        if (!draggedWrapper) {
                            console.warn('⚠️ ITEM dragover: No draggedWrapper found');
                            return false;
                        }
                        if (draggedWrapper === wrapper) {
                            console.log('⏭️ ITEM dragover: Same wrapper, skipping');
                            return false;
                        }
                        console.log('✅ ITEM dragover: All checks passed, proceeding to move logic');

                        // Выполняем ту же логику, что и в обработчике wrapper
                        const rect = wrapper.getBoundingClientRect();
                        const mouseY = e.clientY;
                        const middleY = rect.top + rect.height / 2;
                        const taskItem = wrapper.querySelector('.task-item');
                        const insertBefore = mouseY < middleY;

                        // Визуальные эффекты
                        taskItem.classList.add('drag-target');
                        if (insertBefore) {
                            wrapper.classList.add('drag-over-top');
                            wrapper.classList.remove('drag-over-bottom');
                        } else {
                            wrapper.classList.add('drag-over-bottom');
                            wrapper.classList.remove('drag-over-top');
                        }

                        console.log('🔵 ITEM dragover: Before move check', {
                            lastTargetWrapper: lastTargetWrapper ? 'set' : 'null',
                            currentWrapper: wrapper,
                            lastInsertPosition,
                            insertBefore,
                            willCheck: lastTargetWrapper !== wrapper || lastInsertPosition !== insertBefore
                        });

                        // Живое перемещение задач в реальном времени
                        if (lastTargetWrapper !== wrapper || lastInsertPosition !== insertBefore) {
                            console.log('✅ ITEM dragover: Move check passed, updating lastTargetWrapper');
                            lastTargetWrapper = wrapper;
                            lastInsertPosition = insertBefore;

                            // Проверяем базовые условия
                            if (!draggedWrapper || !wrapper || draggedWrapper === wrapper) {
                                console.warn('⚠️ ITEM dragover: Invalid elements in move check');
                                return false;
                            }

                            // Проверяем, что элементы в контейнере
                            if (!container.contains(draggedWrapper) || !container.contains(wrapper)) {
                                console.warn('⚠️ ITEM dragover: Elements not in container');
                                return false;
                            }

                            // Получаем все элементы контейнера для определения позиций
                            // Теперь container.children содержит .task-item-wrapper напрямую
                            const allWrappers = Array.from(container.children).filter(function(child) {
                                return child.classList.contains('task-item-wrapper');
                            });
                            const draggedIndex = allWrappers.indexOf(draggedWrapper);
                            const targetIndex = allWrappers.indexOf(wrapper);

                            console.log('🔵 ITEM dragover: Indices found', {
                                draggedIndex,
                                targetIndex,
                                allWrappersCount: allWrappers.length,
                                draggedWrapperInList: draggedIndex !== -1,
                                targetWrapperInList: targetIndex !== -1
                            });

                            if (draggedIndex === -1 || targetIndex === -1) {
                                console.warn('⚠️ ITEM dragover: Invalid indices', {
                                    draggedIndex,
                                    targetIndex,
                                    containerChildren: Array.from(container.children).map(c => c.className),
                                    draggedWrapperClass: draggedWrapper.className,
                                    targetWrapperClass: wrapper.className
                                });
                                return false;
                            }

                            // Определяем желаемую позицию
                            const desiredIndex = insertBefore ? targetIndex : targetIndex + 1;

                            console.log('🔵 ITEM dragover: Checking move', {
                                draggedIndex,
                                targetIndex,
                                desiredIndex,
                                draggedId: draggedElement.getAttribute('data-task-id'),
                                targetId: this.getAttribute('data-task-id'),
                                insertBefore,
                                needsMove: draggedIndex !== desiredIndex && (draggedIndex + 1) !== desiredIndex
                            });

                            // Перемещаем только если позиция действительно изменилась
                            if (draggedIndex !== desiredIndex && (draggedIndex + 1) !== desiredIndex) {
                                try {
                                    console.log('🟢 ITEM dragover: MOVING element from', draggedIndex, 'to', desiredIndex);

                                    // Получаем текущий порядок ДО перемещения
                                    const orderBefore = Array.from(container.children)
                                        .filter(w => w.classList.contains('task-item-wrapper'))
                                        .map(w => {
                                            const ti = w.querySelector('.task-item');
                                            return ti ? ti.getAttribute('data-task-id') : null;
                                        }).filter(id => id !== null);
                                    console.log('📋 ITEM Order BEFORE move:', orderBefore);

                                    // Удаляем элемент из текущей позиции
                                    draggedWrapper.remove();

                                    // Получаем обновленный список элементов (без draggedWrapper)
                                    const updatedWrappers = Array.from(container.children).filter(function(child) {
                                        return child.classList.contains('task-item-wrapper');
                                    });

                                    // Вставляем в новую позицию
                                    if (desiredIndex >= updatedWrappers.length) {
                                        container.appendChild(draggedWrapper);
                                        console.log('✅ ITEM Appended to end');
                                    } else {
                                        // Находим элемент, который теперь находится в желаемой позиции
                                        const referenceIndex = desiredIndex > draggedIndex ? desiredIndex - 1 : desiredIndex;
                                        const referenceElement = updatedWrappers[referenceIndex];

                                        console.log('🔍 ITEM Looking for reference element', {
                                            referenceIndex,
                                            referenceElement: referenceElement ? 'found' : 'not found',
                                            updatedWrappersLength: updatedWrappers.length
                                        });

                                        if (referenceElement && referenceElement.parentNode === container) {
                                            container.insertBefore(draggedWrapper, referenceElement);
                                            console.log('✅ ITEM Inserted before reference element');
                                        } else {
                                            container.appendChild(draggedWrapper);
                                            console.log('✅ ITEM Appended to end (fallback)');
                                        }
                                        }

                                        // Проверяем новый порядок ПОСЛЕ перемещения
                                        const orderAfter = Array.from(container.children)
                                            .filter(w => w.classList.contains('task-item-wrapper'))
                                            .map(w => {
                                                const ti = w.querySelector('.task-item');
                                                return ti ? ti.getAttribute('data-task-id') : null;
                                            }).filter(id => id !== null);
                                        console.log('📋 ITEM Order AFTER move:', orderAfter);
                                        console.log('🔄 ITEM Order changed:', JSON.stringify(orderBefore) !== JSON.stringify(orderAfter));
                                } catch (error) {
                                    console.error('❌ Error moving element in item dragover:', error);
                                }
                            } else {
                                console.log('⏭️ ITEM dragover: No move needed - already in position');
                            }
                        }

                        return false;
                    });

                    wrapper.addEventListener('dragenter', function(e) {
                        e.preventDefault();
                        if (!draggedElement || draggedElement === item) return;
                    });

                    wrapper.addEventListener('dragleave', function(e) {
                    // Проверяем, что мы действительно покинули элемент
                    const rect = this.getBoundingClientRect();
                    const tolerance = 5;
                    const x = e.clientX || (e.relatedTarget ? e.relatedTarget.getBoundingClientRect().left : 0);
                    const y = e.clientY || (e.relatedTarget ? e.relatedTarget.getBoundingClientRect().top : 0);

                        // Если курсор вне границ элемента, убираем подсветку
                        if (x < rect.left - tolerance || x > rect.right + tolerance ||
                            y < rect.top - tolerance || y > rect.bottom + tolerance) {
                            this.querySelector('.task-item').classList.remove('drag-target');
                            this.classList.remove('drag-over-top', 'drag-over-bottom');
                        }
                    });

                    wrapper.addEventListener('drop', function(e) {
                        e.preventDefault();
                        e.stopPropagation();

                        console.log('drop: Event triggered');

                        if (draggedElement && draggedElement !== item) {
                            // Find the container that contains this wrapper
                            const container = this.closest('.tasks-container');
                            if (!container) {
                                console.warn('drop: No container found');
                                return false;
                            }

                            const draggedWrapper = draggedElement.closest('.task-item-wrapper');

                            // Проверяем базовые условия
                            if (!draggedWrapper) {
                                console.warn('drop: No draggedWrapper found');
                                return false;
                            }

                            // Проверяем, что элементы в контейнере
                            if (!container.contains(draggedWrapper)) {
                                console.warn('drop: DraggedWrapper not in container');
                                return false;
                            }

                            // Элементы уже перемещены в dragover, просто сохраняем порядок
                            console.log('drop: Elements already moved in dragover, saving order');

                            // Убираем визуальные эффекты
                            this.querySelector('.task-item').classList.remove('drag-target');
                            this.classList.remove('drag-over-top', 'drag-over-bottom');

                            // Сохраняем порядок
                            updateTasksOrder();
                        } else {
                            console.warn('drop: No draggedElement or same item');
                        }

                        return false;
                    });
                });
            });

            // Mobile touch support with long press
            let touchStartElement = null;
            let touchStartWrapper = null;
            let touchStartIndex = null;
            let touchStartY = null;
            let touchStartX = null;
            let longPressTimer = null;
            let isLongPress = false;
            let hasMoved = false;
            const LONG_PRESS_DURATION = 500; // 500ms для долгого нажатия

            // Find all visible task containers for touch events
            const visibleContainersForTouch = document.querySelectorAll('.tasks-container:not(.hidden)');
            visibleContainersForTouch.forEach(function(container) {
                container.querySelectorAll('.task-item').forEach(function(item) {
                    const wrapper = item.closest('.task-item-wrapper');

                    item.addEventListener('touchstart', function(e) {
                        // Сбрасываем флаги
                        isLongPress = false;
                        hasMoved = false;

                        // Сохраняем начальные данные
                        touchStartElement = item;
                        touchStartWrapper = wrapper;
                        touchStartY = e.touches[0].clientY;
                        touchStartX = e.touches[0].clientX;
                        const allWrappersForTouch = Array.from(container.children).filter(function(child) {
                            return child.classList.contains('task-item-wrapper');
                        });
                        touchStartIndex = allWrappersForTouch.indexOf(wrapper);

                        // Запускаем таймер для долгого нажатия
                        longPressTimer = setTimeout(function() {
                            if (!hasMoved && touchStartElement === item) {
                                isLongPress = true;

                                // Добавляем класс для визуального эффекта
                                wrapper.classList.add('touch-dragging', 'dragging');
                                wrapper.style.transition = 'none';

                                // Убираем transition у всех других элементов для плавной анимации
                                container.querySelectorAll('.task-item-wrapper').forEach(function(w) {
                                    if (w !== wrapper) {
                                        w.style.transition = 'transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.4s ease-out, box-shadow 0.4s ease-out';
                                    }
                                });

                                // Визуальная обратная связь - вибрация (если поддерживается)
                                if (navigator.vibrate) {
                                    navigator.vibrate(50);
                                }
                            }
                        }, LONG_PRESS_DURATION);
                    }, { passive: true });

                    item.addEventListener('touchmove', function(e) {
                        if (!touchStartElement || touchStartElement !== item) {
                            // Отменяем таймер, если элемент изменился
                            if (longPressTimer) {
                                clearTimeout(longPressTimer);
                                longPressTimer = null;
                            }
                            return;
                        }

                        const touchY = e.touches[0].clientY;
                        const touchX = e.touches[0].clientX;
                        const deltaY = Math.abs(touchY - touchStartY);
                        const deltaX = Math.abs(touchX - touchStartX);

                        // Проверяем, началось ли движение
                        if (deltaY > 10 || deltaX > 10) {
                            hasMoved = true;

                            // Если это не долгое нажатие, отменяем таймер и позволяем обычный скролл
                            if (!isLongPress) {
                                if (longPressTimer) {
                                    clearTimeout(longPressTimer);
                                    longPressTimer = null;
                                }
                                // Сбрасываем состояние
                                touchStartElement = null;
                                touchStartWrapper = null;
                                return;
                            }
                        }

                        // Продолжаем только если было долгое нажатие
                        if (!isLongPress) return;

                        e.preventDefault();
                        const deltaYMove = touchY - touchStartY;

                        // Обновляем позицию перетаскиваемого элемента
                        wrapper.style.transform = `translateY(${deltaYMove}px)`;
                        wrapper.style.zIndex = '50';

                        const elementBelow = document.elementFromPoint(e.touches[0].clientX, touchY);
                        const targetWrapper = elementBelow?.closest('.task-item-wrapper');

                        // Очищаем предыдущие подсветки
                        container.querySelectorAll('.task-item-wrapper').forEach(function(w) {
                            if (w !== wrapper && w !== targetWrapper) {
                                const taskItem = w.querySelector('.task-item');
                                if (taskItem) {
                                    taskItem.classList.remove('drag-target');
                                }
                                w.classList.remove('drag-over-top', 'drag-over-bottom');
                            }
                        });

                        if (targetWrapper && targetWrapper !== wrapper) {
                            const targetRect = targetWrapper.getBoundingClientRect();
                            const targetMiddleY = targetRect.top + targetRect.height / 2;
                            const insertBefore = touchY < targetMiddleY;

                            const targetTaskItem = targetWrapper.querySelector('.task-item');
                            if (targetTaskItem) {
                                targetTaskItem.classList.add('drag-target');
                            }

                            // Проверяем базовые условия
                            if (!wrapper || !targetWrapper || wrapper === targetWrapper) {
                                return;
                            }

                            // Проверяем, что элементы в контейнере
                            if (!container.contains(wrapper) || !container.contains(targetWrapper)) {
                                return;
                            }

                            try {
                                // Визуальные эффекты для целевого элемента
                                if (insertBefore) {
                                    targetWrapper.classList.add('drag-over-top');
                                    targetWrapper.classList.remove('drag-over-bottom');
                                } else {
                                    targetWrapper.classList.add('drag-over-bottom');
                                    targetWrapper.classList.remove('drag-over-top');
                                }

                                // Перемещаем элемент в DOM для анимации других элементов
                                const allWrappers = Array.from(container.children).filter(function(child) {
                                    return child.classList.contains('task-item-wrapper');
                                });
                                const draggedIndex = allWrappers.indexOf(wrapper);
                                const targetIndex = allWrappers.indexOf(targetWrapper);

                                if (draggedIndex !== -1 && targetIndex !== -1) {
                                    const desiredIndex = insertBefore ? targetIndex : targetIndex + 1;

                                    // Перемещаем только если позиция изменилась
                                    if (draggedIndex !== desiredIndex && (draggedIndex + 1) !== desiredIndex) {
                                        // Сохраняем текущий transform
                                        const currentTransform = wrapper.style.transform;

                                        // Временно убираем transform для правильного расчета позиции
                                        wrapper.style.transform = '';

                                        // Используем requestAnimationFrame для плавного перемещения
                                        requestAnimationFrame(function() {
                                            // Перемещаем элемент
                                            wrapper.remove();
                                            const updatedWrappers = Array.from(container.children).filter(function(child) {
                                                return child.classList.contains('task-item-wrapper');
                                            });

                                            if (desiredIndex >= updatedWrappers.length) {
                                                container.appendChild(wrapper);
                                            } else {
                                                const referenceIndex = desiredIndex > draggedIndex ? desiredIndex - 1 : desiredIndex;
                                                const referenceElement = updatedWrappers[referenceIndex];
                                                if (referenceElement && referenceElement.parentNode === container) {
                                                    container.insertBefore(wrapper, referenceElement);
                                                } else {
                                                    container.appendChild(wrapper);
                                                }
                                            }

                                            // Восстанавливаем transform в следующем кадре для плавности
                                            requestAnimationFrame(function() {
                                                wrapper.style.transform = currentTransform;
                                            });
                                        });
                                    }
                                }
                            } catch (error) {
                                console.error('Error moving element on touch:', error);
                            }
                        }
                    }, { passive: false });

                    item.addEventListener('touchend', function(e) {
                        // Отменяем таймер долгого нажатия
                        if (longPressTimer) {
                            clearTimeout(longPressTimer);
                            longPressTimer = null;
                        }

                        // Если не было долгого нажатия, просто сбрасываем состояние
                        if (!isLongPress || !touchStartElement || touchStartElement !== item) {
                            touchStartElement = null;
                            touchStartWrapper = null;
                            touchStartY = null;
                            touchStartX = null;
                            touchStartIndex = null;
                            isLongPress = false;
                            hasMoved = false;
                            return;
                        }

                        // Find the container for this wrapper
                        const container = wrapper.closest('.tasks-container');
                        if (container) {
                            // Восстанавливаем transition для плавной анимации
                            // Используем requestAnimationFrame для плавного перехода
                            requestAnimationFrame(function() {
                                wrapper.style.transition = 'transform 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.6s ease-out, box-shadow 0.6s ease-out';
                                wrapper.style.transform = '';
                                wrapper.style.zIndex = '';

                                // Убираем все классы и стили с плавной анимацией
                                container.querySelectorAll('.task-item-wrapper').forEach(function(w) {
                                    const taskItem = w.querySelector('.task-item');
                                    if (taskItem) {
                                        taskItem.classList.remove('drag-target');
                                    }
                                    // Устанавливаем плавный transition для всех элементов
                                    w.style.transition = 'transform 0.5s cubic-bezier(0.25, 0.46, 0.45, 0.94), opacity 0.5s ease-out, box-shadow 0.5s ease-out';
                                    w.classList.remove('drag-over-top', 'drag-over-bottom', 'dragging', 'touch-dragging');

                                    // Небольшая задержка перед удалением transition для плавности
                                    setTimeout(function() {
                                        w.style.transition = '';
                                    }, 500);
                                });
                            });

                            // Проверяем, изменился ли порядок
                            const allWrappersForTouchEnd = Array.from(container.children).filter(function(child) {
                                return child.classList.contains('task-item-wrapper');
                            });
                            const newIndex = allWrappersForTouchEnd.indexOf(wrapper);
                            if (newIndex !== touchStartIndex) {
                                updateTasksOrder();
                            }
                        }

                        touchStartElement = null;
                        touchStartWrapper = null;
                        touchStartY = null;
                        touchStartX = null;
                        touchStartIndex = null;
                        isLongPress = false;
                        hasMoved = false;
                    }, { passive: true });

                    // Обработка отмены касания (например, при выходе за пределы экрана)
                    item.addEventListener('touchcancel', function(e) {
                        if (longPressTimer) {
                            clearTimeout(longPressTimer);
                            longPressTimer = null;
                        }
                        touchStartElement = null;
                        touchStartWrapper = null;
                        touchStartY = null;
                        touchStartX = null;
                        touchStartIndex = null;
                        isLongPress = false;
                        hasMoved = false;

                        // Убираем визуальные эффекты
                        if (wrapper) {
                            wrapper.classList.remove('touch-dragging', 'dragging');
                            wrapper.style.transition = '';
                            wrapper.style.transform = '';
                            wrapper.style.zIndex = '';
                        }
                    }, { passive: true });
                });
            });
        }

        function updateTasksOrder() {
            // Get the active tab's container
            const activeContainer = document.querySelector('.tasks-container:not(.hidden)');
            if (!activeContainer) {
                console.warn('updateTasksOrder: No active container found');
                return;
            }

            const taskWrappers = activeContainer.querySelectorAll('.task-item-wrapper');
            console.log('updateTasksOrder: Found', taskWrappers.length, 'task wrappers');

            // Получаем порядок задач из DOM (в том порядке, в котором они сейчас отображаются)
            const taskIds = Array.from(taskWrappers).map((wrapper, index) => {
                const taskItem = wrapper.querySelector('.task-item');
                const taskId = taskItem ? taskItem.getAttribute('data-task-id') : null;
                console.log(`updateTasksOrder: Position ${index + 1} - Task ID: ${taskId}`);
                return taskId;
            }).filter(id => id !== null);

            console.log('updateTasksOrder: Current order in DOM:', taskIds);
            console.log('updateTasksOrder: Sending to server:', JSON.stringify({task_ids: taskIds}));

            if (taskIds.length === 0) {
                console.warn('updateTasksOrder: No task IDs found, skipping update');
                return;
            }

            fetch('{{ route("tasks.reorder") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    task_ids: taskIds
                })
            })
            .then(response => {
                console.log('updateTasksOrder: Response status', response.status);
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('updateTasksOrder: Response data', data);
                if (data.success) {
                    console.log('✅ Порядок задач обновлен успешно в базе данных');
                    // Порядок уже визуально изменен в DOM, не нужно перезагружать страницу
                } else {
                    console.error('❌ Ошибка при обновлении порядка:', data);
                }
            })
            .catch(error => {
                console.error('❌ Ошибка при обновлении порядка:', error);
            });
        }


        // Priority Management
        window.openPriorityModal = function() {
            document.getElementById('priorityModal').classList.remove('hidden');
            lockBodyScroll();
            loadPriorities();
        };

        window.closePriorityModal = function() {
            document.getElementById('priorityModal').classList.add('hidden');
            unlockBodyScroll();
        };

        window.openEditPriorityModal = function(id, name, color) {
            document.getElementById('priorityEditModal').classList.remove('hidden');
            lockBodyScroll();
            document.getElementById('priorityEditForm').action = `/priorities/${id}`;
            document.getElementById('priorityEditMethod').value = 'PUT';
            document.getElementById('priorityEditId').value = id;
            document.getElementById('priorityEditName').value = name;
            document.getElementById('priorityEditColor').value = color;
            document.getElementById('priorityEditColorText').value = color;
        };

        window.closeEditPriorityModal = function() {
            document.getElementById('priorityEditModal').classList.add('hidden');
            unlockBodyScroll();
        };

        function loadPriorities() {
            fetch('{{ route("priorities.index") }}')
                .then(response => response.json())
                .then(priorities => {
                    const container = document.getElementById('prioritiesList');
                    container.innerHTML = '';

                    if (priorities.length === 0) {
                        container.innerHTML = `
                            <div class="text-center py-8 bg-slate-50 rounded-lg border border-dashed border-slate-300">
                                <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                </svg>
                                <p class="text-slate-600 font-medium text-sm sm:text-base mb-1">Нет приоритетов</p>
                                <p class="text-slate-500 text-xs sm:text-sm">Создайте первый приоритет выше</p>
                            </div>
                        `;
                        return;
                    }

                    priorities.forEach(function(priority) {
                        const div = document.createElement('div');
                        div.className = 'flex items-center justify-between p-3 bg-white rounded-lg border border-slate-200 hover:border-purple-300 hover:shadow-sm transition-all duration-200';
                        div.innerHTML = `
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-full flex-shrink-0 border border-slate-200" style="background-color: ${priority.color}"></div>
                                <div class="flex-1 min-w-0">
                                    <span class="font-medium text-slate-900 text-sm sm:text-base block truncate">${priority.name}</span>
                                    <span class="text-xs text-slate-500 font-mono">${priority.color}</span>
                                </div>
                            </div>
                            <div class="flex gap-1.5 ml-2">
                                <button onclick="window.openEditPriorityModal(${priority.id}, '${priority.name.replace(/'/g, "\\'")}', '${priority.color}')"
                                        class="p-1.5 sm:p-2 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500" title="Редактировать">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button onclick="deletePriority(${priority.id})"
                                        class="p-1.5 sm:p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-md transition-colors focus:outline-none focus:ring-2 focus:ring-red-500" title="Удалить">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        `;
                        container.appendChild(div);
                    });
                })
                .catch(error => {
                    console.error('Error loading priorities:', error);
                });
        }

        function deletePriority(id) {
            if (!confirm('Удалить этот приоритет? Задачи с этим приоритетом останутся без приоритета.')) {
                return;
            }

            fetch(`/priorities/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadPriorities();
                    // Обновляем список приоритетов в модальном окне задачи
                    updateTaskModalLists();
                } else {
                    alert(data.error || 'Ошибка при удалении приоритета');
                }
            })
            .catch(error => {
                console.error('Error deleting priority:', error);
                alert('Ошибка при удалении приоритета');
            });
        }

        // Tag Management Functions
        window.openTagModal = function() {
            document.getElementById('tagModal').classList.remove('hidden');
            lockBodyScroll();
            loadTags();
        };

        window.closeTagModal = function() {
            document.getElementById('tagModal').classList.add('hidden');
            unlockBodyScroll();
            document.getElementById('tagForm').reset();
            document.getElementById('tagColor').value = '#3b82f6';
            document.getElementById('tagColorText').value = '#3b82f6';
        };

        window.openEditTagModal = function(id, name, color) {
            document.getElementById('tagEditId').value = id;
            document.getElementById('tagEditName').value = name;
            document.getElementById('tagEditColor').value = color;
            document.getElementById('tagEditColorText').value = color;
            document.getElementById('tagEditModal').classList.remove('hidden');
            lockBodyScroll();
        };

        window.closeEditTagModal = function() {
            document.getElementById('tagEditModal').classList.add('hidden');
            unlockBodyScroll();
            document.getElementById('tagEditForm').reset();
        };

        function loadTags() {
            fetch('/tags')
                .then(response => response.json())
                .then(tags => {
                    const tagsList = document.getElementById('tagsList');
                    tagsList.innerHTML = '';

                    if (tags.length === 0) {
                        tagsList.innerHTML = '<p class="text-sm text-slate-500 text-center py-4">Нет тегов. Создайте первый тег!</p>';
                        return;
                    }

                    tags.forEach(tag => {
                        const tagItem = document.createElement('div');
                        tagItem.className = 'flex items-center justify-between p-3 bg-white rounded-lg border border-slate-200 hover:border-slate-300 hover:shadow-sm transition-all';
                        tagItem.innerHTML = `
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-full flex-shrink-0 border border-slate-200" style="background-color: ${tag.color}"></div>
                                <div class="flex-1 min-w-0">
                                    <span class="font-medium text-slate-900 text-sm sm:text-base block truncate">${tag.name}</span>
                                    <span class="text-xs text-slate-500 font-mono">${tag.color}</span>
                                </div>
                            </div>
                            <div class="flex gap-1.5 flex-shrink-0">
                                <button onclick="window.openEditTagModal(${tag.id}, '${tag.name.replace(/'/g, "\\'")}', '${tag.color}')"
                                        class="p-1.5 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                </button>
                                <button onclick="deleteTag(${tag.id})"
                                        class="p-1.5 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        `;
                        tagsList.appendChild(tagItem);
                    });
                })
                .catch(error => {
                    console.error('Error loading tags:', error);
                });
        }

        function deleteTag(id) {
            if (!confirm('Удалить этот тег? Тег будет удален из всех задач.')) {
                return;
            }

            fetch(`/tags/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadTags();
                    // Обновляем список тегов в модальном окне задачи
                    updateTaskModalLists();
                } else {
                    alert(data.error || 'Ошибка при удалении тега');
                }
            })
            .catch(error => {
                console.error('Error deleting tag:', error);
                alert('Ошибка при удалении тега');
            });
        }

    </script>

    <!-- Priority Management Modal -->
    <div id="priorityModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-3 sm:p-4 overflow-y-auto" onclick="if(event.target === this) window.closePriorityModal()">
        <div class="bg-white rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-md sm:max-w-lg my-auto transform transition-all" onclick="event.stopPropagation()">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 sm:p-5 border-b border-slate-200">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-1.5 sm:p-2 bg-purple-100 rounded-lg flex-shrink-0">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-bold text-slate-900">Приоритеты</h3>
                </div>
                <button onclick="window.closePriorityModal()" class="p-1.5 sm:p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="p-4 sm:p-5 max-h-[calc(100vh-200px)] overflow-y-auto">
                <!-- Create Priority Form -->
                <div class="mb-4 sm:mb-5 p-3 sm:p-4 bg-purple-50 rounded-lg border border-purple-100">
                    <h4 class="text-sm sm:text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Новый приоритет
                    </h4>
                    <form id="priorityForm">
                        <div class="space-y-3 mb-3">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">Название</label>
                                <input type="text" name="name" required placeholder="Например: Критический"
                                       class="w-full px-3 py-2 text-sm sm:text-base border border-slate-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors bg-white">
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">Цвет</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="color" id="priorityColor" value="#3b82f6"
                                           class="w-12 h-10 sm:w-14 sm:h-12 border border-slate-300 rounded-lg cursor-pointer">
                                    <input type="text" value="#3b82f6" id="priorityColorText" placeholder="#3b82f6"
                                           class="flex-1 px-3 py-2 text-xs sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors font-mono bg-white">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-4 py-2.5 rounded-lg text-sm sm:text-base font-medium shadow-sm hover:shadow transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Создать
                        </button>
                    </form>
                </div>

                <!-- Priorities List -->
                <div>
                    <h4 class="text-sm sm:text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        Список приоритетов
                    </h4>
                    <div id="prioritiesList" class="space-y-2 max-h-64 sm:max-h-80 overflow-y-auto">
                        <!-- Priorities will be loaded here -->
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 sm:p-5 border-t border-slate-200">
                <button onclick="window.closePriorityModal()"
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 font-medium text-slate-700 text-sm sm:text-base">
                    Закрыть
                </button>
            </div>
        </div>
    </div>

    <!-- Task Details Modal -->
    <div id="taskDetailsModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto" onclick="if(event.target === this) window.closeTaskDetailsModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-5 sm:p-6 w-full max-w-lg my-4 transform transition-all max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-blue-100 rounded-lg flex-shrink-0">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl sm:text-2xl font-bold text-slate-900 leading-tight" id="taskDetailsTitle">Подробности задачи</h3>
                </div>
                <button onclick="window.closeTaskDetailsModal()" class="p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="space-y-5">
                <!-- Title -->
                <div>
                    <p class="text-lg sm:text-xl font-bold text-slate-900 break-words" id="taskDetailsTitleText"></p>
                </div>

                <!-- Description -->
                <div id="taskDetailsDescriptionSection" style="display: none;" class="w-full overflow-hidden">
                    <p class="text-sm sm:text-base text-slate-700 whitespace-pre-wrap leading-relaxed break-words break-all w-full max-w-full overflow-wrap-anywhere" id="taskDetailsDescription"></p>
                </div>

                <!-- Status -->
                <div>
                    <span id="taskDetailsStatus" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium"></span>
                </div>

                <!-- Priority -->
                <div id="taskDetailsPrioritySection" style="display: none;">
                    <span id="taskDetailsPriority"></span>
                </div>

                <!-- Project -->
                <div id="taskDetailsProjectSection" style="display: none;">
                    <span id="taskDetailsProject"></span>
                </div>

                <!-- Due Date -->
                <div id="taskDetailsDueDateSection" style="display: none;">
                    <span id="taskDetailsDueDate" class="text-sm sm:text-base text-slate-700"></span>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 mt-6 pt-6 border-t border-slate-200">
                <button onclick="window.editTaskFromDetails()"
                        class="flex-1 inline-flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    Редактировать
                </button>
                <button onclick="window.closeTaskDetailsModal()"
                        class="px-6 py-3 border border-slate-300 rounded-xl hover:bg-slate-50 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 font-medium text-slate-700">
                    Закрыть
                </button>
            </div>
        </div>
    </div>

    <!-- Priority Edit Modal -->
    <div id="priorityEditModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto" onclick="if(event.target === this) window.closeEditPriorityModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-5 sm:p-6 w-full max-w-md my-4 transform transition-all" onclick="event.stopPropagation()">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-purple-100 rounded-lg flex-shrink-0">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-900 leading-tight">Редактировать приоритет</h3>
            </div>
            <form id="priorityEditForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="priorityEditMethod" value="PUT">
                <input type="hidden" name="priority_id" id="priorityEditId">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Название</label>
                    <input type="text" name="name" id="priorityEditName" required
                           class="w-full px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Цвет</label>
                    <div class="flex items-center gap-3">
                        <input type="color" name="color" id="priorityEditColor" value="#3b82f6"
                               class="w-16 h-12 border-2 border-slate-300 rounded-xl cursor-pointer">
                        <input type="text" value="#3b82f6" id="priorityEditColorText"
                               class="flex-1 px-4 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors font-mono text-sm">
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 mt-6 pt-4 border-t border-slate-200">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Сохранить
                    </button>
                    <button type="button" onclick="window.closeEditPriorityModal()"
                            class="px-6 py-3 border border-slate-300 rounded-xl hover:bg-slate-50 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 font-medium text-slate-700">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tag Management Modal -->
    <div id="tagModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-3 sm:p-4 overflow-y-auto" onclick="if(event.target === this) window.closeTagModal()">
        <div class="bg-white rounded-xl sm:rounded-2xl shadow-2xl w-full max-w-md sm:max-w-lg my-auto transform transition-all" onclick="event.stopPropagation()">
            <!-- Header -->
            <div class="flex items-center justify-between p-4 sm:p-5 border-b border-slate-200">
                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="p-1.5 sm:p-2 bg-indigo-100 rounded-lg flex-shrink-0">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg sm:text-xl font-bold text-slate-900">Теги</h3>
                </div>
                <button onclick="window.closeTagModal()" class="p-1.5 sm:p-2 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition-colors">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="p-4 sm:p-5 max-h-[calc(100vh-200px)] overflow-y-auto">
                <!-- Create Tag Form -->
                <div class="mb-4 sm:mb-5 p-3 sm:p-4 bg-indigo-50 rounded-lg border border-indigo-100">
                    <h4 class="text-sm sm:text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Новый тег
                    </h4>
                    <form id="tagForm">
                        <div class="space-y-3 mb-3">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">Название</label>
                                <input type="text" name="name" required placeholder="Например: Важное"
                                       class="w-full px-3 py-2 text-sm sm:text-base border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors bg-white">
                            </div>
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">Цвет</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" name="color" id="tagColor" value="#3b82f6"
                                           class="w-12 h-10 sm:w-14 sm:h-12 border border-slate-300 rounded-lg cursor-pointer">
                                    <input type="text" value="#3b82f6" id="tagColorText" placeholder="#3b82f6"
                                           class="flex-1 px-3 py-2 text-xs sm:text-sm border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors font-mono bg-white">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-lg text-sm sm:text-base font-medium shadow-sm hover:shadow transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Создать
                        </button>
                    </form>
                </div>

                <!-- Tags List -->
                <div>
                    <h4 class="text-sm sm:text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                        Список тегов
                    </h4>
                    <div id="tagsList" class="space-y-2 max-h-64 sm:max-h-80 overflow-y-auto">
                        <!-- Tags will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tag Edit Modal -->
    <div id="tagEditModal" class="hidden fixed inset-0 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center z-50 p-4 overflow-y-auto" onclick="if(event.target === this) window.closeEditTagModal()">
        <div class="bg-white rounded-2xl shadow-2xl p-5 sm:p-6 w-full max-w-md my-4 transform transition-all" onclick="event.stopPropagation()">
            <div class="flex items-center gap-3 mb-6">
                <div class="p-2 bg-indigo-100 rounded-lg flex-shrink-0">
                    <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                </div>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-900 leading-tight">Редактировать тег</h3>
            </div>
            <form id="tagEditForm" method="POST">
                @csrf
                <input type="hidden" name="_method" id="tagEditMethod" value="PUT">
                <input type="hidden" name="tag_id" id="tagEditId">
                <div class="space-y-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Название</label>
                        <input type="text" name="name" id="tagEditName" required
                               class="w-full px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors bg-white">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Цвет</label>
                        <div class="flex items-center gap-3">
                            <input type="color" name="color" id="tagEditColor" value="#3b82f6"
                                   class="w-16 h-12 border border-slate-300 rounded-xl cursor-pointer">
                            <input type="text" id="tagEditColorText" placeholder="#3b82f6"
                                   class="flex-1 px-4 py-3 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-colors font-mono bg-white">
                        </div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Сохранить
                    </button>
                    <button type="button" onclick="window.closeEditTagModal()"
                            class="px-6 py-3 border border-slate-300 rounded-xl hover:bg-slate-50 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 font-medium text-slate-700">
                        Отмена
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Инициализация обработчиков для форм приоритетов после загрузки DOM
        document.addEventListener('DOMContentLoaded', function() {
            // Priority Create Form
            const priorityForm = document.getElementById('priorityForm');
            if (priorityForm) {
                priorityForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const data = {
                        name: formData.get('name'),
                        color: formData.get('color')
                    };

                    fetch('{{ route("priorities.store") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.id) {
                            this.reset();
                            const colorInput = document.getElementById('priorityColor');
                            const colorTextInput = document.getElementById('priorityColorText');
                            if (colorInput) colorInput.value = '#3b82f6';
                            if (colorTextInput) colorTextInput.value = '#3b82f6';
                            loadPriorities();
                            // Обновляем список приоритетов в модальном окне задачи
                            updateTaskModalLists();
                        }
                    })
                    .catch(error => {
                        console.error('Error creating priority:', error);
                        alert('Ошибка при создании приоритета');
                    });
                });
            }

            // Priority Edit Form
            const priorityEditForm = document.getElementById('priorityEditForm');
            if (priorityEditForm) {
                priorityEditForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const id = formData.get('priority_id');
                    const data = {
                        name: formData.get('name'),
                        color: formData.get('color')
                    };

                    fetch(`/priorities/${id}`, {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.id) {
                            window.closeEditPriorityModal();
                            loadPriorities();
                            // Обновляем список приоритетов в модальном окне задачи
                            updateTaskModalLists();
                        }
                    })
                    .catch(error => {
                        console.error('Error updating priority:', error);
                        alert('Ошибка при обновлении приоритета');
                    });
                });
            }

            // Color picker sync for priority forms
            const priorityColor = document.getElementById('priorityColor');
            const priorityColorText = document.getElementById('priorityColorText');
            if (priorityColor && priorityColorText) {
                priorityColor.addEventListener('input', function(e) {
                    priorityColorText.value = e.target.value;
                });
                priorityColorText.addEventListener('input', function(e) {
                    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
                        priorityColor.value = e.target.value;
                    }
                });
            }

            const priorityEditColor = document.getElementById('priorityEditColor');
            const priorityEditColorText = document.getElementById('priorityEditColorText');
            if (priorityEditColor && priorityEditColorText) {
                priorityEditColor.addEventListener('input', function(e) {
                    priorityEditColorText.value = e.target.value;
                });
                priorityEditColorText.addEventListener('input', function(e) {
                    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
                        priorityEditColor.value = e.target.value;
                    }
                });
            }

            // Tag Create Form
            const tagForm = document.getElementById('tagForm');
            if (tagForm) {
                tagForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const data = {
                        name: formData.get('name'),
                        color: formData.get('color')
                    };

                    fetch('{{ route("tags.store") }}', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.id) {
                            tagForm.reset();
                            document.getElementById('tagColor').value = '#3b82f6';
                            document.getElementById('tagColorText').value = '#3b82f6';
                            loadTags();
                            // Обновляем список тегов в модальном окне задачи
                            updateTaskModalLists();
                        }
                    })
                    .catch(error => {
                        console.error('Error creating tag:', error);
                        alert('Ошибка при создании тега');
                    });
                });
            }

            // Tag Edit Form
            const tagEditForm = document.getElementById('tagEditForm');
            if (tagEditForm) {
                tagEditForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const tagId = document.getElementById('tagEditId').value;
                    const formData = new FormData(this);
                    const data = {
                        name: formData.get('name'),
                        color: formData.get('color')
                    };

                    fetch(`/tags/${tagId}`, {
                        method: 'PUT',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.id) {
                            window.closeEditTagModal();
                            loadTags();
                            // Обновляем список тегов в модальном окне задачи
                            updateTaskModalLists();
                        }
                    })
                    .catch(error => {
                        console.error('Error updating tag:', error);
                        alert('Ошибка при обновлении тега');
                    });
                });
            }

            // Tag Color Inputs
            const tagColor = document.getElementById('tagColor');
            const tagColorText = document.getElementById('tagColorText');
            if (tagColor && tagColorText) {
                tagColor.addEventListener('input', function(e) {
                    tagColorText.value = e.target.value;
                });
                tagColorText.addEventListener('input', function(e) {
                    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
                        tagColor.value = e.target.value;
                    }
                });
            }

            const tagEditColor = document.getElementById('tagEditColor');
            const tagEditColorText = document.getElementById('tagEditColorText');
            if (tagEditColor && tagEditColorText) {
                tagEditColor.addEventListener('input', function(e) {
                    tagEditColorText.value = e.target.value;
                });
                tagEditColorText.addEventListener('input', function(e) {
                    if (/^#[0-9A-Fa-f]{6}$/.test(e.target.value)) {
                        tagEditColor.value = e.target.value;
                    }
                });
            }

            // Project Form
            const projectForm = document.getElementById('projectForm');
            if (projectForm) {
                projectForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const method = document.getElementById('projectMethod').value;
                    const projectId = document.getElementById('projectId').value;
                    const url = projectId ? `/projects/${projectId}` : '{{ route("projects.store") }}';

                    const data = {
                        name: formData.get('name'),
                        description: formData.get('description') || null,
                        color: formData.get('color')
                    };

                    fetch(url, {
                        method: method,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.closeProjectModal();
                            // Сохраняем ID нового проекта, если это создание (не обновление)
                            const newProjectId = !projectId && data.project ? data.project.id : null;
                            refreshDashboard(newProjectId);
                        } else {
                            alert(data.message || 'Ошибка при сохранении проекта');
                        }
                    })
                    .catch(error => {
                        console.error('Error saving project:', error);
                        alert('Ошибка при сохранении проекта');
                    });
                });
            }

            // Task Form
            const taskForm = document.getElementById('taskForm');
            if (taskForm) {
                taskForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const method = document.getElementById('taskMethod').value;
                    const taskId = document.getElementById('taskId').value;
                    const url = taskId ? `/tasks/${taskId}` : '{{ route("tasks.store") }}';

                    // Собираем выбранные теги
                    const tagIds = [];
                    document.querySelectorAll('input[name="tag_ids[]"]:checked').forEach(checkbox => {
                        tagIds.push(parseInt(checkbox.value));
                    });

                    const data = {
                        project_id: formData.get('project_id') || null,
                        title: formData.get('title'),
                        description: formData.get('description') || null,
                        priority_id: formData.get('priority_id') || null,
                        due_date: formData.get('due_date') || null,
                        due_time: formData.get('due_time') || null,
                        tag_ids: tagIds
                    };

                    if (method === 'PUT' && taskId) {
                        data.completed = formData.get('completed') === 'on';
                    }

                    fetch(url, {
                        method: method,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.closeTaskModal();
                            refreshDashboard();
                        } else {
                            alert(data.message || 'Ошибка при сохранении задачи');
                        }
                    })
                    .catch(error => {
                        console.error('Error saving task:', error);
                        alert('Ошибка при сохранении задачи');
                    });
                });
            }
        });
    </script>

    <script>
        // Функция для обновления списков в модальном окне задачи
        function updateTaskModalLists() {
            // Обновляем список приоритетов
            fetch('{{ route("priorities.index") }}')
                .then(response => response.json())
                .then(priorities => {
                    const prioritySelect = document.getElementById('taskPriority');
                    if (prioritySelect) {
                        const currentValue = prioritySelect.value;
                        prioritySelect.innerHTML = '<option value="">Без приоритета</option>';
                        priorities.forEach(priority => {
                            const option = document.createElement('option');
                            option.value = priority.id;
                            option.textContent = priority.name;
                            option.style.color = priority.color;
                            prioritySelect.appendChild(option);
                        });
                        // Восстанавливаем выбранное значение
                        if (currentValue) {
                            const optionExists = Array.from(prioritySelect.options).some(option => String(option.value) === String(currentValue));
                            if (optionExists) {
                                prioritySelect.value = currentValue;
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading priorities for task modal:', error);
                });

            // Обновляем список тегов
            fetch('{{ route("tags.index") }}')
                .then(response => response.json())
                .then(tags => {
                    const tagsContainer = document.querySelector('#taskModal div.space-y-2.max-h-32');
                    if (tagsContainer) {
                        // Сохраняем выбранные теги
                        const selectedTagIds = [];
                        tagsContainer.querySelectorAll('input[name="tag_ids[]"]:checked').forEach(checkbox => {
                            selectedTagIds.push(checkbox.value);
                        });

                        // Обновляем контейнер
                        if (tags.length === 0) {
                            tagsContainer.innerHTML = '<p class="text-sm text-slate-500 text-center py-2">Нет тегов. Создайте теги в меню "Теги"</p>';
                        } else {
                            tagsContainer.innerHTML = '';
                            tags.forEach(tag => {
                                const label = document.createElement('label');
                                label.className = 'flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-2 rounded-lg transition-colors';
                                label.innerHTML = `
                                    <input type="checkbox" name="tag_ids[]" value="${tag.id}" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" ${selectedTagIds.includes(String(tag.id)) ? 'checked' : ''}>
                                    <span class="inline-flex items-center gap-1.5 px-2 py-1 rounded-full text-xs font-medium text-white" style="background-color: ${tag.color}">
                                        ${tag.name}
                                    </span>
                                `;
                                tagsContainer.appendChild(label);
                            });
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading tags for task modal:', error);
                });
        }

        // Функция для обновления дашборда без перезагрузки
        function refreshDashboard(switchToProjectId = null) {
            fetch('{{ route("dashboard") }}', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.html) {
                    // Парсим HTML и обновляем только нужные части
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(data.html, 'text/html');

                    // Определяем, на какую вкладку переключиться
                    let targetTabId = switchToProjectId || null;
                    if (!targetTabId) {
                        // Пытаемся получить из localStorage
                        try {
                            targetTabId = localStorage.getItem('selectedProjectTab') || 'all';
                        } catch (e) {
                            // Если не удалось, используем активную вкладку
                            const activeTab = document.querySelector('.tab-button.bg-blue-600');
                            targetTabId = activeTab ? activeTab.id.replace('tab-', '') : 'all';
                        }
                    }

                    // Обновляем список проектов в табах (десктоп)
                    const desktopTabsContainers = document.querySelectorAll('.hidden.sm\\:block.bg-white.rounded-xl.shadow-md.mb-6');
                    const newDesktopTabs = doc.querySelectorAll('.hidden.sm\\:block.bg-white.rounded-xl.shadow-md.mb-6');
                    if (newDesktopTabs.length > 0 && desktopTabsContainers.length > 0) {
                        desktopTabsContainers[0].innerHTML = newDesktopTabs[0].innerHTML;
                    }

                    // Обновляем список проектов в мобильном меню
                    const mobileTabsContainer = document.querySelector('#mobileActionsMenu .bg-white.rounded-xl.shadow-md .flex.items-center');
                    const newMobileTabsContainer = doc.querySelector('#mobileActionsMenu .bg-white.rounded-xl.shadow-md .flex.items-center');
                    if (newMobileTabsContainer && mobileTabsContainer) {
                        mobileTabsContainer.innerHTML = newMobileTabsContainer.innerHTML;
                    }

                    // Обновляем контент табов
                    const newTabContents = doc.querySelectorAll('.tab-content');
                    // Находим контейнер для табов (родительский элемент первого таба)
                    const firstTabContent = document.getElementById('tab-content-all');
                    const tabsContainer = firstTabContent ? firstTabContent.parentElement : null;

                    newTabContents.forEach(newContent => {
                        const tabId = newContent.id;
                        const oldContent = document.getElementById(tabId);
                        if (oldContent) {
                            // Обновляем содержимое, сохраняя элемент
                            oldContent.innerHTML = newContent.innerHTML;
                            // Обновляем классы, если они изменились
                            oldContent.className = newContent.className;
                        } else if (tabsContainer) {
                            // Если это новый проект, добавляем его контент в DOM
                            tabsContainer.insertAdjacentHTML('beforeend', newContent.outerHTML);
                        }
                    });

                    // Переключаемся на нужную вкладку
                    setTimeout(() => {
                        if (targetTabId) {
                            // Проверяем, существует ли вкладка с таким ID
                            const tabButton = document.getElementById('tab-' + targetTabId);
                            const tabContent = document.getElementById('tab-content-' + targetTabId);
                            if (tabButton && tabContent) {
                                window.switchTab(targetTabId);
                            } else {
                                // Если вкладка не существует (проект был удален), переключаемся на "all"
                                window.switchTab('all');
                            }
                        }
                    }, 50);

                    // Переинициализируем drag and drop
                    setTimeout(() => {
                        initDragAndDrop();
                    }, 100);

                    // Обновляем списки в модальном окне задачи
                    const newTaskModal = doc.getElementById('taskModal');
                    if (newTaskModal) {
                        const oldTaskModal = document.getElementById('taskModal');
                        if (oldTaskModal) {
                            // Обновляем список проектов
                            const newProjectSelect = newTaskModal.querySelector('#taskProjectId');
                            const oldProjectSelect = oldTaskModal.querySelector('#taskProjectId');
                            if (newProjectSelect && oldProjectSelect) {
                                const currentValue = oldProjectSelect.value;
                                oldProjectSelect.innerHTML = newProjectSelect.innerHTML;
                                if (currentValue) {
                                    const optionExists = Array.from(oldProjectSelect.options).some(option => String(option.value) === String(currentValue));
                                    if (optionExists) {
                                        oldProjectSelect.value = currentValue;
                                    }
                                }
                            }

                            // Обновляем список приоритетов
                            const newPrioritySelect = newTaskModal.querySelector('#taskPriority');
                            const oldPrioritySelect = oldTaskModal.querySelector('#taskPriority');
                            if (newPrioritySelect && oldPrioritySelect) {
                                const currentValue = oldPrioritySelect.value;
                                oldPrioritySelect.innerHTML = newPrioritySelect.innerHTML;
                                if (currentValue) {
                                    const optionExists = Array.from(oldPrioritySelect.options).some(option => String(option.value) === String(currentValue));
                                    if (optionExists) {
                                        oldPrioritySelect.value = currentValue;
                                    }
                                }
                            }

                            // Обновляем список тегов
                            const newTagsContainer = newTaskModal.querySelector('div.space-y-2.max-h-32');
                            const oldTagsContainer = oldTaskModal.querySelector('div.space-y-2.max-h-32');
                            if (newTagsContainer && oldTagsContainer) {
                                // Сохраняем выбранные теги
                                const selectedTagIds = [];
                                oldTagsContainer.querySelectorAll('input[name="tag_ids[]"]:checked').forEach(checkbox => {
                                    selectedTagIds.push(checkbox.value);
                                });

                                // Обновляем контейнер
                                oldTagsContainer.innerHTML = newTagsContainer.innerHTML;

                                // Восстанавливаем выбранные теги
                                selectedTagIds.forEach(tagId => {
                                    const checkbox = oldTagsContainer.querySelector(`input[name="tag_ids[]"][value="${tagId}"]`);
                                    if (checkbox) {
                                        checkbox.checked = true;
                                    }
                                });
                            }
                        }
                    }

                    // Обновляем обработчики событий для новых элементов
                    reinitializeEventHandlers();
                } else {
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error refreshing dashboard:', error);
                location.reload();
            });
        }

        // Функция для переинициализации обработчиков событий
        function reinitializeEventHandlers() {
            // Обновляем обработчики для форм toggle задач
            document.querySelectorAll('.task-toggle-form').forEach(form => {
                const taskIdMatch = form.action.match(/\/tasks\/(\d+)\/toggle/);
                if (taskIdMatch) {
                    const taskId = parseInt(taskIdMatch[1]);
                    form.onsubmit = function(e) {
                        e.preventDefault();
                        toggleTaskComplete(taskId, this);
                        return false;
                    };
                }
            });
        }

        // Функция для переключения статуса задачи
        function toggleTaskComplete(taskId, formElement) {
            fetch(`/tasks/${taskId}/toggle`, {
                method: 'PATCH',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshDashboard();
                } else {
                    alert(data.message || 'Ошибка при изменении статуса задачи');
                }
            })
            .catch(error => {
                console.error('Error toggling task:', error);
                alert('Ошибка при изменении статуса задачи');
            });
        }

        // Функция для удаления задачи
        function deleteTask(taskId, formElement) {
            fetch(`/tasks/${taskId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshDashboard();
                } else {
                    alert(data.message || 'Ошибка при удалении задачи');
                }
            })
            .catch(error => {
                console.error('Error deleting task:', error);
                alert('Ошибка при удалении задачи');
            });
        }

        // Функция для удаления проекта
        function deleteProject(projectId) {
            if (!confirm('Удалить этот проект? Все задачи этого проекта останутся без проекта.')) {
                return;
            }

            fetch(`/projects/${projectId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    refreshDashboard();
                } else {
                    alert(data.message || 'Ошибка при удалении проекта');
                }
            })
            .catch(error => {
                console.error('Error deleting project:', error);
                alert('Ошибка при удалении проекта');
            });
        }
    </script>
</body>
</html>

