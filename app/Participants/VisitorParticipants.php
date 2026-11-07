<?php

namespace App\Participants;

use App\Models\ExternalParticipant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Module\AcademicMobility\Contracts\Participant;
use Module\AcademicMobility\Contracts\SampleParticipantResolver;
use Module\AcademicMobility\Contracts\SearchableParticipantResolver;
use Module\AcademicMobility\Core\Enums\ParticipantKind;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Infrastructure\Resolvers\EloquentParticipantResolver;
use Module\Pagination\Search\SearchInput;

/**
 * Where the participants of an incoming mobility come from: the visitors of other institutions
 * (`external_participants`), one source per kind. They can be searched by their origin institution
 * and registered on the fly when they do not exist yet. Never the university's own people.
 */
class VisitorParticipants implements SampleParticipantResolver, SearchableParticipantResolver
{
    private readonly EloquentParticipantResolver $visitors;

    public function __construct(private readonly ParticipantKind $kind)
    {
        $this->visitors = new EloquentParticipantResolver(
            ExternalParticipant::class,
            $kind->value,
            fn (ExternalParticipant $visitor) => $visitor->full_name,
            fn (ExternalParticipant $visitor) => [
                'institution_id' => $visitor->institution_id,
                'affiliation' => $visitor->affiliation,
            ],
            search: fn (Builder $query, string $term, ?string $institutionId) => $query
                ->with('institution')
                ->when($institutionId, fn (Builder $q) => $q->where('institution_id', $institutionId))
                ->where(function (Builder $q) use ($term) {
                    // `search()` ignores case and accents, like the user search.
                    $like = SearchInput::like($term);

                    foreach (["name || ' ' || lastname", 'email', 'document_number', 'code', 'affiliation'] as $column) {
                        $q->orWhereRaw("search({$column}) like search(?) escape '\\'", [$like]);
                    }
                })
                ->orderBy('lastname')->orderBy('name')->orderBy('id'),
            details: fn (ExternalParticipant $visitor) => [
                __('Institution') => $visitor->institution->name,
                __('Document') => $visitor->document_number,
                __('Code') => $visitor->code,
                $this->affiliationLabel() => $visitor->affiliation,
                __('Email') => $visitor->email,
            ],
            institution: fn (ExternalParticipant $visitor) => $visitor->institution_id,
            scope: fn (Builder $query) => $query->where('kind', $kind->value),
        );
    }

    public function resolve(string|int $id): ?Participant
    {
        return $this->visitors->resolve($id);
    }

    public function search(string $term, int $limit = 10, ?string $institutionId = null): array
    {
        return $this->visitors->search($term, $limit, $institutionId);
    }

    public function fields(): array
    {
        return [
            ['name' => 'name', 'label' => __('Name'), 'type' => 'text', 'required' => true],
            ['name' => 'lastname', 'label' => __('Last name'), 'type' => 'text', 'required' => true],
            ['name' => 'document_number', 'label' => __('Document'), 'type' => 'text', 'required' => true],
            ['name' => 'affiliation', 'label' => $this->affiliationLabel(), 'type' => 'text', 'required' => true],
            ['name' => 'code', 'label' => __('Code at their institution'), 'type' => 'text', 'required' => false],
            ['name' => 'email', 'label' => __('Email'), 'type' => 'email', 'required' => false],
            ['name' => 'phone', 'label' => __('Phone'), 'type' => 'tel', 'required' => false],
        ];
    }

    public function rules(?string $institutionId): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            // One person per document, institution and kind (the table's unique key).
            'document_number' => [
                'required', 'string', 'max:30',
                Rule::unique('external_participants', 'document_number')
                    ->where('institution_id', $institutionId)
                    ->where('kind', $this->kind->value),
            ],
            'affiliation' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function create(array $data, ?string $institutionId): Participant
    {
        $visitor = ExternalParticipant::create([...$data, 'institution_id' => $institutionId, 'kind' => $this->kind]);

        return $this->visitors->resolve($visitor->id);
    }

    /**
     * A random Spanish-named person of the institution (demos): never a document already registered there.
     */
    public function sample(?string $institutionId): array
    {
        $institution = Institution::query()->find($institutionId) ?? Institution::query()->partners()->first();

        do {
            $visitor = ExternalParticipant::factory()->from($institution)->ofKind($this->kind)->spanish()->make();
        } while (ExternalParticipant::query()
            ->where('institution_id', $institution->id)->where('kind', $this->kind)
            ->where('document_number', $visitor->document_number)->exists());

        return array_map('strval', $visitor->only(['name', 'lastname', 'document_number', 'affiliation', 'code', 'email', 'phone']));
    }

    /** What the affiliation is for this kind: a program, a faculty or a dependency. */
    private function affiliationLabel(): string
    {
        return match ($this->kind) {
            ParticipantKind::Student => __('Program'),
            ParticipantKind::Professor => __('Faculty'),
            ParticipantKind::Staff => __('Dependency'),
        };
    }
}
