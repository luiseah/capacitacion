<?php

namespace Module\AcademicMobility\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Module\AcademicMobility\Core\Models\Institution;
use Module\AcademicMobility\Facades\AcademicMobility;
use Module\AcademicMobility\Http\Requests\IndexInstitutionsRequest;
use Module\AcademicMobility\Http\Requests\StoreInstitutionRequest;
use Module\AcademicMobility\Http\Requests\UpdateInstitutionRequest;
use Module\AcademicMobility\Http\Resources\InstitutionResource;

class InstitutionController extends Controller
{
    public function index(IndexInstitutionsRequest $request): JsonResponse
    {
        $model = config('academic-mobility.models.institution');

        $institutions = $model::query()
            ->applyFilters($request)
            ->localFirst()
            ->paginate((int) config('academic-mobility.per_page', 15))
            ->withQueryString()
            ->through(fn ($institution) => (new InstitutionResource($institution))->resolve($request));

        return Response::api($institutions, __('Successful'));
    }

    public function show(Request $request, Institution $institution): JsonResponse
    {
        Gate::authorize('update', $institution);

        return Response::api((new InstitutionResource($institution))->resolve($request));
    }

    public function store(StoreInstitutionRequest $request): JsonResponse
    {
        $model = config('academic-mobility.models.institution');

        $institution = $model::create($request->safe()->only(['name', 'country']) + ['is_local' => false]);

        if ($request->boolean('is_local')) {
            $institution->makeLocal();
        }

        return Response::api((new InstitutionResource($institution))->resolve($request), __('Institution created'), status: 201);
    }

    public function update(UpdateInstitutionRequest $request, Institution $institution): JsonResponse
    {
        $institution->fill($request->safe()->only(['name', 'country']))->save();

        if ($request->boolean('is_local') && ! $institution->is_local) {
            $institution->makeLocal();
        }

        return Response::api((new InstitutionResource($institution->refresh()))->resolve($request), __('Institution updated'));
    }

    public function destroy(Institution $institution): JsonResponse
    {
        Gate::authorize('delete', $institution);

        $inUse = config('academic-mobility.models.mobility')::query()
            ->withTrashed()
            ->where(fn ($q) => $q->where('origin_institution_id', $institution->id)->orWhere('destination_institution_id', $institution->id))
            ->exists();

        if ($inUse) {
            return Response::error(__('This institution has mobilities and cannot be deleted.'), 409);
        }

        if ($institution->hasAgreements()) {
            return Response::error(__('This institution has agreements and cannot be deleted.'), 409);
        }

        if ($reason = AcademicMobility::institutionDeletionBlockedBy($institution)) {
            return Response::error($reason, 409);
        }

        $institution->delete();

        return Response::success(__('Institution deleted'));
    }
}
