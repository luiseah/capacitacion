<?php

namespace Module\AcademicMobility\Infrastructure\Adapters;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Module\AcademicMobility\Contracts\AffiliatedParticipant;
use Module\AcademicMobility\Contracts\DetailedParticipant;
use Module\AcademicMobility\Contracts\Participant;

/**
 * Adapts any Eloquent model of the host to {@see Participant}, so the host
 * does not have to modify `Student`, `Professor`... to take part.
 */
class ModelParticipant implements AffiliatedParticipant, DetailedParticipant, Participant
{
    /**
     * @param  string|Closure(Model): string  $name  Attribute holding the display name, or a closure building it.
     * @param  array<int, string>|Closure(Model): array<string, mixed>  $attributes  Model attributes to expose, or a closure returning the facts.
     * @param  (Closure(Model): array<string, mixed>)|null  $details  Label => value to display next to the name.
     * @param  (Closure(Model): ?string)|null  $institution  Id of the institution a visitor belongs to.
     */
    public function __construct(
        private readonly Model $model,
        private readonly string $type,
        private readonly string|Closure $name = 'name',
        private readonly array|Closure $attributes = [],
        private readonly ?Closure $details = null,
        private readonly ?Closure $institution = null,
    ) {}

    public function participantType(): string
    {
        return $this->type;
    }

    public function participantId(): string|int
    {
        return $this->model->getKey();
    }

    public function displayName(): string
    {
        return (string) ($this->name instanceof Closure ? ($this->name)($this->model) : $this->model->getAttribute($this->name));
    }

    public function attributes(): array
    {
        if ($this->attributes instanceof Closure) {
            return ($this->attributes)($this->model);
        }

        return collect($this->attributes)
            ->mapWithKeys(fn (string $attribute) => [$attribute => $this->model->getAttribute($attribute)])
            ->all();
    }

    public function details(): array
    {
        return $this->details === null
            ? []
            : array_map(fn (mixed $value) => (string) $value, array_filter(($this->details)($this->model), fn (mixed $value) => $value !== null && $value !== ''));
    }

    public function institutionId(): ?string
    {
        $id = $this->institution === null ? null : ($this->institution)($this->model);

        return $id === null ? null : (string) $id;
    }

    public function model(): Model
    {
        return $this->model;
    }
}
