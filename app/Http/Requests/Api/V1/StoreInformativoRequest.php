<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiRequest;

class StoreInformativoRequest extends ApiRequest
{
    public function authorize(): bool {
        return $this->user()->hasRole('admin') || $this->user()->hasRole('editor');
    }
    public function rules(): array
    {
        return [
            'title' => ['required','string','max:255'],
            'content' => ['required','string'],
            'status' => ['required','string','in:rascunho,pendente,revisao,aprovado,agendado,publicado,despublicado,rejeitado'],
            'category_id' => ['required','integer','exists:categories,id'],
            'course_id' => ['nullable','integer','exists:courses,id'],
            'year_id' => ['nullable','integer','exists:years,id'],
            'department_id' => ['nullable','integer','exists:departments,id'],
            // author_id is always set from authenticated user
            'author_id' => ['prohibited'],
            'publish_at' => ['nullable','date'],
            'published_at' => ['nullable','date'],
            'unpublished_at' => ['nullable','date'],
        ];
    }
}
