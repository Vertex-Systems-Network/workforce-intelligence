<?php

namespace App\Http\Requests\Projects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Provides project request behavior within the WorkIntel application. */ class ProjectRequest extends FormRequest
{
    /** Determines whether the current request is authorized. */ public function authorize(): bool { return true; }

    /** Defines validation rules for the incoming request. */ public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:160'],
            'code' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'on_hold', 'completed', 'archived'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_type' => ['required', Rule::in(['hours', 'money', 'none'])],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
            'estimated_minutes' => ['nullable', 'integer', 'min:0'],
            'billable' => ['required', 'boolean'],
            'client_visible' => ['sometimes', 'boolean'],
            'currency' => ['required', 'string', 'size:3'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer'],
        ];
    }
}
