<?php

namespace App\Http\Requests\Requests;

use App\Enums\ServiceRequestRelation;
use App\Models\ServiceRequest;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Module\Pagination\Enums\SortDirection;

/**
 * The requests list: the relations it can include and how it sorts. Which requests it shows is
 * decided by `ServiceRequestBuilder::visibleTo()`.
 */
class ServiceRequestIndexRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', ServiceRequest::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'with' => ['nullable', 'array'],
            'with.*' => ['distinct', Rule::enum(ServiceRequestRelation::class)],
            'sort' => ['nullable', 'array'],
            'sort.*' => [Rule::enum(SortDirection::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
