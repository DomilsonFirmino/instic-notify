<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiRequest;

class UpdateUserRequest extends ApiRequest
{
    public function authorize(): bool
    {
        $auth = $this->user();

        $isAdmin = method_exists($auth, 'hasRole') && $auth->hasRole('admin');
        $routeId = $this->route('id') ?? $this->route('user') ?? $this->route('userId');
        $isSelf = $routeId !== null && (string)$auth->id === (string)$routeId;

        return $isAdmin || $isSelf;
    }

    public function rules(): array
    {
        $userId = $this->route('id');
        return [
            'name' => ['sometimes','required','string','max:255'],
            'email' => ['sometimes','required','email','max:255','unique:users,email,'.$userId],
            'password' => ['sometimes','required','string','min:8'],
            'role' => ['sometimes','required','string','in:admin,editor,revisor,leitor'],
            'course_id' => ['sometimes','required','integer','exists:courses,id'],
            'year_id' => ['sometimes','required','integer','exists:years,id'],
            'department_id' => ['nullable','integer','exists:departments,id'],
        ];
    }
}
