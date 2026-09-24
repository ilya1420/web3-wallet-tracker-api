<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Entity\SocialAccount;
use App\Domain\Entity\User;
use App\Domain\Entity\NotificationChannel;
use App\Domain\Exception\SocialAccountLinkRequiredException;
use App\Domain\Repository\NotificationChannelRepositoryInterface;
use App\Domain\Repository\SocialAccountRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Service\SocialUserResolver;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\SocialAuthProfile;
use PHPUnit\Framework\TestCase;

final class SocialUserResolverTest extends TestCase
{
    public function testReturnsExistingUserBySocialAccount(): void
    {
        $user = new User(new Email('alice@example.com'), 'hash');
        $socialAccount = new SocialAccount($user, new SocialAuthProfile('google_one_tap', 'subject', 'alice@example.com'));

        $resolver = new SocialUserResolver(
            new SocialResolverUserRepository(null),
            new SocialResolverSocialAccountRepository($socialAccount),
            new SocialResolverNotificationChannelRepository(),
        );

        self::assertSame($user, $resolver->resolve(new SocialAuthProfile('google_one_tap', 'subject', 'alice@example.com')));
    }

    public function testCreatesUserAndSocialAccountForNewEmailProfile(): void
    {
        $userRepository = new SocialResolverUserRepository(null);
        $socialAccountRepository = new SocialResolverSocialAccountRepository(null);
        $notificationChannelRepository = new SocialResolverNotificationChannelRepository();
        $resolver = new SocialUserResolver($userRepository, $socialAccountRepository, $notificationChannelRepository);

        $user = $resolver->resolve(new SocialAuthProfile('google_oauth2', 'subject', 'Alice@Example.com'));

        self::assertSame('alice@example.com', $user->email());
        self::assertSame($user, $userRepository->savedUser);
        self::assertNotNull($socialAccountRepository->savedSocialAccount);
        self::assertNotNull($notificationChannelRepository->savedChannel);
    }

    public function testCreatesUserWithoutEmailForProviderWithoutEmail(): void
    {
        $userRepository = new SocialResolverUserRepository(null);
        $socialAccountRepository = new SocialResolverSocialAccountRepository(null);
        $notificationChannelRepository = new SocialResolverNotificationChannelRepository();
        $resolver = new SocialUserResolver($userRepository, $socialAccountRepository, $notificationChannelRepository);

        $user = $resolver->resolve(new SocialAuthProfile('telegram', '123456'));

        self::assertNull($user->email());
        self::assertSame($user, $userRepository->savedUser);
        self::assertNotNull($socialAccountRepository->savedSocialAccount);
        self::assertNotNull($notificationChannelRepository->savedChannel);
    }

    public function testDoesNotAutoMergeByEmail(): void
    {
        $existingUser = new User(new Email('alice@example.com'), 'hash');
        $resolver = new SocialUserResolver(
            new SocialResolverUserRepository($existingUser),
            new SocialResolverSocialAccountRepository(null),
            new SocialResolverNotificationChannelRepository(),
        );

        $this->expectException(SocialAccountLinkRequiredException::class);

        $resolver->resolve(new SocialAuthProfile('google_oauth2', 'new-subject', 'alice@example.com'));
    }
}

final class SocialResolverUserRepository implements UserRepositoryInterface
{
    public ?User $savedUser = null;

    public function __construct(private readonly ?User $user)
    {
    }

    public function save(User $user, bool $flush = true): void
    {
        $this->savedUser = $user;
    }

    public function remove(User $user, bool $flush = true): void
    {
    }

    public function findByEmail(string $email): ?User
    {
        return $this->user !== null && $this->user->email() === mb_strtolower(trim($email)) ? $this->user : null;
    }

    public function findById(string $id): ?User
    {
        return null;
    }

    public function findAllUsers(): array
    {
        return [];
    }
}

final class SocialResolverNotificationChannelRepository implements NotificationChannelRepositoryInterface
{
    public ?NotificationChannel $savedChannel = null;

    public function save(NotificationChannel $channel, bool $flush = true): void
    {
        $this->savedChannel = $channel;
    }

    public function findOneByTypeAndDestination(string $type, string $destination): ?NotificationChannel
    {
        return null;
    }
}

final class SocialResolverSocialAccountRepository implements SocialAccountRepositoryInterface
{
    public ?SocialAccount $savedSocialAccount = null;

    public function __construct(private readonly ?SocialAccount $socialAccount)
    {
    }

    public function save(SocialAccount $socialAccount, bool $flush = true): void
    {
        $this->savedSocialAccount = $socialAccount;
    }

    public function findOneByProviderSubject(string $provider, string $providerUserId): ?SocialAccount
    {
        return $this->socialAccount;
    }
}
