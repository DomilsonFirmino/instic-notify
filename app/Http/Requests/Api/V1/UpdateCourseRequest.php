<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => ['sometimes','required','string','max:255'],
            'department_id' => ['sometimes','required','integer','exists:departments,id'],
        ];
    }
}
