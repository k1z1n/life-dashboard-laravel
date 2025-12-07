<?php

namespace App\DTOs;

class TagDTO
{
    public function __construct(
        public ?int $id = null,
        public ?int $userId = null,
        public string $name = '',
        public string $color = '#3b82f6',
        public ?int $order = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            userId: isset($data['user_id']) ? (int)$data['user_id'] : null,
            name: $data['name'] ?? '',
            color: $data['color'] ?? '#3b82f6',
            order: $data['order'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'user_id' => $this->userId,
            'name' => $this->name,
            'color' => $this->color,
            'order' => $this->order,
        ], fn($value) => $value !== null);
    }
}

