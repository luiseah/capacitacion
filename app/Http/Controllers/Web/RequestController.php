<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Enums\RequestPriority;
use App\Enums\RequestTypeKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requests\StoreServiceRequestRequest;
use App\Http\Resources\ServiceRequestDetailResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RequestController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ServiceRequest::class);

        $user = $request->user();

        $requests = ServiceRequest::query()
            ->with(['type', 'requester'])
            ->when(! $user->can(PermissionName::ViewAnyRequests->value), fn ($q) => $q->where('requester_id', $user->id))
            ->latest()
            ->paginate(15)
            ->through(fn (ServiceRequest $r) => (new ServiceRequestResource($r))->resolve());

        return Inertia::render('requests/index', [
            'requests' => $requests,
            'canCreate' => $user->can(PermissionName::CreateRequests->value),
        ]);
    }

    /**
     * Entry point: open the form of the first available type. The user can
     * switch type from the form itself.
     */
    public function create(): RedirectResponse
    {
        Gate::authorize('create', ServiceRequest::class);

        $first = $this->activeTypes()->first();

        abort_if($first === null, 404);

        return to_route('requests.form', $first['key']);
    }

    /**
     * Form of the type bound from the URL, with every available type so the
     * user can switch between them without leaving the page.
     */
    public function form(RequestTypeKey $type): Response
    {
        Gate::authorize('create', ServiceRequest::class);
        abort_unless($this->isActive($type), 404);

        return Inertia::render('requests/form', [
            'types' => $this->activeTypes(),
            'type' => ['key' => $type->value, 'name' => $type->label()],
            'fields' => array_map(fn ($field) => $field->toArray(), $type->strategy()->requestModel()::fields()),
            'priorities' => array_map(fn (RequestPriority $p) => $p->value, RequestPriority::cases()),
        ]);
    }

    public function store(StoreServiceRequestRequest $request, RequestTypeKey $type): RedirectResponse
    {
        abort_unless($this->isActive($type), 404);

        $submit = $request->validated('action') === 'submit';

        $serviceRequest = $type->strategy()->create(
            $request->user(),
            $request->safe()->only(['title', 'description', 'priority']),
            $request->metadata(),
            $submit,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $submit ? __('Request submitted.') : __('Request saved as draft.'),
        ]);

        return to_route('requests.show', $serviceRequest);
    }

    public function show(ServiceRequest $serviceRequest): Response
    {
        Gate::authorize('view', $serviceRequest);

        $serviceRequest->load(['type', 'requester']);

        return Inertia::render('requests/show', [
            'request' => (new ServiceRequestDetailResource($serviceRequest))->resolve(),
        ]);
    }

    private function isActive(RequestTypeKey $key): bool
    {
        return RequestType::query()->where('key', $key->value)->where('is_active', true)->exists();
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{key: string, name: string}>
     */
    private function activeTypes(): Collection
    {
        $active = RequestType::query()->where('is_active', true)->pluck('key')
            ->map(fn (RequestTypeKey $key) => $key->value)->all();

        return collect(RequestTypeKey::cases())
            ->filter(fn (RequestTypeKey $key) => in_array($key->value, $active, true))
            ->map(fn (RequestTypeKey $key) => ['key' => $key->value, 'name' => $key->label()])
            ->values();
    }
}
