<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#3b82f6">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Профиль - Life Dashboard</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b border-slate-200 sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-blue-600 hover:text-blue-700 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        <span class="text-sm font-medium">Назад к задачам</span>
                    </a>
                </div>
                <h1 class="text-lg sm:text-xl font-bold text-slate-900">Профиль</h1>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm text-slate-600 hover:text-slate-900 transition-colors flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        <span class="hidden sm:inline">Выйти</span>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
        <!-- Profile Info -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-2xl font-bold">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-slate-900">{{ $user->name }}</h2>
                        <p class="text-slate-600">{{ $user->email }}</p>
                    </div>
                </div>

                <!-- Telegram Connection -->
                <div id="telegram-connection" class="flex-shrink-0">
                    @if($user->hasTelegram())
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2 px-4 py-2 bg-green-50 border border-green-200 rounded-lg">
                                <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                </svg>
                                <span class="text-sm font-medium text-green-700">Telegram подключен</span>
                            </div>
                            <button onclick="disconnectTelegram()" class="px-4 py-2 text-sm font-medium text-red-600 hover:text-red-700 hover:bg-red-50 border border-red-200 rounded-lg transition-colors">
                                Отключить
                            </button>
                        </div>
                    @else
                        <button onclick="connectTelegram()" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg shadow-md hover:shadow-lg transition-all">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161l-1.86 8.779c-.14.625-.506.781-1.026.486l-2.83-2.087-1.366 1.312c-.151.151-.277.277-.569.277l.203-2.872 5.24-4.734c.228-.203-.05-.316-.354-.113l-6.473 4.078-2.789-.87c-.607-.19-.619-.607.127-.899l10.906-4.202c.506-.19.948.113.783.899z"/>
                            </svg>
                            Подключить Telegram
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">Всего выполнено</p>
                        <p class="text-3xl font-bold text-slate-900">{{ $totalCompleted }}</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">На этой неделе</p>
                        <p class="text-3xl font-bold text-slate-900">{{ $completedThisWeek }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-slate-600 mb-1">В этом месяце</p>
                        <p class="text-3xl font-bold text-slate-900">{{ $completedThisMonth }}</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completed Tasks -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200">
            <div class="p-6 border-b border-slate-200">
                <h3 class="text-xl font-bold text-slate-900 flex items-center gap-2">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                    Выполненные задачи
                </h3>
            </div>

            <div class="divide-y divide-slate-200">
                @forelse($completedTasks as $date => $tasks)
                    <div class="date-group">
                        <!-- Date Header (Clickable) -->
                        <button type="button"
                                onclick="toggleDateGroup('{{ $date }}')"
                                class="w-full px-6 py-4 flex items-center justify-between hover:bg-slate-50 transition-colors text-left group">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-900">
                                        {{ \Carbon\Carbon::parse($date)->locale('ru')->isoFormat('D MMMM YYYY') }}
                                    </h4>
                                    <p class="text-sm text-slate-600">{{ count($tasks) }} {{ \Illuminate\Support\Str::plural('задача', count($tasks)) }}</p>
                                </div>
                            </div>
                            <svg class="w-5 h-5 text-slate-400 transition-transform duration-200 chevron-icon"
                                 id="chevron-{{ $date }}"
                                 fill="none"
                                 stroke="currentColor"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>

                        <!-- Tasks List (Hidden by default) -->
                        <div id="tasks-{{ $date }}" class="hidden bg-slate-50">
                            <div class="px-6 py-4 space-y-3">
                                @foreach($tasks as $task)
                                    <div class="bg-white rounded-lg border border-slate-200 p-4 hover:shadow-md transition-shadow">
                                        <div class="flex items-start gap-3">
                                            <!-- Completed Checkbox -->
                                            <div class="mt-0.5 flex-shrink-0">
                                                <div class="w-5 h-5 bg-green-100 border-2 border-green-600 rounded flex items-center justify-center">
                                                    <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                </div>
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <h5 class="font-medium text-slate-900 line-through opacity-75">{{ $task->title }}</h5>

                                                @if($task->description)
                                                    <p class="text-sm text-slate-600 mt-1 line-through opacity-60">{{ Str::limit($task->description, 100) }}</p>
                                                @endif

                                                <div class="flex flex-wrap items-center gap-2 mt-3">
                                                    @if($task->priority)
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white"
                                                              style="background-color: {{ $task->priority->color }}">
                                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                                            </svg>
                                                            {{ $task->priority->name }}
                                                        </span>
                                                    @endif

                                                    @if($task->project)
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white"
                                                              style="background-color: {{ $task->project->color }}">
                                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                                                            </svg>
                                                            {{ $task->project->name }}
                                                        </span>
                                                    @endif

                                                    @foreach($task->tags as $tag)
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-200 text-slate-700">
                                                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                                                            </svg>
                                                            {{ $tag->name }}
                                                        </span>
                                                    @endforeach

                                                    <span class="text-xs text-slate-500 ml-auto">
                                                        {{ $task->completed_at->locale('ru')->isoFormat('HH:mm') }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 rounded-full mb-4">
                            <svg class="w-8 h-8 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                        </div>
                        <h3 class="text-lg font-medium text-slate-900 mb-2">Нет выполненных задач</h3>
                        <p class="text-slate-600 mb-6">Начните выполнять задачи, чтобы увидеть их здесь</p>
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium shadow-md hover:shadow-lg transition-all duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Перейти к задачам
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </main>

    <!-- Telegram Link Modal (для iOS/Safari PWA) -->
    <div id="telegramLinkModal" class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-md z-50 flex items-center justify-center p-4" onclick="if(event.target === this) closeTelegramLinkModal()">
        <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6" onclick="event.stopPropagation()">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold text-slate-900">Подключение Telegram</h3>
                <button onclick="closeTelegramLinkModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <p class="text-slate-600 mb-4">
                Нажмите на ссылку ниже, чтобы открыть Telegram и подключить аккаунт:
            </p>

            <div class="mb-4">
                <a id="telegramLink" href="#" target="_blank" rel="noopener noreferrer"
                   class="block w-full px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg text-center transition-colors break-all">
                    <span id="telegramLinkText">Открыть Telegram</span>
                </a>
            </div>

            <div class="flex items-center gap-2 p-3 bg-slate-50 rounded-lg mb-4">
                <button onclick="copyTelegramLink()" class="flex-1 px-4 py-2 bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 font-medium rounded-lg transition-colors text-sm">
                    📋 Копировать ссылку
                </button>
            </div>

            <p class="text-xs text-slate-500 text-center">
                После подключения вернитесь в приложение и обновите страницу
            </p>
        </div>
    </div>

    <script>
        function toggleDateGroup(date) {
            const tasksElement = document.getElementById(`tasks-${date}`);
            const chevronElement = document.getElementById(`chevron-${date}`);

            if (tasksElement.classList.contains('hidden')) {
                tasksElement.classList.remove('hidden');
                chevronElement.style.transform = 'rotate(180deg)';
            } else {
                tasksElement.classList.add('hidden');
                chevronElement.style.transform = 'rotate(0deg)';
            }
        }

        /**
         * Универсальный метод открытия Telegram ссылки
         * Работает в PWA режиме на iOS/Safari и других браузерах
         */
        function openTelegramLink(url) {
            const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
            const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
            const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
            const isPWA = isStandalone;

            // Для iOS/Safari/PWA всегда показываем модальное окно с ссылкой
            // Это самый надёжный способ для PWA режима
            if (isIOS || (isSafari && isPWA)) {
                showTelegramLinkModal(url);
                return false; // Возвращаем false чтобы показать модальное окно
            } else {
                // Для других браузеров пробуем автоматическое открытие
                try {
                    const telegramWindow = window.open(url, '_blank', 'noopener,noreferrer');

                    // Проверяем успешность открытия
                    if (!telegramWindow || telegramWindow.closed || typeof telegramWindow.closed === 'undefined') {
                        // Если window.open заблокирован, показываем модальное окно
                        showTelegramLinkModal(url);
                        return false;
                    }

                    return true;
                } catch (e) {
                    console.warn('window.open failed, showing modal:', e);
                    // Fallback на модальное окно
                    showTelegramLinkModal(url);
                    return false;
                }
            }
        }

        /**
         * Показать модальное окно с ссылкой Telegram
         */
        function showTelegramLinkModal(url) {
            const modal = document.getElementById('telegramLinkModal');
            const link = document.getElementById('telegramLink');
            const linkText = document.getElementById('telegramLinkText');

            if (modal && link) {
                link.href = url;
                linkText.textContent = url.length > 40 ? url.substring(0, 37) + '...' : url;
                modal.classList.remove('hidden');

                // Сохраняем URL для копирования
                modal.dataset.url = url;
            }
        }

        /**
         * Закрыть модальное окно
         */
        function closeTelegramLinkModal() {
            const modal = document.getElementById('telegramLinkModal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        /**
         * Копировать ссылку Telegram в буфер обмена
         */
        function copyTelegramLink(event) {
            const modal = document.getElementById('telegramLinkModal');
            const url = modal?.dataset.url;

            if (url && navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    const button = event?.target || document.querySelector('#telegramLinkModal button');
                    if (button) {
                        const originalText = button.textContent;
                        button.textContent = '✓ Скопировано!';
                        button.classList.add('bg-green-50', 'text-green-700', 'border-green-300');

                        setTimeout(() => {
                            button.textContent = originalText;
                            button.classList.remove('bg-green-50', 'text-green-700', 'border-green-300');
                        }, 2000);
                    } else {
                        alert('✓ Ссылка скопирована в буфер обмена!');
                    }
                }).catch(err => {
                    console.error('Failed to copy:', err);
                    alert('Не удалось скопировать ссылку. Попробуйте скопировать вручную.');
                });
            } else {
                // Fallback для старых браузеров
                const textArea = document.createElement('textarea');
                textArea.value = url;
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    alert('Ссылка скопирована в буфер обмена!');
                } catch (err) {
                    alert('Не удалось скопировать ссылку. Скопируйте вручную из ссылки выше.');
                }
                document.body.removeChild(textArea);
            }
        }

        async function connectTelegram() {
            console.log('connectTelegram called');
            try {
                console.log('Fetching auth link...');
                const response = await fetch('/telegram/auth/link', {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                    },
                });

                console.log('Response:', response);
                const data = await response.json();
                console.log('Data:', data);

                if (data.success) {
                    // Используем универсальный метод открытия ссылки
                    const opened = openTelegramLink(data.link);

                    // Если автоматическое открытие не удалось, модальное окно уже показано
                    // Если удалось, показываем сообщение с инструкциями
                    if (opened) {
                        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
                        const isStandalone = window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;

                        if (!isIOS && !isStandalone) {
                            alert('Откроется Telegram в новой вкладке.\n\nПосле подключения вернитесь и обновите страницу.');
                        }
                    }
                    // Если opened === false, модальное окно уже показано, ничего дополнительно не делаем
                } else {
                    alert('Ошибка: ' + (data.message || 'Не удалось создать ссылку'));
                }
            } catch (error) {
                console.error('Error connecting Telegram:', error);
                alert('Произошла ошибка при подключении Telegram');
            }
        }

        // Закрытие модального окна по Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeTelegramLinkModal();
            }
        });

        async function disconnectTelegram() {
            if (!confirm('Вы уверены, что хотите отключить Telegram?')) {
                return;
            }

            try {
                const response = await fetch('/telegram/auth/disconnect', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                });

                const data = await response.json();

                if (data.success) {
                    alert('Telegram успешно отключен');
                    location.reload();
                } else {
                    alert('Ошибка: ' + (data.message || 'Не удалось отключить'));
                }
            } catch (error) {
                console.error('Error disconnecting Telegram:', error);
                alert('Произошла ошибка при отключении Telegram');
            }
        }
    </script>
</body>
</html>
