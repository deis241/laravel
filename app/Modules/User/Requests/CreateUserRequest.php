<?php

namespace App\Modules\User\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['nullable', 'string', 'min:8'],
            'status'    => ['nullable', 'in:active,inactive'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'role_id'   => ['nullable', 'integer', 'exists:roles,id'],
        ];
    }
}
