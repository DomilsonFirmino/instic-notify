<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiRequest;

class StoreInformativoRequest extends ApiRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'title' => ['required','string','max:255'],
            'content' => ['required','string'],
            'status' => ['required','string'],
            'category_id' => ['required','integer','exists:categories,id'],
            'course_id' => ['nullable','integer','exists:courses,id'],
            'year_id' => ['nullable','integer','exists:years,id'],
            'author_id' => ['required','integer','exists:users,id'],
        ];
    }
}
