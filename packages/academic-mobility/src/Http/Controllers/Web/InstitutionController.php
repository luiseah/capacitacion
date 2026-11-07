<?php

namespace Module\AcademicMobility\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Module\AcademicMobility\Core\Models\Institution;

/**
 * Serves the institution pages only; they load their data through the API.
 */
class InstitutionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', config('academic-mobility.models.institution'));

        return Inertia::render('academic-mobility/institutions/index');
    }

    public function create(): Response
    {
        Gate::authorize('create', config('academic-mobility.models.institution'));

        return Inertia::render('academic-mobility/institutions/form');
    }

    public function edit(Institution $institution): Response
    {
        Gate::authorize('update', $institution);

        return Inertia::render('academic-mobility/institutions/form', ['institutionId' => $institution->id]);
    }
}
