<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Exception\MultiAccountingDetectedException;
use App\Domain\Entity\User;
use App\Infrastructure\Persistence\Doctrine\Entity\UserRegistrationContext;
use App\Infrastructure\Persistence\Doctrine\Repository\UserRegistrationContextRepository;
use Psr\Cache\CacheItemPoolInterface;

final readonly class MultiAccountGuardService
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private UserRegistrationContextRepository $registrationContextRepository,
        private int $maxRegistrationsPerIpPerDay,
    ) {
    }

    public function assertCanRegister(string $normalizedEmail, string $deviceFingerprint, ?string $ip): void
    {
        $fingerprintHash = $this->hashValue($deviceFingerprint);
        $fingerprintItem = $this->cache->getItem($this->fingerprintKey($fingerprintHash));

        if ($fingerprintItem->isHit()) {
            $existingEmail = (string) $fingerprintItem->get();
            if ($existingEmail !== $normalizedEmail) {
                throw new MultiAccountingDetectedException('Multi-accounting is not allowed for this device.');
            }
        }

        $ipHash = $this->hashNullable($ip);
        if ($ipHash !== null) {
            $counterDate = new \DateTimeImmutable('today');
            $ipCounterItem = $this->cache->getItem($this->ipCounterKey($ipHash, $counterDate));
            $current = $ipCounterItem->isHit() ? (int) $ipCounterItem->get() : 0;

            if ($current >= $this->maxRegistrationsPerIpPerDay) {
                throw new MultiAccountingDetectedException('Too many registrations from this IP address.');
            }
        }
    }

    public function upsertRegistrationContext(User $user, ?string $deviceFingerprint, ?string $registrationIp, ?\DateTimeImmutable $counterDate): void
    {
        $this->upsertRegistrationContextHashes(
            $user,
            $this->hashNullable($deviceFingerprint),
            $this->hashNullable($registrationIp),
            $counterDate,
        );
    }

    public function upsertRegistrationContextHashes(
        User $user,
        ?string $deviceFingerprintHash,
        ?string $registrationIpHash,
        ?\DateTimeImmutable $counterDate,
    ): void
    {
        $context = $this->registrationContextRepository->findOneByUser($user) ?? new UserRegistrationContext($user);
        $previousFingerprintHash = $context->deviceFingerprintHash();
        $previousIpHash = $context->registrationIpHash();
        $previousCounterDate = $context->registrationIpCounterDate();

        $context->update($deviceFingerprintHash, $registrationIpHash, $counterDate);
        $this->registrationContextRepository->save($context);

        $this->syncFingerprintCache($previousFingerprintHash, $deviceFingerprintHash, $user->email());
        $this->syncIpCounterCache($previousIpHash, $previousCounterDate, $registrationIpHash, $counterDate);
    }

    public function clearUserRegistrationContext(User $user): void
    {
        $context = $this->registrationContextRepository->findOneByUser($user);
        if ($context === null) {
            return;
        }

        if ($context->deviceFingerprintHash() !== null) {
            $this->cache->deleteItem($this->fingerprintKey($context->deviceFingerprintHash()));
        }

        $this->decrementIpCounter($context->registrationIpHash(), $context->registrationIpCounterDate());
        $this->registrationContextRepository->remove($context);
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

    private function syncFingerprintCache(?string $previousHash, ?string $currentHash, string $email): void
    {
        if ($previousHash !== null && $previousHash !== $currentHash) {
            $this->cache->deleteItem($this->fingerprintKey($previousHash));
        }

        if ($currentHash === null) {
            return;
        }

        $fingerprintItem = $this->cache->getItem($this->fingerprintKey($currentHash));
        $fingerprintItem->set($email);
        $fingerprintItem->expiresAfter(60 * 60 * 24 * 365);
        $this->cache->save($fingerprintItem);
    }

    private function syncIpCounterCache(
        ?string $previousIpHash,
        ?\DateTimeImmutable $previousCounterDate,
        ?string $currentIpHash,
        ?\DateTimeImmutable $currentCounterDate,
    ): void {
        $shouldDecrementPrevious = $previousIpHash !== $currentIpHash
            || !$this->sameDate($previousCounterDate, $currentCounterDate);

        if ($shouldDecrementPrevious) {
            $this->decrementIpCounter($previousIpHash, $previousCounterDate);
        }

        $shouldIncrementCurrent = $currentIpHash !== null
            && $currentCounterDate !== null
            && ($previousIpHash !== $currentIpHash || !$this->sameDate($previousCounterDate, $currentCounterDate));

        if ($shouldIncrementCurrent) {
            $this->incrementIpCounter($currentIpHash, $currentCounterDate);
        }
    }

    private function incrementIpCounter(?string $ipHash, ?\DateTimeImmutable $date): void
    {
        if ($ipHash === null || $date === null) {
            return;
        }

        $item = $this->cache->getItem($this->ipCounterKey($ipHash, $date));
        $current = $item->isHit() ? (int) $item->get() : 0;
        $item->set($current + 1);
        $item->expiresAfter(60 * 60 * 24);
        $this->cache->save($item);
    }

    private function decrementIpCounter(?string $ipHash, ?\DateTimeImmutable $date): void
    {
        if ($ipHash === null || $date === null) {
            return;
        }

        $key = $this->ipCounterKey($ipHash, $date);
        $item = $this->cache->getItem($key);
        if (!$item->isHit()) {
            return;
        }

        $current = max(0, (int) $item->get() - 1);
        if ($current === 0) {
            $this->cache->deleteItem($key);

            return;
        }

        $item->set($current);
        $item->expiresAfter(60 * 60 * 24);
        $this->cache->save($item);
    }

    private function fingerprintKey(string $fingerprintHash): string
    {
        return 'registration_fingerprint_' . $fingerprintHash;
    }

    private function ipCounterKey(string $ipHash, \DateTimeImmutable $date): string
    {
        return 'registration_ip_' . $ipHash . '_' . $date->format('Ymd');
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
