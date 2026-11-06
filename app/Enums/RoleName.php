<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'superadmin';
    case Admin = 'admin';
    case Requester = 'requester';

    /**
     * @return array<int, PermissionName>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin, self::Admin => PermissionName::cases(),
            self::Requester => [PermissionName::CreateRequests],
        };
    }
}
