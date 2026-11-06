<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RequestTypeKey;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequestTypeResource;
use App\Models\RequestType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class RequestTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = RequestType::query()->where('is_active', true)->orderBy('id')->get();

        return Response::api(RequestTypeResource::collection($types), __('Successful'));
    }

    public function show(RequestTypeKey $type): JsonResponse
    {
        $requestType = RequestType::query()->where('key', $type->value)->where('is_active', true)->firstOrFail();

        return Response::api(new RequestTypeResource($requestType), __('Successful'));
    }
}
