<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Ownership is enforced separately in the controller (PostController::denyIfNotOwner),
     * which returns a custom 404 response instead of the default 403 from FormRequest.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'title'       => ['sometimes', 'required', 'string', 'max:255'],
            'content'     => ['sometimes', 'required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_id.required' => 'A categoria é obrigatória.',
            'category_id.exists'   => 'A categoria selecionada não existe.',
            'title.required'       => 'O título é obrigatório.',
            'title.max'            => 'O título não pode ter mais que 255 caracteres.',
            'content.required'     => 'O conteúdo é obrigatório.',
        ];
    }
}