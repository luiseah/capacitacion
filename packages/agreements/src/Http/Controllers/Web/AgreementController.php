<?php

namespace Module\Agreements\Http\Controllers\Web;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Module\Agreements\Models\Agreement;

/**
 * Serves the agreement pages only; they load their data through the API.
 */
class AgreementController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', config('agreements.models.agreement'));

        return Inertia::render('agreements/index');
    }

    public function create(): Response
    {
        Gate::authorize('create', config('agreements.models.agreement'));

        return Inertia::render('agreements/form');
    }

    public function edit(Agreement $agreement): Response
    {
        Gate::authorize('update', $agreement);

        return Inertia::render('agreements/form', ['agreementId' => $agreement->id]);
    }
}
