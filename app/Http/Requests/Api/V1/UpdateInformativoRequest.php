<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiRequest;

class UpdateInformativoRequest extends ApiRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'title' => ['sometimes','required','string','max:255'],
            'content' => ['sometimes','required','string'],
            'status' => ['sometimes','required','string'],
            'category_id' => ['sometimes','required','integer','exists:categories,id'],
            'course_id' => ['nullable','integer','exists:courses,id'],
            'year_id' => ['nullable','integer','exists:years,id'],
        ];
    }
}
