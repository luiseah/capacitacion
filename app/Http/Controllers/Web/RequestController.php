<?php

namespace App\Http\Controllers\Web;

use App\Enums\PermissionName;
use App\Enums\RequestPriority;
use App\Enums\RequestTypeKey;
use App\Http\Controllers\Controller;
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
        Gate::authorize('create', [ServiceRequest::class, $type]);

        return Inertia::render('requests/form', [
            'types' => $this->activeTypes(),
            'type' => ['key' => $type->value, 'name' => $type->label()],
            'fields' => array_map(fn ($field) => $field->toArray(), $type->strategy()->requestModel()::fields()),
            'priorities' => array_map(fn (RequestPriority $p) => $p->value, RequestPriority::cases()),
        ]);
    }

    public function store(RequestTypeKey $type): RedirectResponse
    {
        Gate::authorize('create', [ServiceRequest::class, $type]);

        $strategy = $type->strategy();

        // Resolving the type's form request authorizes and validates the input.
        $request = app($strategy->formRequest());

        $serviceRequest = $strategy->create($request);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $request->shouldSubmit() ? __('Request submitted.') : __('Request saved as draft.'),
        ]);

        return to_route('requests.show', $serviceRequest);
    }

    public function edit(ServiceRequest $serviceRequest): Response
    {
        Gate::authorize('update', $serviceRequest);

        $type = $serviceRequest->type->key;

        return Inertia::render('requests/form', [
            'types' => [],
            'type' => ['key' => $type->value, 'name' => $type->label()],
            'fields' => array_map(fn ($field) => $field->toArray(), $type->strategy()->requestModel()::fields()),
            'priorities' => array_map(fn (RequestPriority $p) => $p->value, RequestPriority::cases()),
            'request' => [
                'uuid' => $serviceRequest->uuid,
                'title' => $serviceRequest->title,
                'description' => $serviceRequest->description,
                'priority' => $serviceRequest->priority->value,
                'status' => $serviceRequest->status->value,
                'metadata' => $serviceRequest->metadata ?? [],
            ],
        ]);
    }

    public function update(ServiceRequest $serviceRequest): RedirectResponse
    {
        Gate::authorize('update', $serviceRequest);

        $strategy = $serviceRequest->type->key->strategy();

        // Resolving the type's form request authorizes and validates the input.
        $form = app($strategy->formRequest());

        $strategy->update($serviceRequest, $form);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $form->shouldSubmit() ? __('Request submitted.') : __('Request updated.'),
        ]);

        return to_route('requests.show', $serviceRequest);
    }

    public function destroy(ServiceRequest $serviceRequest): RedirectResponse
    {
        Gate::authorize('delete', $serviceRequest);

        $serviceRequest->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Request deleted.')]);

        return to_route('requests.index');
    }

    public function show(ServiceRequest $serviceRequest): Response
    {
        Gate::authorize('view', $serviceRequest);

        $serviceRequest->load(['type', 'requester']);

        return Inertia::render('requests/show', [
            'request' => (new ServiceRequestDetailResource($serviceRequest))->resolve(),
            'canEdit' => request()->user()->can('update', $serviceRequest),
            'canDelete' => request()->user()->can('delete', $serviceRequest),
        ]);
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
