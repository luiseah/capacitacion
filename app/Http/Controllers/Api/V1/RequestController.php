<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionName;
use App\Enums\RequestTypeKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requests\StoreServiceRequestRequest;
use App\Http\Resources\ServiceRequestDetailResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\RequestType;
use App\Models\ServiceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;

class RequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ServiceRequest::class);

        $user = $request->user();

        $requests = ServiceRequest::query()
            ->with(['type', 'requester'])
            ->when(! $user->can(PermissionName::ViewAnyRequests->value), fn ($q) => $q->where('requester_id', $user->id))
            ->latest()
            ->paginate(15)
            ->through(fn (ServiceRequest $r) => (new ServiceRequestResource($r))->resolve());

        return Response::api($requests, __('Successful'));
    }

    public function store(StoreServiceRequestRequest $request, RequestTypeKey $type): JsonResponse
    {
        abort_unless(
            RequestType::query()->where('key', $type->value)->where('is_active', true)->exists(),
            404,
        );

        $serviceRequest = $type->strategy()->create(
            $request->user(),
            $request->safe()->only(['title', 'description', 'priority']),
            $request->metadata(),
            $request->validated('action') === 'submit',
        );

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
}
