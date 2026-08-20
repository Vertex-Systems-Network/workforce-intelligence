<?php

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Provides task request behavior within the WorkIntel application. */ class TaskRequest extends FormRequest
{
    /** Determines whether the current request is authorized. */ public function authorize(): bool { return true; }

    /** Defines validation rules for the incoming request. */ public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer'],
            'owner_member_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'description_html' => ['nullable', 'string', 'max:50000'],
            'status_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'max:64'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'start_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'billable' => ['required', 'boolean'],
            'client_visible' => ['sometimes', 'boolean'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer'],
            'observer_ids' => ['sometimes', 'array'],
            'observer_ids.*' => ['integer'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer'],
        ];
    }
}
