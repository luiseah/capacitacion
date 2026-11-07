<?php

namespace Module\Agreements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Uploads the signed document (a PDF) of an agreement.
 */
class AgreementDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows('update', $this->route('agreement'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['document' => ['required', 'file', 'mimes:pdf', 'max:10240']];
    }
}
