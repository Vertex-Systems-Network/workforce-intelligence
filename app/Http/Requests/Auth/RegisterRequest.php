<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/** Provides register request behavior within the WorkIntel application. */ class RegisterRequest extends FormRequest
{
    /** Determines whether the current request is authorized. */ public function authorize(): bool
    {
        return true;
    }

    /** Defines validation rules for the incoming request. */ public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'company_name' => ['required', 'string', 'max:150'],
            'password' => ['required', Password::min(12)->mixedCase()->letters()->numbers()->symbols()],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'country' => ['sometimes', 'nullable', 'string', 'size:2'],
        ];
    }
}
