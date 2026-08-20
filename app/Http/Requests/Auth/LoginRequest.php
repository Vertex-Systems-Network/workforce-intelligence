<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/** Provides login request behavior within the WorkIntel application. */ class LoginRequest extends FormRequest
{
    /** Determines whether the current request is authorized. */ public function authorize(): bool
    {
        return true;
    }

    /** Defines validation rules for the incoming request. */ public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
            'mfa_code' => ['nullable', 'string', 'max:32'],
        ];
    }
}
