<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\AdminUserOutput;
use App\Application\DTO\MeOutput;
use App\Application\DTO\UserOutput;
use App\Domain\Entity\User;

final class UserOutputMapper
{
    public function toUserOutput(User $user): UserOutput
    {
        return new UserOutput(
            id: $user->id()->toRfc4122(),
            email: $user->email(),
            isVerified: $user->isVerified(),
            lastLoginAt: $user->lastLoginAt()?->format(DATE_ATOM),
            createdAt: $user->createdAt()->format(DATE_ATOM),
        );
    }

    public function toMeOutput(User $user): MeOutput
    {
        return new MeOutput(
            id: $user->id()->toRfc4122(),
            email: $user->email(),
            roles: $user->getRoles(),
            isVerified: $user->isVerified(),
            lastLoginAt: $user->lastLoginAt()?->format(DATE_ATOM),
            createdAt: $user->createdAt()->format(DATE_ATOM),
        );
    }

    public function toAdminUserOutput(User $user): AdminUserOutput
    {
        return new AdminUserOutput(
            id: $user->id()->toRfc4122(),
            email: $user->email(),
            roles: $user->getRoles(),
            isVerified: $user->isVerified(),
            lastLoginAt: $user->lastLoginAt()?->format(DATE_ATOM),
            createdAt: $user->createdAt()->format(DATE_ATOM),
            deviceFingerprint: $user->deviceFingerprint(),
            registrationIp: $user->registrationIp(),
            registrationIpCounterDate: $user->registrationIpCounterDate(),
        );
    }
}
