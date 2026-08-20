<?php

namespace App\Http\Requests\Timer;

use Illuminate\Foundation\Http\FormRequest;

/** Provides start timer request behavior within the WorkIntel application. */ class StartTimerRequest extends FormRequest
{
    /** Determines whether the current request is authorized. */ public function authorize(): bool
    {
        return true;
    }

    /** Defines validation rules for the incoming request. */ public function rules(): array
    {
        return [
            'project_id' => ['nullable', 'integer', 'min:1'],
            'task_id' => ['nullable', 'integer', 'min:1'],
            'billable' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
