<?php

namespace App\Enums;

use App\Http\Requests\Requests\ServiceRequestIndexRequest;

/**
 * The relations of a service request the list can include (`with[]=type`): validated by
 * {@see ServiceRequestIndexRequest} and loaded by `ServiceRequestBuilder`.
 */
enum ServiceRequestRelation: string
{
    case Type = 'type';
    case Requester = 'requester';
}
