<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Exception\MultiAccountingDetectedException;
use App\Domain\Entity\User;
use Psr\Cache\CacheItemPoolInterface;

final readonly class MultiAccountGuardService
{
    public function __construct(
        private CacheItemPoolInterface $cache,
        private int $maxRegistrationsPerIpPerDay,
    ) {
    }

    public function assertCanRegister(string $normalizedEmail, string $deviceFingerprint, ?string $ip): void
    {
        $fingerprintKey = $this->fingerprintKey($deviceFingerprint);
        $fingerprintItem = $this->cache->getItem($fingerprintKey);

        if ($fingerprintItem->isHit()) {
            $existingEmail = (string) $fingerprintItem->get();
            if ($existingEmail !== $normalizedEmail) {
                throw new MultiAccountingDetectedException('Multi-accounting is not allowed for this device.');
            }
        }

        if ($ip !== null && $ip !== '') {
            $ipKey = $this->ipCounterKey($ip, (new \DateTimeImmutable())->format('Y-m-d'));
            $ipCounterItem = $this->cache->getItem($ipKey);
            $current = $ipCounterItem->isHit() ? (int) $ipCounterItem->get() : 0;

            if ($current >= $this->maxRegistrationsPerIpPerDay) {
                throw new MultiAccountingDetectedException('Too many registrations from this IP address.');
            }
        }
    }

    /**
     * @param array{email:string,deviceFingerprint:?string,registrationIp:?string,registrationIpCounterDate:?string}|null $previousState
     */
    public function syncUserRegistrationContext(User $user, ?array $previousState = null): void
    {
        $currentState = $this->extractState($user);

        if ($previousState !== null) {
            $shouldRefreshFingerprint = $previousState['deviceFingerprint'] !== $currentState['deviceFingerprint']
                || $previousState['email'] !== $currentState['email'];

            if ($shouldRefreshFingerprint && $previousState['deviceFingerprint'] !== null) {
                $this->cache->deleteItem($this->fingerprintKey($previousState['deviceFingerprint']));
            }

            $shouldRebalanceIpCounter = $previousState['registrationIp'] !== $currentState['registrationIp']
                || $previousState['registrationIpCounterDate'] !== $currentState['registrationIpCounterDate'];

            if ($shouldRebalanceIpCounter) {
                $this->decrementIpCounter($previousState['registrationIp'], $previousState['registrationIpCounterDate']);
            }
        }

        if ($currentState['deviceFingerprint'] !== null) {
            $fingerprintItem = $this->cache->getItem($this->fingerprintKey($currentState['deviceFingerprint']));
            $fingerprintItem->set($currentState['email']);
            $fingerprintItem->expiresAfter(60 * 60 * 24 * 365);
            $this->cache->save($fingerprintItem);
        }

        if ($previousState !== null) {
            $shouldIncrementCurrentIpCounter = $previousState['registrationIp'] !== $currentState['registrationIp']
                || $previousState['registrationIpCounterDate'] !== $currentState['registrationIpCounterDate'];

            if ($shouldIncrementCurrentIpCounter) {
                $this->incrementIpCounter($currentState['registrationIp'], $currentState['registrationIpCounterDate']);
            }

            return;
        }

        $this->incrementIpCounter($currentState['registrationIp'], $currentState['registrationIpCounterDate']);
    }

    public function clearUserRegistrationContext(User $user): void
    {
        $state = $this->extractState($user);

        if ($state['deviceFingerprint'] !== null) {
            $this->cache->deleteItem($this->fingerprintKey($state['deviceFingerprint']));
        }

        $this->decrementIpCounter($state['registrationIp'], $state['registrationIpCounterDate']);
    }

    /**
     * @return array{email:string,deviceFingerprint:?string,registrationIp:?string,registrationIpCounterDate:?string}
     */
    public function extractState(User $user): array
    {
        return [
            'email' => $user->email(),
            'deviceFingerprint' => $this->normalizeNullable($user->deviceFingerprint()),
            'registrationIp' => $this->normalizeNullable($user->registrationIp()),
            'registrationIpCounterDate' => $this->normalizeNullable($user->registrationIpCounterDate()),
        ];
    }

    private function fingerprintKey(string $deviceFingerprint): string
    {
        return 'registration_fingerprint_' . hash('sha256', trim($deviceFingerprint));
    }

    private function ipCounterKey(string $ip, string $date): string
    {
        return 'registration_ip_' . hash('sha256', trim($ip)) . '_' . str_replace('-', '', trim($date));
    }

    private function incrementIpCounter(?string $ip, ?string $date): void
    {
        $normalizedIp = $this->normalizeNullable($ip);
        $normalizedDate = $this->normalizeNullable($date);

        if ($normalizedIp === null || $normalizedDate === null) {
            return;
        }

        $ipCounterItem = $this->cache->getItem($this->ipCounterKey($normalizedIp, $normalizedDate));
        $current = $ipCounterItem->isHit() ? (int) $ipCounterItem->get() : 0;
        $ipCounterItem->set($current + 1);
        $ipCounterItem->expiresAfter(60 * 60 * 24);
        $this->cache->save($ipCounterItem);
    }

    private function decrementIpCounter(?string $ip, ?string $date): void
    {
        $normalizedIp = $this->normalizeNullable($ip);
        $normalizedDate = $this->normalizeNullable($date);

        if ($normalizedIp === null || $normalizedDate === null) {
            return;
        }

        $key = $this->ipCounterKey($normalizedIp, $normalizedDate);
        $ipCounterItem = $this->cache->getItem($key);

        if (!$ipCounterItem->isHit()) {
            return;
        }

        $current = max(0, (int) $ipCounterItem->get() - 1);
        if ($current === 0) {
            $this->cache->deleteItem($key);

            return;
        }

        $ipCounterItem->set($current);
        $ipCounterItem->expiresAfter(60 * 60 * 24);
        $this->cache->save($ipCounterItem);
    }

    private function normalizeNullable(?string $value): ?string
    {
        $normalized = $value !== null ? trim($value) : null;

        return $normalized === '' ? null : $normalized;
    }
}
