<?php

namespace Module\Agreements\Http\Controllers\Api;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Module\Agreements\Http\Requests\AgreementDocumentRequest;
use Module\Agreements\Http\Requests\AgreementRequest;
use Module\Agreements\Http\Requests\IndexAgreementsRequest;
use Module\Agreements\Http\Requests\SearchAgreementsRequest;
use Module\Agreements\Http\Resources\AgreementResource;
use Module\Agreements\Models\Agreement;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The agreements (convenios) of the partner institutions with the local one.
 */
class AgreementController extends Controller
{
    public function index(IndexAgreementsRequest $request): JsonResponse
    {
        $agreements = config('agreements.models.agreement')::query()
            ->applyFilters($request)
            ->paginated((int) config('agreements.per_page', 15))
            ->through(fn ($agreement) => (new AgreementResource($agreement))->resolve($request));

        return Response::api($agreements, __('Successful'));
    }

    /**
     * The agreements of an institution with the local one, for the mobility pages: all of them (the latest
     * first), so it is clear which are in force. Whoever can create mobilities (the form) or see them can ask.
     */
    public function ofInstitution(SearchAgreementsRequest $request): JsonResponse
    {
        $agreements = config('agreements.models.agreement')::query()
            ->with(['framework', 'institution', 'institutions'])
            ->forInstitution($request->string('institution_id')->toString())
            ->latestFirst()
            ->get();

        return Response::api(AgreementResource::collection($agreements)->resolve($request));
    }

    public function show(Request $request, Agreement $agreement): JsonResponse
    {
        Gate::authorize('update', $agreement);

        return Response::api((new AgreementResource($agreement->load(['institution', 'institutions', 'framework'])))->resolve($request));
    }

    public function store(AgreementRequest $request): JsonResponse
    {
        $agreement = DB::transaction(function () use ($request) {
            $agreement = config('agreements.models.agreement')::create($request->attributesToStore());

            return $agreement->syncInstitutions($request->otherInstitutions());
        });

        return Response::api((new AgreementResource($agreement->load(['institution', 'institutions', 'framework'])))->resolve($request), __('Agreement created'), status: 201);
    }

    public function update(AgreementRequest $request, Agreement $agreement): JsonResponse
    {
        DB::transaction(function () use ($request, $agreement) {
            $agreement->update($request->attributesToStore());
            $agreement->syncInstitutions($request->otherInstitutions());
        });

        return Response::api((new AgreementResource($agreement->refresh()->load(['institution', 'institutions', 'framework'])))->resolve($request), __('Agreement updated'));
    }

    /**
     * The signed document of the agreement (a PDF), for whoever can see the agreements.
     */
    public function document(Agreement $agreement): StreamedResponse
    {
        Gate::authorize('viewAny', config('agreements.models.agreement'));

        abort_unless($agreement->hasDocument() && $this->disk()->exists($agreement->document_path), 404, __('This agreement has no document.'));

        return $this->disk()->response($agreement->document_path, $agreement->document_name, ['Content-Type' => 'application/pdf']);
    }

    /**
     * Keeps the signed document of the agreement, replacing the one it had.
     */
    public function storeDocument(AgreementDocumentRequest $request, Agreement $agreement): JsonResponse
    {
        $file = $request->file('document');
        $path = $this->disk()->putFileAs('agreements', $file, "{$agreement->id}.pdf");

        $agreement->update(['document_path' => $path, 'document_name' => $file->getClientOriginalName()]);

        return Response::api((new AgreementResource($agreement->load(['institution', 'institutions', 'framework'])))->resolve($request), __('Document uploaded'));
    }

    public function destroyDocument(Agreement $agreement): JsonResponse
    {
        Gate::authorize('update', $agreement);

        if ($agreement->hasDocument()) {
            $this->disk()->delete($agreement->document_path);
            $agreement->update(['document_path' => null, 'document_name' => null]);
        }

        return Response::success(__('Document removed'));
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('agreements.documents.disk'));
    }

    public function destroy(Agreement $agreement): JsonResponse
    {
        Gate::authorize('delete', $agreement);

        // A framework agreement leaves its specific ones standing, without a framework.
        $agreement->delete();

        return Response::success(__('Agreement deleted'));
    }
}
