<?php

namespace Module\AcademicMobility\Infrastructure\Resolvers;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Module\AcademicMobility\Contracts\Participant;
use Illuminate\Database\Eloquent\Builder;
use Module\AcademicMobility\Contracts\SearchableParticipantResolver;
use Module\AcademicMobility\Infrastructure\Adapters\ModelParticipant;

/**
 * Ready-made resolver for hosts whose participants are Eloquent models:
 *
 *   AcademicMobility::registerParticipantResolver('student', fn () =>
 *       new EloquentParticipantResolver(Student::class, 'student', fn ($s) => $s->full_name, ['program', 'gpa']));
 */
class EloquentParticipantResolver implements SearchableParticipantResolver
{
    /**
     * @param  class-string<Model>  $model
     * @param  string|Closure(Model): string  $name
     * @param  array<int, string>|Closure(Model): array<string, mixed>  $attributes
     * @param  string|null  $keyColumn  Column to look the id up in (null = the model's primary key).
     * @param  (Closure(Model): array<string, mixed>)|null  $details  Label => value shown next to the name.
     * @param  Closure(Builder, string, ?string): mixed|null  $search  Narrows the query to a search term (and to an institution, for visitors); without it the participants cannot be searched.
     * @param  (Closure(Model): ?string)|null  $institution  Id of the institution a visitor belongs to.
     * @param  (Closure(Builder): mixed)|null  $scope  Applied to every query: which rows are participants of this source (e.g. one kind).
     */
    public function __construct(
        private readonly string $model,
        private readonly string $type,
        private readonly string|Closure $name = 'name',
        private readonly array|Closure $attributes = [],
        private readonly ?string $keyColumn = null,
        private readonly ?Closure $search = null,
        private readonly ?Closure $details = null,
        private readonly ?Closure $institution = null,
        private readonly ?Closure $scope = null,
    ) {}

    public function resolve(string|int $id): ?Participant
    {
        $query = $this->query();

        $model = $this->keyColumn === null ? $query->find($id) : $query->where($this->keyColumn, $id)->first();

        return $model === null ? null : new ModelParticipant($model, $this->type, $this->name, $this->attributes, $this->details, $this->institution);
    }

    public function search(string $term, int $limit = 10, ?string $institutionId = null): array
    {
        if ($this->search === null) {
            return [];
        }

        $query = $this->query();
        ($this->search)($query, $term, $institutionId);

        return $query->limit($limit)->get()
            ->map(fn (Model $model) => new ModelParticipant($model, $this->type, $this->name, $this->attributes, $this->details, $this->institution))
            ->all();
    }

    /**
     * @return Builder<Model>
     */
    private function query(): Builder
    {
        $query = $this->model::query();

        if ($this->scope !== null) {
            ($this->scope)($query);
        }

        return $query;
    }
}
