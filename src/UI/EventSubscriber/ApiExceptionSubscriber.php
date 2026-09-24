<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use App\Application\Exception\InvalidCredentialsException;
use App\Application\Exception\CurrencyConversionException;
use App\Application\Exception\InvalidLoginTokenException;
use App\Application\Exception\MultiAccountingDetectedException;
use App\Application\Exception\RateLimitExceededException;
use App\Application\Exception\SocialAuthException;
use App\Application\Exception\UserAlreadyExistsException;
use App\Application\Exception\UserNotFoundException;
use App\Application\Exception\Web3ProviderException;
use App\Application\Exception\Web3WalletAlreadyExistsException;
use App\Application\Exception\Web3WalletNotFoundException;
use App\Domain\Exception\SocialAccountLinkRequiredException;
use App\UI\Exception\DtoValidationException;
use App\UI\Exception\UnexpectedInputTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 50],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof HttpException) {
            return;
        }

        $mapped = match (true) {
            $throwable instanceof RateLimitExceededException => new TooManyRequestsHttpException(null, $throwable->getMessage(), $throwable),
            $throwable instanceof InvalidLoginTokenException, $throwable instanceof InvalidCredentialsException, $throwable instanceof SocialAuthException => new UnauthorizedHttpException('Bearer', $throwable->getMessage(), $throwable),
            $throwable instanceof UserAlreadyExistsException, $throwable instanceof Web3WalletAlreadyExistsException, $throwable instanceof SocialAccountLinkRequiredException => new ConflictHttpException($throwable->getMessage(), $throwable),
            $throwable instanceof Web3WalletNotFoundException, $throwable instanceof UserNotFoundException => new NotFoundHttpException($throwable->getMessage(), $throwable),
            $throwable instanceof MultiAccountingDetectedException, $throwable instanceof DtoValidationException, $throwable instanceof \InvalidArgumentException => new UnprocessableEntityHttpException($throwable->getMessage(), $throwable),
            $throwable instanceof UnexpectedInputTypeException => new HttpException(Response::HTTP_BAD_REQUEST, $throwable->getMessage(), $throwable),
            $throwable instanceof Web3ProviderException => new HttpException(Response::HTTP_BAD_GATEWAY, $throwable->getMessage(), $throwable),
            default => null,
        };

        if ($mapped !== null) {
            $event->setThrowable($mapped);
        }
    }
}
