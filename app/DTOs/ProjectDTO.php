<?php

namespace App\DTOs;

class ProjectDTO
{
    public function __construct(
        public ?int $id = null,
        public ?int $userId = null,
        public string $name = '',
        public ?string $description = null,
        public string $color = '#3b82f6',
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            userId: isset($data['user_id']) ? (int)$data['user_id'] : null,
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
            color: $data['color'] ?? '#3b82f6',
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->userId,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
        ], fn($value) => $value !== null);
    }
}

