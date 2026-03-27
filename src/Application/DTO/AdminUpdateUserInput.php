<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminUpdateUserInput
{
    #[Assert\When(
        expression: 'this.email !== null',
        constraints: [new Assert\Email()],
    )]
    public ?string $email = null;

    #[Assert\When(
        expression: 'this.password !== null',
        constraints: [new Assert\Length(min: 8, max: 255)],
    )]
    public ?string $password = null;

    /** @var list<string>|null */
    #[Assert\All([
        new Assert\Type('string'),
    ])]
    public ?array $roles = null;

    #[Assert\Type('bool')]
    public ?bool $isVerified = null;
}
