<?php

namespace Module\AcademicMobility\Documents;

final readonly class DocumentRequirement
{
    public function __construct(
        public string $key,
        public string $label,
        public bool $required = true,
    ) {}
}
