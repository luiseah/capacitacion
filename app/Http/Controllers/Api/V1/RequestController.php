<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RequestTypeKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requests\ServiceRequestIndexRequest;
use App\Http\Resources\ServiceRequestDetailResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;

class RequestController extends Controller
{
    public function index(ServiceRequestIndexRequest $request): JsonResponse
    {
        $requests = ServiceRequest::query()
            ->visibleTo($request->user())
            ->applyFilters($request)
            ->paginated(15)
            ->through(fn (ServiceRequest $r) => (new ServiceRequestResource($r))->resolve());

        return Response::api($requests, __('Successful'));
    }

    public function store(RequestTypeKey $type): JsonResponse
    {
        Gate::authorize('create', [ServiceRequest::class, $type]);

        $strategy = $type->strategy();

        // Resolving the type's form request authorizes and validates the input.
        $serviceRequest = $strategy->create(app($strategy->formRequest()));

        return Response::api(
            new ServiceRequestDetailResource($serviceRequest->load(['type', 'requester'])),
            __('Request created'),
            status: 201,
        );
    }

    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        Gate::authorize('view', $serviceRequest);

        return Response::api(
            new ServiceRequestDetailResource($serviceRequest->load(['type', 'requester'])),
            __('Successful'),
        );
    }

    public function update(ServiceRequest $serviceRequest): JsonResponse
    {
        Gate::authorize('update', $serviceRequest);

        $strategy = $serviceRequest->type->key->strategy();

        // Resolving the type's form request authorizes and validates the input.
        $strategy->update($serviceRequest, app($strategy->formRequest()));

        return Response::api(
            new ServiceRequestDetailResource($serviceRequest->load(['type', 'requester'])),
            __('Request updated'),
        );
    }

    public function destroy(ServiceRequest $serviceRequest): JsonResponse
    {
        Gate::authorize('delete', $serviceRequest);

        $serviceRequest->delete();

        return Response::success(__('Request deleted'));
    }
}
