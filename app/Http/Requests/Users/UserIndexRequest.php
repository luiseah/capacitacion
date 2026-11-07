<?php

namespace App\Http\Requests\Users;

use App\Enums\RoleName;
use App\Enums\UserRelation;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Module\Pagination\Enums\SortDirection;

/**
 * The users list: its filters, the relations it can include and how it sorts.
 */
class UserIndexRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('viewAny', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(RoleName::class)],
            'with' => ['nullable', 'array'],
            'with.*' => ['distinct', Rule::enum(UserRelation::class)],
            'sort' => ['nullable', 'array'],
            'sort.*' => [Rule::enum(SortDirection::class)],
        ];
    }
}
