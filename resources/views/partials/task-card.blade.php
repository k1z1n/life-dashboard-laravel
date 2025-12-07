<div class="task-item-wrapper relative transition-all duration-300 ease-out py-1.5 sm:py-2 w-full" style="will-change: transform;">
    <div class="flex items-start gap-2 sm:gap-3 p-3 sm:p-4 lg:p-5 rounded-xl border border-slate-200 hover:border-slate-300 hover:shadow-md bg-white transition-all duration-200 {{ $task->completed ? 'opacity-60 bg-slate-50' : '' }} task-item cursor-pointer w-full max-w-full overflow-hidden" 
         draggable="true" 
         data-task-id="{{ $task->id }}"
         onclick="if (!event.target.closest('.task-toggle-form') && !event.target.closest('button[type=\'submit\']')) { window.openTaskDetailsModal({{ $task->id }}, '{{ addslashes($task->title) }}', '{{ addslashes($task->description ?? '') }}', {{ $task->priority_id ?? 'null' }}, '{{ $task->due_date ? $task->due_date->format('Y-m-d') : '' }}', {{ $task->project_id ?? 'null' }}, '{{ $task->due_time ?? '' }}', {{ $task->completed ? 'true' : 'false' }}, '{{ $task->priority ? addslashes($task->priority->name) : '' }}', '{{ $task->priority ? $task->priority->color : '' }}', @if($task->project_id) @php $projectData = \App\Models\Project::find($task->project_id); @endphp @if($projectData) '{{ addslashes($projectData->name) }}', '{{ $projectData->color }}' @else '', '' @endif @else '', '' @endif); }">
        <!-- Checkbox -->
        <form action="{{ route('tasks.toggle', $task) }}" method="POST" class="mt-0.5 flex-shrink-0 task-toggle-form" 
              onmousedown="event.stopPropagation()" 
              onclick="event.stopPropagation()" 
              ontouchstart="event.stopPropagation()"
              onsubmit="event.preventDefault(); toggleTaskComplete({{ $task->id }}, this); return false;">
            @csrf
            @method('PATCH')
            <button type="submit" 
                    class="w-6 h-6 rounded-md border-2 {{ $task->completed ? 'bg-green-500 border-green-500' : 'border-slate-300 hover:border-green-400' }} flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                    onclick="event.stopPropagation()"
                    onmousedown="event.stopPropagation()"
                    ontouchstart="event.stopPropagation()">
                @if($task->completed)
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                    </svg>
                @endif
            </button>
        </form>
        
        <div class="flex-1 min-w-0 overflow-hidden">
            <h3 class="font-semibold text-slate-900 {{ $task->completed ? 'line-through text-slate-500' : '' }} text-base leading-tight break-words">
                {{ $task->title }}
            </h3>
            <div class="flex items-center gap-2 mt-3 flex-wrap">
                <!-- Priority Badge -->
                @if($task->priority)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white border border-transparent shadow-sm" style="background-color: {{ $task->priority->color }}">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        {{ $task->priority->name }}
                    </span>
                @endif
                
                <!-- Project Badge -->
                @if($task->project_id)
                    @php
                        $project = \App\Models\Project::find($task->project_id);
                    @endphp
                    @if($project)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium text-white border border-transparent shadow-sm" style="background-color: {{ $project->color }}">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                            </svg>
                            {{ $project->name }}
                        </span>
                    @endif
                @endif
                
                <!-- Tags -->
                @if($task->tags && $task->tags->count() > 0)
                    @foreach($task->tags as $tag)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium text-white border border-transparent shadow-sm" style="background-color: {{ $tag->color }}" data-tag-id="{{ $tag->id }}">
                            {{ $tag->name }}
                        </span>
                    @endforeach
                @endif
                
                <!-- Due Date -->
                @if($task->due_date && !$task->completed)
                    <span class="inline-flex items-center gap-1.5 {{ $task->isOverdue() ? 'text-red-600 font-semibold' : 'text-slate-600' }} text-xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        {{ $task->due_date->format('d.m.Y') }}
                        @if($task->due_time && $task->isDueToday())
                            в {{ $task->due_time }}
                        @endif
                    </span>
                    
                    @php
                        $hoursLeft = $task->getHoursUntilDue();
                        $minutesLeft = $task->getMinutesUntilDue();
                        $daysLeft = $task->getDaysUntilDue();
                        $isToday = $task->isDueToday();
                        
                        // Определяем, что показывать
                        $showDays = $daysLeft !== null && $daysLeft >= 1;
                        $showHours = $hoursLeft !== null && $hoursLeft >= 1 && $daysLeft < 1;
                        $showMinutes = $minutesLeft !== null && $minutesLeft < 60 && $hoursLeft < 1;
                        
                        // Определяем цвет в зависимости от оставшегося времени
                        $colorClass = 'bg-green-100 text-green-700 border border-green-200';
                        if ($daysLeft !== null && $daysLeft < 1) {
                            $colorClass = 'bg-red-100 text-red-700 border border-red-200';
                        } elseif ($daysLeft !== null && $daysLeft < 3) {
                            $colorClass = 'bg-amber-100 text-amber-700 border border-amber-200';
                        }
                    @endphp
                    @if($hoursLeft !== null || $daysLeft !== null)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $colorClass }}" 
                              data-due-date="{{ $task->due_date->format('Y-m-d') }}"
                              data-due-time="{{ $task->due_time ?? '' }}"
                              data-is-today="{{ $isToday ? '1' : '0' }}"
                              data-task-id="{{ $task->id }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            @if($showDays)
                                <span class="time-left" data-days="{{ $daysLeft }}">
                                    <span class="days-left">{{ $daysLeft }}</span> 
                                    @if($daysLeft == 1)
                                        день
                                    @elseif($daysLeft >= 2 && $daysLeft <= 4)
                                        дня
                                    @else
                                        дней
                                    @endif
                                </span>
                            @elseif($showHours)
                                <span class="time-left" data-hours="{{ $hoursLeft }}">
                                    <span class="hours-left">{{ $hoursLeft }}</span> 
                                    @if($hoursLeft == 1)
                                        час
                                    @elseif($hoursLeft >= 2 && $hoursLeft <= 4)
                                        часа
                                    @else
                                        часов
                                    @endif
                                </span>
                            @elseif($showMinutes)
                                <span class="time-left" data-minutes="{{ $minutesLeft }}">
                                    <span class="minutes-left">{{ $minutesLeft }}</span> 
                                    @if($minutesLeft == 1)
                                        минута
                                    @elseif($minutesLeft >= 2 && $minutesLeft <= 4)
                                        минуты
                                    @else
                                        минут
                                    @endif
                                </span>
                            @endif
                        </span>
                    @elseif($task->isOverdue())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700 border border-red-200">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            Просрочено
                        </span>
                    @endif
                @endif
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="flex gap-1 sm:gap-1.5 flex-shrink-0 ml-1 sm:ml-2" onclick="event.stopPropagation()" onmousedown="event.stopPropagation()" ontouchstart="event.stopPropagation()">
            <button onclick="event.stopPropagation(); window.openEditTaskModal({{ $task->id }}, '{{ addslashes($task->title) }}', '{{ addslashes($task->description ?? '') }}', {{ $task->priority_id ?? 'null' }}, '{{ $task->due_date ? $task->due_date->format('Y-m-d') : '' }}', {{ $task->project_id ?? 'null' }}, '{{ $task->due_time ?? '' }}', [{{ $task->tags->pluck('id')->implode(',') }}])" 
                    class="p-1.5 sm:p-2 text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 flex-shrink-0" 
                    title="Редактировать">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
            </button>
            <button type="button" onclick="event.stopPropagation(); if(confirm('Удалить эту задачу?')) deleteTask({{ $task->id }}, this)"
                    class="p-1.5 sm:p-2 text-red-600 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 flex-shrink-0" 
                    title="Удалить"
                    onmousedown="event.stopPropagation()" 
                    ontouchstart="event.stopPropagation()">
                <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </button>
        </div>
</div>
</div>
