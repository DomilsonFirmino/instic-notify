<?php

namespace App\Http\Requests\Api;

use App\Traits\ApiResponseTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class ApiRequest extends FormRequest
{
    use ApiResponseTrait;

    protected function failedValidation(Validator $validator)
    {
        $response = $this->error(
            'Falha na validação.',
            'VALIDATION_ERROR',
            $validator->errors()->toArray(),
            422
        );

        throw new HttpResponseException($response);
    }
}
