<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * Owners and admins can manage settings, members and invitations.
     */
    public function canManageTeam(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Roles that can be given through an invitation (ownership is never invited).
     *
     * @return list<self>
     */
    public static function assignable(): array
    {
        return [self::Admin, self::Member];
    }
}
