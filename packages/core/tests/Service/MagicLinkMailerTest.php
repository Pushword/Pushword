<?php

namespace Pushword\Core\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\LoginTokenRepository;
use Pushword\Core\Service\Email\NotificationEmailSender;
use Pushword\Core\Service\MagicLinkMailer;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Group('integration')]
final class MagicLinkMailerTest extends KernelTestCase
{
    public function testLinksUseConfiguredLiveOriginInsteadOfTheRequestHost(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = 'host-safe-link-'.uniqid().'@example.tld';

        $entityManager->persist($user);
        $entityManager->flush();

        $transport = new class implements TransportInterface {
            /** @var list<RawMessage> */
            public array $messages = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
            {
                $this->messages[] = $message;

                return null;
            }

            public function __toString(): string
            {
                return 'capture://';
            }
        };

        $apps = self::getContainer()->get(SiteRegistry::class);
        $router = self::getContainer()->get('router');
        $router->getContext()->setHost('attacker.example');
        $emailSender = new NotificationEmailSender(
            new Mailer($transport),
            $apps,
            self::getContainer()->get(Environment::class),
            null,
        );
        $mailer = new MagicLinkMailer(
            $emailSender,
            $entityManager,
            self::getContainer()->get(LoginTokenRepository::class),
            $router,
            self::getContainer()->get(TranslatorInterface::class),
            $apps,
        );

        try {
            $mailer->sendMagicLink($user);
            self::assertCount(1, $transport->messages);
            $message = $transport->messages[0];
            self::assertInstanceOf(TemplatedEmail::class, $message);
            $context = $message->getContext();

            foreach (['loginUrl', 'setPasswordUrl'] as $key) {
                self::assertIsString($context[$key]);
                self::assertStringStartsWith('https://localhost.dev/login/', $context[$key]);
                self::assertStringNotContainsString('attacker.example', $context[$key]);
            }
        } finally {
            $entityManager->remove($user);
            $entityManager->flush();
        }
    }
}
