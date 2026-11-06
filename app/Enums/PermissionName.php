<?php

namespace App\Enums;

enum PermissionName: string
{
    case CreateRequests = 'requests.create';
    case ViewAnyRequests = 'requests.view-any';
    case ManageRequests = 'requests.manage';
}
