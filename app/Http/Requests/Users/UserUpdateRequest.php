<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('user'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->profileRules($this->route('user')->id),
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'distinct', Rule::enum(RoleName::class)],
        ];
    }

    /**
     * Guard against locking yourself out: you keep the admin role.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->route('user');

                if ($this->user()->is($target)
                    && $target->hasRole(RoleName::Admin->value)
                    && ! in_array(RoleName::Admin->value, (array) $this->input('roles'), true)) {
                    $validator->errors()->add('roles', __('You cannot remove your own admin role.'));
                }
            },
        ];
    }
}
