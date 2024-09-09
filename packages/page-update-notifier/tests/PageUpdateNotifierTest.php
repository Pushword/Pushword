<?php

namespace Pushword\PageUpdateNotifier\Tests;

use DateTime;
use Error;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\MockObject\MockObject;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\PageUpdateNotifier\PageUpdateNotifier;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class PageUpdateNotifierTest extends KernelTestCase
{
    protected function getNotifier(): PageUpdateNotifier
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $apps = $this->getApps();
        $translator = self::getContainer()->get('translator');
        $twig = self::getContainer()->get('twig');
        $mailer = new Mailer($this->getTransporter());

        return new PageUpdateNotifier(
            $mailer,
            $apps,
            sys_get_temp_dir(),
            $entityManager,
            $translator,
            $twig,
        );
    }

    protected function getApps(): AppPool
    {
        return self::getContainer()->get(AppPool::class);
    }

    protected function getPage(): Page
    {
        return (new Page())
            ->setSlug('page-updater')
            ->setTitle('Just created')
            ->setCreatedAt(new DateTime())
            ->setLocale('en')
            ->setHost('localhost.dev');
    }

    public function testRun(): void
    {
        $notifier = $this->getNotifier();
        $this->getApps()->get()->setCustomProperty('page_update_notification_from', 'contact@example.tld');
        $this->getApps()->get()->setCustomProperty('page_update_notification_to', 'contact@example.tld');
        $this->getApps()->get()->setCustomProperty('page_update_notification_interval', 'P1D');

        FileSystem::delete($notifier->getCacheDir());
        self::assertSame(PageUpdateNotifier::NOTHING_TO_NOTIFY, $notifier->run($this->getPage()));

        self::getContainer()->get('doctrine.orm.default_entity_manager')->persist($this->getPage());
        self::getContainer()->get('doctrine.orm.default_entity_manager')->flush();

        self::assertSame('Notification sent', $notifier->run($this->getPage()));

        self::assertSame(PageUpdateNotifier::WAS_EVER_RUN_SINCE_INTERVAL, $notifier->run($this->getPage()));

        return;
    }

    /**
     * @return AbstractTransport&MockObject
     */
    protected function getTransporter(): MockObject
    {
        $mock = $this->createMock(AbstractTransport::class);
        $mock->method('send')->willReturn(null);

        return $mock;
    }

    /** @return ExecutionContextInterface&MockObject */
    protected function getExceptionContextInterface(): MockObject
    {
        $mockConstraintViolationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $mockConstraintViolationBuilder->method('atPath')->willReturnSelf();
        $mockConstraintViolationBuilder->method('addViolation')->willReturnSelf();

        $mock = $this->createMock(ExecutionContextInterface::class);
        $mock->method('buildViolation')->willReturnCallback(static function ($arg) use ($mockConstraintViolationBuilder): MockObject {
            if (\in_array($arg, ['page.customProperties.malformed', 'page.customProperties.notStandAlone'], true)) {
                throw new Error();
            }

            return $mockConstraintViolationBuilder;
        });

        return $mock;
    }
}
