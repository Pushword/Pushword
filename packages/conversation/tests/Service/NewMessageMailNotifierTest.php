<?php

declare(strict_types=1);

namespace Pushword\Conversation\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Service\ImportContext;
use Pushword\Conversation\Service\NewMessageMailNotifier;
use Pushword\Core\Service\Email\NotificationEmailSender;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;

#[Group('integration')]
final class NewMessageMailNotifierTest extends KernelTestCase
{
    /** @return iterable<string, array{?string}> */
    public static function authorProvider(): iterable
    {
        yield 'named contact' => ['Camille <img src=x onerror=alert(1)> & Co'];
        yield 'unnamed contact' => [null];
    }

    #[DataProvider('authorProvider')]
    public function testDefaultsEscapeHtmlAndPreservePlainTextAndReplyTo(?string $authorName): void
    {
        self::bootKernel();
        $message = $this->createMessage();
        $message->authorName = $authorName;
        $message->setContent("Hello &amp; goodbye\n&lt;script&gt;alert(1)&lt;/script&gt; &quot;quoted&quot;");

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function (Email $email) use ($authorName): void {
            self::assertSame('[Forum & Contact] : Nouveau message', $email->getSubject());
            self::assertSame('visitor@example.org', $email->getReplyTo()[0]->getAddress());
            self::assertSame('notifications@example.org', $email->getFrom()[0]->getAddress());
            self::assertSame('team@example.org', $email->getTo()[0]->getAddress());

            $html = $email->getHtmlBody();
            self::assertIsString($html);
            self::assertStringContainsString('Hello &amp; goodbye<br', $html);
            self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
            self::assertStringNotContainsString('<script>', $html);
            self::assertStringNotContainsString('<img', $html);
            self::assertStringContainsString('form[contact_&lt;origin&gt;&amp;]', $html);
            self::assertStringContainsString('https://origin.example.org&lt;host&gt;/', $html);
            if (null !== $authorName) {
                self::assertStringContainsString('Camille &lt;img src=x onerror=alert(1)&gt; &amp; Co', $html);
            }

            self::assertSame(
                "Hello & goodbye\n<script>alert(1)</script> \"quoted\"\n\n---\nEnvoyé par ".($authorName ?? '...')." - visitor@example.org\nDepuis https://origin.example.org<host>/ › form[contact_<origin>&]\n",
                $email->getTextBody(),
            );
        });

        $notifier = $this->createNotifier($mailer);
        self::assertTrue($notifier->sendMessage($message));
        self::assertTrue($notifier->sendMessage($message));
    }

    public function testAllThreeTemplatesCanBeOverriddenWithTheFullContext(): void
    {
        self::bootKernel();
        $message = $this->createMessage();
        $message->authorName = "Camille\r\nBcc: injected@example.org\u{2028}End";
        $message->setContent('Stay &amp; &lt;script&gt;');
        $message->setCustomProperty('requestType', 'Quote');

        $twig = self::getContainer()->get(Environment::class);
        $twig->setLoader(new ChainLoader([
            new ArrayLoader([
                '@PushwordConversation/conversation/new_message.subject.txt.twig' => '{% autoescape false %}  {{ appName }}: {{ message.customProperty("requestType") }} / {{ authorName }}  {% endautoescape %}',
                '@PushwordConversation/conversation/new_message.html.twig' => '{{ content|nl2br }}|{{ authorEmail }}|{{ host }}|{{ referring }}|{{ message.customProperty("requestType") }}',
                '@PushwordConversation/conversation/new_message.txt.twig' => '{% autoescape false %}{{ content }}|{{ authorEmail }}|{{ host }}|{{ referring }}|{{ message.customProperty("requestType") }}{% endautoescape %}',
            ]),
            $twig->getLoader(),
        ]));

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(static function (Email $email): void {
            self::assertSame('Forum & Contact: Quote / Camille Bcc: injected@example.org End', $email->getSubject());
            self::assertSame('Stay &amp; &lt;script&gt;|visitor@example.org|origin.example.org&lt;host&gt;|contact_&lt;origin&gt;&amp;|Quote', $email->getHtmlBody());
            self::assertSame('Stay & <script>|visitor@example.org|origin.example.org<host>|contact_<origin>&|Quote', $email->getTextBody());
            self::assertSame('visitor@example.org', $email->getReplyTo()[0]->getAddress());
            self::assertFalse($email->getHeaders()->has('Bcc'));
        });

        $this->createNotifier($mailer)->sendMessage($message);
    }

    public function testDisabledMailerDoesNotRenderTemplates(): void
    {
        self::bootKernel();
        self::getContainer()->get(Environment::class)->setLoader(new ArrayLoader());

        self::assertFalse($this->createNotifier(null)->sendMessage($this->createMessage()));
    }

    private function createMessage(): Message
    {
        $message = new Message();
        $message->authorName = 'Camille';
        $message->authorEmail = 'visitor@example.org';
        $message->host = 'origin.example.org<host>';
        $message->referring = 'contact_<origin>&';

        return $message;
    }

    private function createNotifier(?MailerInterface $mailer): NewMessageMailNotifier
    {
        $apps = self::getContainer()->get(SiteRegistry::class);
        $site = $apps->get();
        $site->setCustomProperty('name', 'Forum & Contact');
        $site->setCustomProperty('conversation_notification_email_from', 'notifications@example.org');
        $site->setCustomProperty('conversation_notification_email_to', 'team@example.org');

        $twig = self::getContainer()->get(Environment::class);
        self::getContainer()->get('translator')->setLocale('fr');

        return new NewMessageMailNotifier(
            new NotificationEmailSender($mailer, $apps, $twig, null),
            $apps,
            self::getContainer()->getParameter('kernel.project_dir'),
            self::createStub(EntityManagerInterface::class),
            self::getContainer()->get(TranslatorInterface::class),
            new NullLogger(),
            self::createStub(Security::class),
            new ArrayAdapter(),
            new ImportContext(),
            $twig,
        );
    }
}
