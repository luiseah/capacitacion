<?php

namespace Module\AcademicMobility\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Module\AcademicMobility\Core\Models\Agreement;

/**
 * Serves the agreement pages only; they load their data through the API.
 */
class AgreementController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', config('academic-mobility.models.agreement'));

        return Inertia::render('academic-mobility/agreements/index');
    }

    public function create(): Response
    {
        Gate::authorize('create', config('academic-mobility.models.agreement'));

        return Inertia::render('academic-mobility/agreements/form');
    }

    public function edit(Agreement $agreement): Response
    {
        Gate::authorize('update', $agreement);

        return Inertia::render('academic-mobility/agreements/form', ['agreementId' => $agreement->id]);
    }
}
