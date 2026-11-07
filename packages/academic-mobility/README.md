# Academic Mobility

Reusable academic mobility management for Laravel. The package owns the mobility lifecycle; **your app
owns its people, rules, documents and approvers** and plugs them in through contracts.

## Install

```bash
composer require module/academic-mobility        # from a (private) Composer repository
# or, while developing in a monorepo: add a `path` repository to packages/academic-mobility

php artisan vendor:publish --tag=academic-mobility-config
php artisan vendor:publish --tag=academic-mobility-migrations   # then set load_migrations => false
php artisan migrate
```

The service provider and the `AcademicMobility` facade are registered by Laravel package auto-discovery.

## Architecture

One Composer package, a modular monolith. Components only talk through `Contracts/`:

| Component | Namespace | Responsibility |
|---|---|---|
| Core | `Core\*` | `Mobility` model, statuses, `CreateMobility`, persistence port, events, exceptions |
| Rules | `Rules\*` | `RuleEngine`, eligibility rules, condition evaluators, strategies |
| Documents | `Documents\*` | document requirements (providers: phase 2) |
| Workflow | `Workflow\*` | status transitions (approvals: phase 2) |
| Infrastructure | `Infrastructure\*` | adapters/resolvers for Eloquent hosts |

The package never references `User`, `Student`, `Professor`... A mobility stores only
`participant_type` (an alias such as `student`) and `participant_id` (string, any key type).

## Plugging in your app

```php
// AppServiceProvider::boot()
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Infrastructure\Resolvers\EloquentParticipantResolver;

AcademicMobility::registerParticipantResolver('student', fn () => new EloquentParticipantResolver(
    Student::class, 'student', fn ($s) => $s->full_name, ['enrollment_status', 'gpa'],
));

AcademicMobility::registerRule('active_enrollment', ActiveEnrollmentRule::class); // container-built
AcademicMobility::registerConditionEvaluator('abroad', AbroadCondition::class);
AcademicMobility::registerStrategy('exchange', ExchangeStrategy::class);
```

Also available: `registerDocumentProvider()`, `registerApprover()`. Classes can be listed in the
`rules`, `strategies`, ... keys of `config/academic-mobility.php` instead. Registering an alias again
**replaces** it, including the built-ins (`participant_attribute` rule, `default` strategy).

A strategy lists its rules as data:

```php
['rule' => 'participant_attribute', 'params' => ['attribute' => 'gpa', 'operator' => '>=', 'value' => 4.0],
 'message' => 'GPA must be at least 4.0.', 'when' => ['evaluator' => 'abroad']]
```

```php
$mobility = AcademicMobility::create(new CreateMobilityData(
    new ParticipantReference('student', $student->id),
    MobilityDirection::Outgoing, MobilityScope::International, $partner->id,   // direction null when the strategy is a DirectionalStrategy
    'exchange', ['destination' => 'abroad'],
));
$mobility->origin_institution_id;      // the local institution (outgoing)
$mobility->destination_institution_id; // $partner
$mobility->scope;                      // International (the partner is in another country)
AcademicMobility::transition($mobility, MobilityStatus::Submitted);
```

Use your own model with `config('academic-mobility.models.mobility')` (it must extend the package's `Mobility`). Listen to
`MobilityCreated` and `MobilityStatusChanged` to integrate.

## Institutions and the route of a mobility

The package owns a small `Institution` model (`name`, `country` ISO-2, `is_local`). **Exactly one is the
local institution** (the one your app belongs to; a partial unique index enforces it). Create it first
(`/academic-mobility/institutions/create`), then the partner institutions.

The local institution is **always one end** of a mobility. You choose the direction and the *other* institution;
the package places the local one:

| Direction | Origin | Destination |
|---|---|---|
| `incoming` | the other institution | the local institution |
| `outgoing` | the local institution | the other institution |

The **strategy is the kind of mobility** and picks the direction: a strategy that also implements
`Contracts\DirectionalStrategy` is only for incoming or only for outgoing mobilities and fixes it (the form does
not ask); a strategy that does not serves both, and the form asks for the direction. A direction that contradicts the
strategy's is rejected.

The type of a mobility is chosen as **participant kind × direction × scope** (e.g. a professor, outgoing, international).
The scope has to agree with the other institution: same country as the local one => `national`, otherwise
`international`; a mismatch is rejected. Direction, scope, origin and destination are stored (and filterable).

The participant kind (student, professor, staff...) is the resolver alias you register. Rules can look at
the route: `RuleContext::$route`, plus the built-in conditions
`['evaluator' => 'direction', 'params' => ['is' => 'outgoing']]` and
`['evaluator' => 'scope', 'params' => ['is' => ['international']]]`.

API: `GET|POST /api/academic-mobility/institutions`, `GET|PUT|DELETE .../institutions/{id}`
(abilities `institutions.index|create|update|destroy`; the local one cannot be deleted, and making another
one local demotes it).

## Mobilities list (API + React page)

- **API:** `GET /api/academic-mobility/mobilities` (`academic-mobility.api.mobilities.index`).
  Filters `status`, `participant_type`, `direction`, `scope`; `sort[created_at|status|participant_type|strategy]=asc|desc`;
  `page`. Answers `{ status, message, data, current_page, last_page, ... }`.
- **Page:** `GET /academic-mobility/mobilities` renders the Inertia page
  `academic-mobility/mobilities/index` (only when `inertiajs/inertia-laravel` is installed).
  React sources live in `resources/js/`; publish them with
  `php artisan vendor:publish --tag=academic-mobility-react` (or alias `@academic` to them in dev).
- **Config:** `routes.*` (prefixes, middleware, `enabled`) and `per_page`.

## ACL

The package is **deny by default** and never knows how permissions are stored.
`MobilityPolicy` (registered for `models.mobility`) asks the Laravel Gate for the
abilities in `MobilityAbility`:

| Ability | Grants |
|---|---|
| `mobilities.index` | the list (API and page) |
| `mobilities.show` | a single mobility |
| `mobilities.create` | the creation form, its options and the store endpoint |
| `institutions.index` / `.create` / `.update` / `.destroy` | the institutions pages and API |

Grant them with whatever the host uses: Spatie permissions with those names,
`Gate::define('mobilities.index', fn ($user) => ...)`, or a `Gate::before()` hook.
Guests get 401 (route middleware); users without the ability get 403.

## Tests

```bash
cd packages/academic-mobility && composer install && vendor/bin/phpunit
```

Orchestra Testbench boots a Laravel app with the provider; the tests use fake participants, so no host
models are needed.
