<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StudentStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'email', 'ends_with:@chcc.edu.ph', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'section_id' => ['required', 'exists:sections,id'],
        ];
    }
}

