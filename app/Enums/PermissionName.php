<?php

namespace App\Enums;

/**
 * Permissions of the requests resource, named after its routes. They apply to
 * every request; owners can always see their own requests and edit or delete
 * them while they are drafts, without needing any of these.
 */
enum PermissionName: string
{
    /** See the list with everyone's requests (without it: only your own). */
    case IndexRequests = 'requests.index';

    /** View any request. */
    case ShowRequests = 'requests.show';

    case CreateRequests = 'requests.create';

    /** Edit any request, in any state. */
    case UpdateRequests = 'requests.update';

    /** Delete any request, in any state. */
    case DestroyRequests = 'requests.destroy';
}
