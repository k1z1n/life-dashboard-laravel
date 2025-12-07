<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'description',
        'completed',
        'priority_id',
        'due_date',
        'due_time',
        'order',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'due_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'task_tag');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
    
    // Accessor для due_time (формат H:i)
    public function getDueTimeAttribute($value)
    {
        if (!$value) {
            return null;
        }
        // Если это уже строка в формате H:i, возвращаем как есть
        if (is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value)) {
            return $value;
        }
        // Если это время из базы, форматируем
        return $value ? date('H:i', strtotime($value)) : null;
    }


    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && !$this->completed;
    }

    public function getHoursUntilDue(): ?int
    {
        if (!$this->due_date || $this->completed) {
            return null;
        }
        
        $now = now();
        
        // Если указано время и срок сегодня, используем точное время
        if ($this->due_time && $this->due_date->isToday()) {
            $due = $this->due_date->copy()->setTimeFromTimeString($this->due_time);
        } else {
            $due = $this->due_date->endOfDay();
        }
        
        if ($due->isPast()) {
            return null; // Просрочено
        }
        
        return max(0, $now->diffInHours($due));
    }

    public function getMinutesUntilDue(): ?int
    {
        if (!$this->due_date || $this->completed) {
            return null;
        }
        
        $now = now();
        
        // Если указано время и срок сегодня, используем точное время
        if ($this->due_time && $this->due_date->isToday()) {
            $due = $this->due_date->copy()->setTimeFromTimeString($this->due_time);
        } else {
            return null; // Для других дней не показываем минуты
        }
        
        if ($due->isPast()) {
            return null; // Просрочено
        }
        
        return max(0, $now->diffInMinutes($due));
    }

    public function getDaysUntilDue(): ?int
    {
        if (!$this->due_date || $this->completed) {
            return null;
        }
        
        $now = now();
        
        // Если указано время и срок сегодня, используем точное время
        if ($this->due_time && $this->due_date->isToday()) {
            $due = $this->due_date->copy()->setTimeFromTimeString($this->due_time);
        } else {
            $due = $this->due_date->endOfDay();
        }
        
        if ($due->isPast()) {
            return null; // Просрочено
        }
        
        return max(0, $now->diffInDays($due));
    }

    public function isDueToday(): bool
    {
        return $this->due_date && $this->due_date->isToday();
    }
}
