<?php

namespace Module\AcademicMobility\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Module\AcademicMobility\Core\Models\Mobility;

/**
 * Serves the page only: it loads its data through the API.
 */
class MobilityController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', config('academic-mobility.models.mobility'));

        return Inertia::render('academic-mobility/mobilities/index');
    }

    public function create(): Response
    {
        Gate::authorize('create', config('academic-mobility.models.mobility'));

        return Inertia::render('academic-mobility/mobilities/create');
    }

    public function show(Mobility $mobility): Response
    {
        Gate::authorize('view', $mobility);

        return Inertia::render('academic-mobility/mobilities/show', ['mobilityId' => $mobility->id]);
    }
}
