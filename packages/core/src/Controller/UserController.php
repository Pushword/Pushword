<?php

namespace Pushword\Core\Controller;

use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use LogicException;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\LoginTokenRepository;
use Pushword\Core\Repository\UserRepository;
use Pushword\Core\Security\MagicLinkAuthenticator;
use Pushword\Core\Service\MagicLinkMailer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;

final class UserController extends AbstractController
{
    public function __construct(
        private readonly AuthenticationUtils $authenticationUtils,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly MagicLinkMailer $magicLinkMailer,
        private readonly LoginTokenRepository $tokenRepo,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly MagicLinkAuthenticator $authenticator,
        #[Autowire(service: 'limiter.login_email')]
        private readonly RateLimiterFactory $loginEmailLimiter,
        #[Autowire(service: 'limiter.login_address')]
        private readonly RateLimiterFactory $loginAddressLimiter,
        private readonly bool $enablePasswordReset = false,
    ) {
    }

    #[Route('/login', name: 'pushword_login')]
    public function login(Request $request): Response
    {
        if (($user = $this->getUser()) instanceof User) {
            if ($user->requiresPasswordChange()) {
                return $this->redirectToRoute('pushword_login_change_password');
            }

            return $this->redirectToRoute('pushword_admin');
        }

        $authenticationException = $this->authenticationUtils->getLastAuthenticationError();
        $lastUsername = $this->authenticationUtils->getLastUsername();

        // Check if we're in step 2 (password entry)
        $step = $request->query->getString('step', 'email');
        if ('password' === $step && '' !== $lastUsername) {
            return $this->render('@Pushword/user/login.html.twig', [
                'last_username' => $lastUsername,
                'error' => $authenticationException,
                'step' => 'password',
                'enable_password_reset' => $this->enablePasswordReset,
            ]);
        }

        // Check for OAuth error from session
        $oauthError = $request->getSession()->get('oauth_error');
        if (null !== $oauthError) {
            $request->getSession()->remove('oauth_error');
            $this->addFlash('error', $oauthError);
        }

        return $this->render('@Pushword/user/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $authenticationException,
            'step' => 'email',
            'enable_password_reset' => $this->enablePasswordReset,
        ]);
    }

    #[Route('/login/check-email', name: 'pushword_login_check_email', methods: ['POST'])]
    public function checkEmail(Request $request): RedirectResponse
    {
        $email = $request->request->getString('email');
        $csrfToken = $request->request->getString('_csrf_token');

        if (! $this->isCsrfTokenValid('authenticate', $csrfToken)) {
            $this->addFlash('error', 'securityLoginCsrfError');

            return $this->redirectToRoute('pushword_login');
        }

        $this->consumeEmailLimit($request, $email);

        // Keep the response identical for known and unknown addresses. Existing
        // users receive a magic link in parallel, without disclosing existence.
        $request->getSession()->set('_security.last_username', $email);

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            $this->magicLinkMailer->sendMagicLink($user);
        }

        return $this->redirectToRoute('pushword_login', ['step' => 'password']);
    }

    #[Route('/login/magic/{token}', name: 'pushword_login_magic')]
    public function magicLogin(
        string $token,
        Request $request,
    ): Response {
        $decoded = base64_decode($token, true);
        if (false === $decoded || ! str_contains($decoded, ':')) {
            $this->addFlash('error', 'magicLinkInvalid');

            return $this->redirectToRoute('pushword_login');
        }

        [$userId, $plainToken] = explode(':', $decoded, 2);
        $user = $this->userRepository->find((int) $userId);

        if (! $user instanceof User) {
            $this->addFlash('error', 'magicLinkInvalid');

            return $this->redirectToRoute('pushword_login');
        }

        $tokenHash = hash('sha256', $plainToken);
        $loginToken = $this->tokenRepo->findValidToken($user, $tokenHash, LoginToken::TYPE_LOGIN);

        if (! $loginToken instanceof LoginToken) {
            $this->addFlash('error', 'magicLinkExpiredOrUsed');

            return $this->redirectToRoute('pushword_login');
        }

        $loginToken->markUsed();
        $this->em->flush();

        return $this->userAuthenticator->authenticateUser($user, $this->authenticator, $request)
            ?? $this->redirectToRoute('pushword_admin');
    }

    #[Route('/login/set-password/{token}', name: 'pushword_login_set_password', methods: ['GET', 'POST'])]
    public function setPassword(
        string $token,
        Request $request,
    ): Response {
        $decoded = base64_decode($token, true);
        if (false === $decoded || ! str_contains($decoded, ':')) {
            $this->addFlash('error', 'magicLinkInvalid');

            return $this->redirectToRoute('pushword_login');
        }

        [$userId, $plainToken] = explode(':', $decoded, 2);
        $user = $this->userRepository->find((int) $userId);

        if (! $user instanceof User) {
            $this->addFlash('error', 'magicLinkInvalid');

            return $this->redirectToRoute('pushword_login');
        }

        $tokenHash = hash('sha256', $plainToken);
        $loginToken = $this->tokenRepo->findValidToken($user, $tokenHash, LoginToken::TYPE_SET_PASSWORD)
            ?? $this->tokenRepo->findValidToken($user, $tokenHash, LoginToken::TYPE_PASSWORD_RESET);

        if (! $loginToken instanceof LoginToken) {
            $this->addFlash('error', 'magicLinkExpiredOrUsed');

            return $this->redirectToRoute('pushword_login');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->getString('password');
            $passwordConfirm = $request->request->getString('password_confirm');

            if ($password !== $passwordConfirm) {
                return $this->render('@Pushword/user/set_password.html.twig', [
                    'token' => $token,
                    'error' => 'passwordsMustMatch',
                ]);
            }

            if (\strlen($password) < 7) {
                return $this->render('@Pushword/user/set_password.html.twig', [
                    'token' => $token,
                    'error' => 'userPasswordShort',
                ]);
            }

            // Set password (UserListener will hash it on flush)
            $user->setPlainPassword($password);
            $user->completePasswordChange();
            $loginToken->markUsed();
            $this->em->flush();

            return $this->userAuthenticator->authenticateUser($user, $this->authenticator, $request)
                ?? $this->redirectToRoute('pushword_admin');
        }

        return $this->render('@Pushword/user/set_password.html.twig', [
            'token' => $token,
            'error' => null,
        ]);
    }

    #[Route('/login/change-password', name: 'pushword_login_change_password', methods: ['GET', 'POST'])]
    public function changePassword(Request $request): Response
    {
        $user = $this->getUser();
        if (! $user instanceof User) {
            return $this->redirectToRoute('pushword_login');
        }

        if (! $user->requiresPasswordChange()) {
            return $this->redirectToRoute('pushword_admin');
        }

        $error = null;
        if ($request->isMethod('POST')) {
            if (! $this->isCsrfTokenValid('change_password', $request->request->getString('_csrf_token'))) {
                $error = 'securityLoginCsrfError';
            } else {
                $password = $request->request->getString('password');
                $passwordConfirm = $request->request->getString('password_confirm');

                if ($password !== $passwordConfirm) {
                    $error = 'passwordsMustMatch';
                } elseif (\strlen($password) < 7) {
                    $error = 'userPasswordShort';
                } else {
                    $user->setPlainPassword($password);
                    $user->completePasswordChange();
                    $this->em->flush();

                    return $this->userAuthenticator->authenticateUser($user, $this->authenticator, $request)
                        ?? $this->redirectToRoute('pushword_admin');
                }
            }
        }

        return $this->render('@Pushword/user/set_password.html.twig', [
            'csrf_intention' => 'change_password',
            'error' => $error,
            'password_change_required' => true,
        ]);
    }

    #[Route('/login/forgot-password', name: 'pushword_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if (! $this->enablePasswordReset) {
            return $this->redirectToRoute('pushword_login');
        }

        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->getString('_csrf_token');

            if (! $this->isCsrfTokenValid('forgot_password', $csrfToken)) {
                $this->addFlash('error', 'securityLoginCsrfError');

                return $this->redirectToRoute('pushword_forgot_password');
            }

            $email = $request->request->getString('email');
            $this->consumeEmailLimit($request, $email);
            $user = $this->userRepository->findOneBy(['email' => $email]);

            if ($user instanceof User) {
                $this->magicLinkMailer->sendPasswordResetLink($user);
            }

            return $this->render('@Pushword/user/forgot_password.html.twig', [
                'email_sent' => true,
            ]);
        }

        return $this->render('@Pushword/user/forgot_password.html.twig', [
            'email_sent' => false,
        ]);
    }

    #[Route('/login/oauth/{provider}', name: 'pushword_oauth_start')]
    public function oauthStart(string $provider): Response
    {
        if (! class_exists(ClientRegistry::class)) {
            return $this->redirectToRoute('pushword_login');
        }

        /** @var ClientRegistry $clientRegistry */
        $clientRegistry = $this->container->get('knpu.oauth2.registry');

        // Default scopes - providers may override via their config
        return $clientRegistry->getClient($provider)->redirect(['email'], []);
    }

    #[Route('/login/oauth/{provider}/check', name: 'pushword_oauth_check')]
    public function oauthCheck(string $provider): RedirectResponse
    {
        // This is handled by OAuthAuthenticator
        return $this->redirectToRoute('pushword_admin');
    }

    #[Route('/logout', name: 'pushword_logout', methods: ['GET', 'HEAD', 'POST'])]
    public function logout(): never
    {
        throw new LogicException('This method can be blank');
    }

    private function consumeEmailLimit(Request $request, string $email): void
    {
        $limiters = [
            $this->loginEmailLimiter->create($request->getClientIp() ?? 'unknown'),
            $this->loginAddressLimiter->create(hash('sha256', strtolower(trim($email)))),
        ];

        foreach ($limiters as $limiter) {
            $limit = $limiter->consume();
            if ($limit->isAccepted()) {
                continue;
            }

            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            throw new TooManyRequestsHttpException($retryAfter, 'Too many login attempts. Please try again later.');
        }
    }
}
