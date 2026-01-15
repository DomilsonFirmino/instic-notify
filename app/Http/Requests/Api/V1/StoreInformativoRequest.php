<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Api\ApiRequest;
use Illuminate\Validation\Rule;

class StoreInformativoRequest extends ApiRequest
{
    public function authorize(): bool {
        return $this->user()->hasRole('admin') || $this->user()->hasRole('editor');
    }
    public function rules(): array
    {
        return [
            // Avoid duplicated informativos by unique title within the same audience scope
            // Scope uniqueness by optional course/year/department to allow global vs targeted items
            'title' => [
                'required','string','max:255',
                Rule::unique('informativos','title')
                    ->where(function ($query) {
                        $query->where('course_id', $this->input('course_id'))
                              ->where('year_id', $this->input('year_id'))
                              ->where('department_id', $this->input('department_id'));
                    })
            ],
            'content' => ['required','string'],
            'status' => ['required','string','in:rascunho,pendente,revisao,aprovado,agendado,publicado,despublicado,rejeitado'],
            'category_id' => ['required','integer','exists:categories,id'],
            'course_id' => ['nullable','integer','exists:courses,id'],
            'year_id' => ['nullable','integer','exists:years,id'],
            'department_id' => ['nullable','integer','exists:departments,id'],
            // author_id is always set from authenticated user
            'author_id' => ['prohibited'],
            'publish_at' => ['nullable','date'],
            'unpublish_at' => ['nullable','date'],
            // Accept single file or multiple files. Validation for each file below.
            'files' => ['nullable'],
            'files.*' => ['file','image','mimes:jpeg,png,jpg,webp','max:5120'], // max 5MB
        ];
    }
}
