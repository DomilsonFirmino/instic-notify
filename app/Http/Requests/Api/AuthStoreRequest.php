<?php

namespace App\Http\Requests\Api;

class AuthStoreRequest extends ApiRequest
{
    public function authorize(): bool {
        return !$this->user();
    }
    public function rules()
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
