<?php

namespace App\Enums;

use App\Http\Requests\Users\UserIndexRequest;

/**
 * The relations of a user the list can include (`with[]=roles`): validated by
 * {@see UserIndexRequest} and loaded by `UserBuilder`.
 */
enum UserRelation: string
{
    case Roles = 'roles';
}
