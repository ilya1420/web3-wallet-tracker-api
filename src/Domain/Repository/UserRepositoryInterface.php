<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;

interface UserRepositoryInterface
{
    public function save(User $user): void;

    public function findByEmail(string $email): ?User;

    public function findById(string $id): ?User;

    /** @return list<User> */
    public function findAllUsers(): array;
}
