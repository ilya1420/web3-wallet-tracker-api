<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\Exception\MultiAccountingDetectedException;
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
            $ipKey = $this->ipCounterKey($ip);
            $ipCounterItem = $this->cache->getItem($ipKey);
            $current = $ipCounterItem->isHit() ? (int) $ipCounterItem->get() : 0;

            if ($current >= $this->maxRegistrationsPerIpPerDay) {
                throw new MultiAccountingDetectedException('Too many registrations from this IP address.');
            }
        }
    }

    public function markRegistered(string $normalizedEmail, string $deviceFingerprint, ?string $ip): void
    {
        $fingerprintItem = $this->cache->getItem($this->fingerprintKey($deviceFingerprint));
        $fingerprintItem->set($normalizedEmail);
        $fingerprintItem->expiresAfter(60 * 60 * 24 * 365);
        $this->cache->save($fingerprintItem);

        if ($ip !== null && $ip !== '') {
            $ipCounterItem = $this->cache->getItem($this->ipCounterKey($ip));
            $current = $ipCounterItem->isHit() ? (int) $ipCounterItem->get() : 0;
            $ipCounterItem->set($current + 1);
            $ipCounterItem->expiresAfter(60 * 60 * 24);
            $this->cache->save($ipCounterItem);
        }
    }

    private function fingerprintKey(string $deviceFingerprint): string
    {
        return 'registration_fingerprint_' . hash('sha256', trim($deviceFingerprint));
    }

    private function ipCounterKey(string $ip): string
    {
        return 'registration_ip_' . hash('sha256', trim($ip)) . '_' . (new \DateTimeImmutable())->format('Ymd');
    }
}
