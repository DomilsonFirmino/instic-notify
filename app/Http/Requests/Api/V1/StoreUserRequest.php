<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        // Use Spatie Permission to check for the 'admin' role
        return $user && method_exists($user, 'hasRole') && $user->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:users,email'],
            'password' => ['required','string','min:8'],
            'role' => ['nullable','string','in:admin,editor,revisor,leitor'],
            'course_id' => ['required','integer','exists:courses,id'],
            'year_id' => ['required','integer','exists:years,id'],
            'department_id' => ['nullable','integer','exists:departments,id'],
        ];
    }
}
