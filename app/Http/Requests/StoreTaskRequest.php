<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = auth()->id();
        
        return [
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $userId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority_id' => ['nullable', 'integer', Rule::exists('priorities', 'id')->where('user_id', $userId)],
            'due_date' => ['nullable', 'date'],
            'due_time' => ['nullable', 'date_format:H:i'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')->where('user_id', $userId)],
        ];
    }
}
