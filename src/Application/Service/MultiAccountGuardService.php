<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Exception\MultiAccountingDetectedException;
use App\Domain\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Entity\UserRegistrationContext;
use App\Infrastructure\Persistence\Doctrine\Repository\RegistrationIpCounterRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\UserRegistrationContextRepository;

final readonly class MultiAccountGuardService
{
    public function __construct(
        private UserRegistrationContextRepository $registrationContextRepository,
        private RegistrationIpCounterRepository $registrationIpCounterRepository,
        private int $maxRegistrationsPerIpPerDay,
    ) {
    }

    public function assertCanRegister(string $normalizedEmail, string $deviceFingerprint, ?string $ip): void
    {
        $fingerprintHash = $this->hashValue($deviceFingerprint);
        if ($this->registrationContextRepository->existsByDeviceFingerprintHash($fingerprintHash)) {
            throw new MultiAccountingDetectedException('Multi-accounting is not allowed for this device.');
        }

        $ipHash = $this->hashNullable($ip);
        if ($ipHash === null) {
            return;
        }

        $counterDate = new \DateTimeImmutable('today');
        $current = $this->registrationIpCounterRepository->currentCount($ipHash, $counterDate);
        if ($current >= $this->maxRegistrationsPerIpPerDay) {
            throw new MultiAccountingDetectedException('Too many registrations from this IP address.');
        }
    }

    public function upsertRegistrationContext(
        User $user,
        ?string $deviceFingerprint,
        ?string $registrationIp,
        ?\DateTimeImmutable $counterDate,
        bool $flush = true,
    ): void {
        $this->upsertRegistrationContextHashes(
            $user,
            $this->hashNullable($deviceFingerprint),
            $this->hashNullable($registrationIp),
            $counterDate,
            $flush,
        );
    }

    public function upsertRegistrationContextHashes(
        User $user,
        ?string $deviceFingerprintHash,
        ?string $registrationIpHash,
        ?\DateTimeImmutable $counterDate,
        bool $flush = true,
    ): void {
        $context = $this->registrationContextRepository->findOneByUser($user) ?? new UserRegistrationContext($user);
        $previousIpHash = $context->registrationIpHash();
        $previousCounterDate = $context->registrationIpCounterDate();

        $shouldReleasePreviousIpSlot = $previousIpHash !== null
            && $previousCounterDate !== null
            && ($previousIpHash !== $registrationIpHash || !$this->sameDate($previousCounterDate, $counterDate));

        if ($shouldReleasePreviousIpSlot) {
            $this->registrationIpCounterRepository->releaseSlot($previousIpHash, $previousCounterDate);
        }

        $shouldReserveCurrentIpSlot = $registrationIpHash !== null
            && $counterDate !== null
            && ($previousIpHash !== $registrationIpHash || !$this->sameDate($previousCounterDate, $counterDate));

        if ($shouldReserveCurrentIpSlot) {
            $isReserved = $this->registrationIpCounterRepository->reserveSlot(
                $registrationIpHash,
                $counterDate,
                $this->maxRegistrationsPerIpPerDay,
            );

            if (!$isReserved) {
                throw new MultiAccountingDetectedException('Too many registrations from this IP address.');
            }
        }

        $context->update($deviceFingerprintHash, $registrationIpHash, $counterDate);
        $this->registrationContextRepository->save($context, $flush);
    }

    public function clearUserRegistrationContext(User $user, bool $flush = true): void
    {
        $context = $this->registrationContextRepository->findOneByUser($user);
        if ($context === null) {
            return;
        }

        if ($context->registrationIpHash() !== null && $context->registrationIpCounterDate() !== null) {
            $this->registrationIpCounterRepository->releaseSlot(
                $context->registrationIpHash(),
                $context->registrationIpCounterDate(),
            );
        }

        $this->registrationContextRepository->remove($context, $flush);
    }

    public function findContext(User $user): ?UserRegistrationContext
    {
        return $this->registrationContextRepository->findOneByUser($user);
    }

    public function hashDeviceFingerprint(?string $deviceFingerprint): ?string
    {
        return $this->hashNullable($deviceFingerprint);
    }

    public function hashRegistrationIp(?string $registrationIp): ?string
    {
        return $this->hashNullable($registrationIp);
    }

    private function hashNullable(?string $value): ?string
    {
        $normalized = $this->normalizeNullable($value);

        return $normalized === null ? null : $this->hashValue($normalized);
    }

    private function hashValue(string $value): string
    {
        return hash('sha256', trim($value));
    }

    private function normalizeNullable(?string $value): ?string
    {
        $normalized = $value !== null ? trim($value) : null;

        return $normalized === '' ? null : $normalized;
    }

    private function sameDate(?\DateTimeImmutable $left, ?\DateTimeImmutable $right): bool
    {
        return $left?->format('Y-m-d') === $right?->format('Y-m-d');
    }
}
