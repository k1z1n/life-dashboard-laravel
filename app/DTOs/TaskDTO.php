<?php

namespace App\DTOs;

use Carbon\Carbon;

class TaskDTO
{
    public function __construct(
        public ?int $id = null,
        public ?int $userId = null,
        public ?int $projectId = null,
        public string $title = '',
        public ?string $description = null,
        public bool $completed = false,
        public ?int $priorityId = null,
        public ?Carbon $dueDate = null,
        public ?string $dueTime = null,
        public int $order = 0,
        public array $tagIds = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            userId: isset($data['user_id']) ? (int)$data['user_id'] : null,
            projectId: $data['project_id'] ?? null,
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            completed: $data['completed'] ?? false,
            priorityId: isset($data['priority_id']) ? (int)$data['priority_id'] : null,
            dueDate: isset($data['due_date']) ? Carbon::parse($data['due_date']) : null,
            dueTime: $data['due_time'] ?? null,
            order: $data['order'] ?? 0,
            tagIds: isset($data['tag_ids']) && is_array($data['tag_ids']) ? array_map('intval', $data['tag_ids']) : [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->userId,
            'project_id' => $this->projectId,
            'title' => $this->title,
            'description' => $this->description,
            'completed' => $this->completed,
            'priority_id' => $this->priorityId,
            'due_date' => $this->dueDate?->format('Y-m-d'),
            'due_time' => $this->dueTime,
            'order' => $this->order,
        ], fn($value) => $value !== null);
    }
}

