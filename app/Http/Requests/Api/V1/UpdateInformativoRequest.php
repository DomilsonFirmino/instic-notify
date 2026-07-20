<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateInformativoRequest extends ApiRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $allowedStatuses = $this->user()?->hasRole('admin')
            ? ['rascunho', 'pendente', 'aprovado']
            : ['rascunho', 'pendente'];

        return [
            'title' => ['sometimes','required','string','max:255'],
            'content' => ['sometimes','required','string'],
            // Admin may set aprovado to skip review; others only draft/pending
            'status' => ['sometimes','required','string', Rule::in($allowedStatuses)],
            'category_id' => ['sometimes','required','integer','exists:categories,id'],
            'course_id' => ['nullable','integer','exists:courses,id'],
            'year_id' => ['nullable','integer','exists:years,id'],
            'department_id' => ['nullable','integer','exists:departments,id'],
            'publish_at' => ['nullable','date'],
            'unpublished_at' => ['nullable','date'],
        ];
    }
}
