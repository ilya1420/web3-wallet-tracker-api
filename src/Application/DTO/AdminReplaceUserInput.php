<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminReplaceUserInput
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\Length(min: 8, max: 255)]
    public ?string $password = null;

    /** @var list<string> */
    #[Assert\Count(min: 1)]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\Regex('/^ROLE_[A-Z0-9_]+$/'),
    ])]
    public array $roles = ['ROLE_USER'];

    #[Assert\Type('bool')]
    public bool $isVerified = false;

    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public ?string $lastLoginAt = null;

    #[Assert\NotBlank]
    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public string $createdAt;

    #[Assert\Length(min: 16, max: 255)]
    public ?string $deviceFingerprint = null;

    #[Assert\Ip]
    public ?string $registrationIp = null;

    #[Assert\Date]
    public ?string $registrationIpCounterDate = null;
}
