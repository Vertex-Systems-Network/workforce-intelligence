<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Provides person request behavior within the WorkIntel application. */ class PersonRequest extends FormRequest
{
    /** Determines whether the current request is authorized. */ public function authorize(): bool
    {
        return true;
    }

    /** Defines validation rules for the incoming request. */ public function rules(): array
    {
        $memberId = $this->route('member')?->id;
        $userId = $this->route('member')?->user_id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:40'],
            'avatar_url' => ['nullable', 'url', 'max:1000'],
            'locale' => ['nullable', 'string', 'max:12'],
            'password' => [$memberId ? 'nullable' : 'required', 'nullable', 'string', 'min:8'],
            'employee_code' => ['nullable', 'string', 'max:40'],
            'job_title_id' => ['nullable', 'integer'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'department_id' => ['nullable', 'integer'],
            'manager_id' => ['nullable', 'integer'],
            'role_slug' => ['nullable', 'string', 'max:80'],
            'role_slugs' => ['nullable', 'array', 'min:1', 'max:20'],
            'role_slugs.*' => ['string', 'max:80'],
            'primary_role_slug' => ['nullable', 'string', 'max:80'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contractor', 'intern'])],
            'joining_date' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::in(['active', 'invited', 'suspended', 'archived'])],
        ];
    }
}
