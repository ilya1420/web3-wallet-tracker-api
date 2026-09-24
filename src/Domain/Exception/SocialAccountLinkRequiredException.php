<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class SocialAccountLinkRequiredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Sign in to the existing account before linking this social account.');
    }
}
