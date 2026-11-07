<?php

namespace Module\AcademicMobility\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Module\AcademicMobility\Core\Models\Requirement;
use Module\AcademicMobility\Http\Requests\IndexRequirementsRequest;
use Module\AcademicMobility\Http\Requests\RequirementRequest;
use Module\AcademicMobility\Http\Resources\RequirementResource;

/**
 * The catalog of requirements: what each kind of mobility (direction, scope, participant kind) brings.
 */
class RequirementController extends Controller
{
    public function index(IndexRequirementsRequest $request): JsonResponse
    {
        $requirements = config('academic-mobility.models.requirement')::query()
            ->applyFilters($request)
            ->ordered()
            ->paginate((int) config('academic-mobility.per_page', 15))
            ->withQueryString()
            ->through(fn ($requirement) => (new RequirementResource($requirement))->resolve($request));

        return Response::api($requirements, __('Successful'));
    }

    public function show(Request $request, Requirement $requirement): JsonResponse
    {
        Gate::authorize('update', $requirement);

        return Response::api((new RequirementResource($requirement))->resolve($request));
    }

    public function store(RequirementRequest $request): JsonResponse
    {
        $requirement = config('academic-mobility.models.requirement')::create($request->attributesToStore())->refresh();

        return Response::api((new RequirementResource($requirement))->resolve($request), __('Requirement created'), status: 201);
    }

    public function update(RequirementRequest $request, Requirement $requirement): JsonResponse
    {
        $requirement->update($request->attributesToStore());

        return Response::api((new RequirementResource($requirement->refresh()))->resolve($request), __('Requirement updated'));
    }

    /**
     * Requests that already exist keep their own copy, so deleting from the catalog is always safe.
     */
    public function destroy(Requirement $requirement): JsonResponse
    {
        Gate::authorize('delete', $requirement);

        $requirement->delete();

        return Response::success(__('Requirement deleted'));
    }
}
