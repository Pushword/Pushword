<?php

namespace Pushword\Core\Service;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\LoginToken;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\LoginTokenRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class MagicLinkMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $em,
        private LoginTokenRepository $tokenRepo,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private AppPool $apps,
    ) {
    }

    public function sendMagicLink(User $user): void
    {
        $this->tokenRepo->invalidateUserTokens($user, LoginToken::TYPE_LOGIN);
        $this->tokenRepo->invalidateUserTokens($user, LoginToken::TYPE_SET_PASSWORD);

        $loginPlainToken = bin2hex(random_bytes(32));
        $setPasswordPlainToken = bin2hex(random_bytes(32));

        $loginToken = new LoginToken($user, LoginToken::TYPE_LOGIN);
        $loginToken->setToken($loginPlainToken);

        $this->em->persist($loginToken);

        $setPasswordToken = new LoginToken($user, LoginToken::TYPE_SET_PASSWORD);
        $setPasswordToken->setToken($setPasswordPlainToken);

        $this->em->persist($setPasswordToken);

        $this->em->flush();

        $loginUrlToken = base64_encode($user->getId().':'.$loginPlainToken);
        $setPasswordUrlToken = base64_encode($user->getId().':'.$setPasswordPlainToken);

        $loginUrl = $this->urlGenerator->generate(
            'pushword_login_magic',
            ['token' => $loginUrlToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $setPasswordUrl = $this->urlGenerator->generate(
            'pushword_login_set_password',
            ['token' => $setPasswordUrlToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $appName = $this->apps->get()->getStr('name') ?: $this->apps->get()->getMainHost();
        $emailFrom = $this->apps->get()->getStr('conversation_notification_email_from')
            ?: 'noreply@'.$this->apps->get()->getMainHost();

        $email = new TemplatedEmail()
            ->from($emailFrom)
            ->to($user->email)
            ->subject($this->translator->trans('magicLinkEmailSubject', ['%appName%' => $appName]))
            ->htmlTemplate('@Pushword/user/email/magic_link.html.twig')
            ->context([
                'user' => $user,
                'loginUrl' => $loginUrl,
                'setPasswordUrl' => $setPasswordUrl,
                'appName' => $appName,
                'expiresInMinutes' => LoginToken::TTL_SECONDS / 60,
            ]);

        $this->mailer->send($email);
    }
}
