<?php

namespace App\Http\Controllers\Web;

use App\Enums\RequestTypeKey;
use App\Http\Controllers\Controller;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Serves the request pages only. Pages receive just the identifier they need
 * (the type key or the request uuid) and load their data through the API.
 */
class RequestController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ServiceRequest::class);

        return Inertia::render('requests/index');
    }

    /**
     * Entry point: open the form of the first available type. The user can
     * switch type from the form itself.
     */
    public function create(): RedirectResponse
    {
        Gate::authorize('create', ServiceRequest::class);

        $first = RequestType::query()->where('is_active', true)->orderBy('id')->first();

        abort_if($first === null, 404);

        return to_route('requests.form', $first->key->value);
    }

    public function form(RequestTypeKey $type): Response
    {
        Gate::authorize('create', [ServiceRequest::class, $type]);

        return Inertia::render('requests/form', ['typeKey' => $type->value]);
    }

    public function edit(ServiceRequest $serviceRequest): Response
    {
        Gate::authorize('update', $serviceRequest);

        return Inertia::render('requests/form', ['requestUuid' => $serviceRequest->uuid]);
    }

    public function show(ServiceRequest $serviceRequest): Response
    {
        Gate::authorize('view', $serviceRequest);

        return Inertia::render('requests/show', ['requestUuid' => $serviceRequest->uuid]);
    }
}
