<?php

namespace App\Http\Requests\Clients;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Provides client request behavior within the WorkIntel application. */ class ClientRequest extends FormRequest
{
    /** Determines whether the current request is authorized. */ public function authorize(): bool { return true; }

    /** Defines validation rules for the incoming request. */ public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:255'],
            'billing_email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
        ];
    }
}
