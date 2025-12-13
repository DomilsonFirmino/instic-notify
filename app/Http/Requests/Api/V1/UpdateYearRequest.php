<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiRequest;

class UpdateYearRequest extends ApiRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['name' => ['sometimes','required','string','max:255']]; }
}
