<?php

namespace Module\AcademicMobility\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class UpdateInstitutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('institution'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'country' => ['required', 'string', 'size:2', 'alpha:ascii'],
            'is_local' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The local institution is replaced by making another one local, never left empty.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->route('institution')->is_local && $this->has('is_local') && ! $this->boolean('is_local')) {
                $validator->errors()->add('is_local', __('Make another institution local instead.'));
            }
        }];
    }
}
