<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiRequest;

class StoreDepartmentRequest extends ApiRequest
{
    public function authorize(): bool {
        $user = $this->user();
        return $user && method_exists($user, 'hasRole') && $user->hasRole('admin');
    }
    public function rules(): array { return ['name' => ['required','string','max:255','unique:departments,name']]; }
}
