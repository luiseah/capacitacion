<?php

namespace App\Http\Requests\Users;

use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Deleting a user: the permission and "nobody deletes themselves" rules live in
 * {@see \App\Policies\UserPolicy::delete()}; the policy's message is what the 403 carries.
 */
class UserDestroyRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('delete', $this->route('user'));
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
