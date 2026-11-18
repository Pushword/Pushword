<?php

declare(strict_types=1);

namespace Pushword\Core\Controller;

use DateTimeImmutable;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\LoginTokenRepository;
use Pushword\Core\Security\MagicLinkAuthenticator;
use Pushword\Core\Service\MagicLinkMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

final class MagicLoginController extends AbstractController
{
    public function __construct(
        private readonly LoginTokenRepository $tokens,
        private readonly MagicLinkMailer $mailer,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly MagicLinkAuthenticator $authenticator,
        #[Autowire(service: 'limiter.login_email')]
        private readonly RateLimiterFactory $ipLimiter,
        #[Autowire(service: 'limiter.login_address')]
        private readonly RateLimiterFactory $addressLimiter,
    ) {
    }

    #[Route('/login/magic/{token}', name: 'pushword_login_magic', methods: ['GET', 'POST'])]
    public function login(string $token, Request $request): Response
    {
        if ($request->isMethod('HEAD')) {
            return $this->response();
        }

        $loginToken = $this->findToken($token);
        if (! $loginToken instanceof LoginToken) {
            $this->addFlash('error', 'magicLinkInvalid');

            return $this->redirectToRoute('pushword_login');
        }

        $user = $this->getUser();
        if ($user instanceof User && $user->id === $loginToken->user->id) {
            return $this->redirectToRoute($user->requiresPasswordChange() ? 'pushword_login_change_password' : 'pushword_admin');
        }

        if ($loginToken->isRecoveryExpired()) {
            $this->addFlash('error', 'magicLinkExpiredOrUsed');

            return $this->redirectToRoute('pushword_login');
        }

        $error = null;
        $status = Response::HTTP_OK;
        if ($request->isMethod('POST')) {
            if (! $this->isCsrfTokenValid('magic_login', $request->request->getString('_csrf_token'))) {
                $error = 'securityLoginCsrfError';
            } else {
                $action = $request->request->getString('action', 'connect');
                if ('verify_code' === $action) {
                    if ($this->tokens->consumeLoginCode($loginToken, trim($request->request->getString('code')))) {
                        return $this->authenticate($loginToken, $request);
                    }

                    $error = $loginToken->hasLoginCode() ? 'magicLinkCodeInvalid' : 'magicLinkCodeUnavailable';
                } elseif ('request_code' === $action && ! $loginToken->isValid() && 0 === $this->resendAfter($loginToken)) {
                    if (! $this->canSendCode($request, $loginToken->user)) {
                        $error = 'magicLinkCodeRateLimited';
                        $status = Response::HTTP_TOO_MANY_REQUESTS;
                    } elseif (! $this->mailer->sendLoginCode($loginToken)) {
                        $error = 'magicLinkCodeSendFailed';
                    }
                } elseif ('connect' === $action && $this->tokens->consumeLoginLink($loginToken)) {
                    return $this->authenticate($loginToken, $request);
                }
            }
        }

        $connecting = $loginToken->isValid();

        return $this->render('@Pushword/user/magic_login.html.twig', [
            'connecting' => $connecting,
            'autoConnect' => null === $error && $connecting,
            'hasCode' => $loginToken->hasLoginCode(),
            'resendAfter' => $this->resendAfter($loginToken),
            'error' => $error,
        ], $this->response($status));
    }

    private function findToken(string $encoded): ?LoginToken
    {
        $decoded = base64_decode($encoded, true);
        if (false === $decoded || ! str_contains($decoded, ':')) {
            return null;
        }

        [$userId, $plainToken] = explode(':', $decoded, 2);

        return $this->tokens->findOneBy([
            'user' => (int) $userId,
            'tokenHash' => hash('sha256', $plainToken),
            'type' => LoginToken::TYPE_LOGIN,
        ]);
    }

    private function canSendCode(Request $request, User $user): bool
    {
        $ipAccepted = $this->ipLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted();
        $addressAccepted = $this->addressLimiter->create(hash('sha256', strtolower(trim($user->email))))->consume()->isAccepted();

        return $ipAccepted && $addressAccepted;
    }

    private function resendAfter(LoginToken $token): int
    {
        return $token->loginCodeExpiresAt instanceof DateTimeImmutable ? max(
            0,
            $token->loginCodeExpiresAt->getTimestamp() - time() - LoginToken::CODE_TTL_SECONDS + LoginToken::CODE_RESEND_SECONDS,
        ) : 0;
    }

    private function authenticate(LoginToken $token, Request $request): Response
    {
        $request->attributes->set(LoginToken::class, $token);

        $response = $this->userAuthenticator->authenticateUser($token->user, $this->authenticator, $request)
            ?? $this->redirectToRoute('pushword_admin');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    private function response(int $status = Response::HTTP_OK): Response
    {
        return new Response('', $status, ['Cache-Control' => 'private, no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
