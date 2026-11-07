<?php

namespace Module\AcademicMobility\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Module\AcademicMobility\Core\Models\Requirement;

/**
 * Serves the requirement pages only; they load their data through the API.
 */
class RequirementController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', config('academic-mobility.models.requirement'));

        return Inertia::render('academic-mobility/requirements/index');
    }

    public function create(): Response
    {
        Gate::authorize('create', config('academic-mobility.models.requirement'));

        return Inertia::render('academic-mobility/requirements/form');
    }

    public function edit(Requirement $requirement): Response
    {
        Gate::authorize('update', $requirement);

        return Inertia::render('academic-mobility/requirements/form', ['requirementId' => $requirement->id]);
    }
}
