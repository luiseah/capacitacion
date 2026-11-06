<?php

namespace App\Requests;

use App\Enums\FieldType;

final readonly class Field
{
    /**
     * @param  array<int, mixed>  $rules  Extra validation rules on top of the type's base rules.
     * @param  array<int, string>  $options  Allowed values for select fields.
     */
    public function __construct(
        public string $key,
        public string $label,
        public FieldType $type,
        public bool $required = false,
        public array $rules = [],
        public array $options = [],
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function validationRules(): array
    {
        return [
            $this->required ? 'required' : 'nullable',
            ...$this->type->baseRules($this->options),
            ...$this->rules,
        ];
    }

    /**
     * Shape sent to the frontend to render the form input.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'options' => $this->options,
        ];
    }
}
