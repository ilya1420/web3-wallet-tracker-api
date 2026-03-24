<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            ['email' => 'admin@example.com', 'password' => 'AdminPass123!', 'roles' => ['ROLE_ADMIN']],
            ['email' => 'alice@example.com', 'password' => 'UserPass123!', 'roles' => ['ROLE_USER']],
            ['email' => 'bob@example.com', 'password' => 'UserPass123!', 'roles' => ['ROLE_USER']],
            ['email' => 'carol@example.com', 'password' => 'UserPass123!', 'roles' => ['ROLE_USER']],
            ['email' => 'dave@example.com', 'password' => 'UserPass123!', 'roles' => ['ROLE_USER']],
        ];

        foreach ($users as $item) {
            $user = new User(new Email($item['email']), 'placeholder_hash', $item['roles']);
            $user->changePassword($this->passwordHasher->hashPassword($user, $item['password']));
            $manager->persist($user);
        }

        $manager->flush();
    }
}
