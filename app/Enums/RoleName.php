<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case Requester = 'requester';

    /**
     * @return array<int, PermissionName>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => PermissionName::cases(),
            self::Requester => [PermissionName::CreateRequests],
        };
    }
}
