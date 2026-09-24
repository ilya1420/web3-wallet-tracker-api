<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\SocialAccount;
use App\Domain\Entity\User;
use App\Domain\Exception\SocialAccountLinkRequiredException;
use App\Domain\Entity\NotificationChannel;
use App\Domain\Repository\NotificationChannelRepositoryInterface;
use App\Domain\Repository\SocialAccountRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\SocialAuthProfile;

final readonly class SocialUserResolver
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SocialAccountRepositoryInterface $socialAccountRepository,
        private NotificationChannelRepositoryInterface $notificationChannelRepository,
    ) {
    }

    public function resolve(SocialAuthProfile $profile): User
    {
        $provider = $profile->normalizedProvider();
        $providerUserId = trim($profile->providerUserId);

        $socialAccount = $this->socialAccountRepository->findOneByProviderSubject($provider, $providerUserId);
        if ($socialAccount !== null) {
            $socialAccount->refresh($profile);
            $this->upsertNotificationChannel($socialAccount->user(), $profile);

            return $socialAccount->user();
        }

        $email = $profile->normalizedEmail();
        if ($email !== null && $this->userRepository->findByEmail($email) !== null) {
            throw new SocialAccountLinkRequiredException();
        }

        $user = new User(
            $email !== null ? new Email($email) : null,
            'social-auth-only:' . hash('sha256', $provider . ':' . $providerUserId),
            ['ROLE_USER'],
            $profile->displayName,
        );

        $this->userRepository->save($user, false);
        $this->link($user, $profile);

        return $user;
    }

    public function link(User $user, SocialAuthProfile $profile): void
    {
        $existingSocialAccount = $this->socialAccountRepository->findOneByProviderSubject(
            $profile->normalizedProvider(),
            trim($profile->providerUserId),
        );

        if ($existingSocialAccount !== null && $existingSocialAccount->user()->id()->toRfc4122() !== $user->id()->toRfc4122()) {
            throw new SocialAccountLinkRequiredException();
        }

        if ($existingSocialAccount === null) {
            $this->socialAccountRepository->save(new SocialAccount($user, $profile), false);
        } else {
            $existingSocialAccount->refresh($profile);
        }

        if ($user->email() === null && $profile->normalizedEmail() !== null) {
            $user->changeEmail(new Email($profile->normalizedEmail()));
        }

        if ($user->displayName() === null && $profile->displayName !== null) {
            $user->changeDisplayName($profile->displayName);
        }

        $this->upsertNotificationChannel($user, $profile);
    }

    private function upsertNotificationChannel(User $user, SocialAuthProfile $profile): void
    {
        $type = null;
        $destination = null;

        if ($profile->normalizedEmail() !== null) {
            $type = 'email';
            $destination = $profile->normalizedEmail();
        }

        if ($profile->normalizedProvider() === 'telegram') {
            $type = 'telegram';
            $destination = trim($profile->providerUserId);
        }

        if ($type === null || $destination === null) {
            return;
        }

        $channel = $this->notificationChannelRepository->findOneByTypeAndDestination($type, $destination)
            ?? new NotificationChannel($user, $type, $destination, true, true);
        $channel->verify(true);

        $this->notificationChannelRepository->save($channel, false);
    }
}
