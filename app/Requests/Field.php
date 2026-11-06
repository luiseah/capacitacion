<?php

namespace App\Requests;

use App\Enums\FieldType;

final readonly class Field
{
    /**
     * @param  array<int, string>  $options  Allowed values for select fields.
     */
    public function __construct(
        public string $key,
        public string $label,
        public FieldType $type,
        public bool $required = false,
        public array $options = [],
    ) {}

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
