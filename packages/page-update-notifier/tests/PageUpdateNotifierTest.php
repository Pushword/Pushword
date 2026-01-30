<?php

namespace Pushword\PageUpdateNotifier\Tests;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Error;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\Email\NotificationEmailSender;
use Pushword\Core\Site\SiteRegistry;
use Pushword\PageUpdateNotifier\NotificationStatus;
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
        $emailSender = new NotificationEmailSender(
            $mailer,
            $apps,
            $twig,
            null,
        );

        return new PageUpdateNotifier(
            $emailSender,
            $apps,
            sys_get_temp_dir(),
            $entityManager,
            $translator,
        );
    }

    protected function getApps(): SiteRegistry
    {
        return self::getContainer()->get(SiteRegistry::class);
    }

    protected function getPage(): Page
    {
        $page = new Page();
        $page->setSlug('page-updater');
        $page->setTitle('Just created');
        $page->createdAt = new DateTime();
        $page->locale = 'en';
        $page->host = 'localhost.dev';

        return $page;
    }

    public function testRun(): void
    {
        $notifier = $this->getNotifier();
        $this->getApps()->get()->setCustomProperty('page_update_notification_from', 'contact@example.tld');
        $this->getApps()->get()->setCustomProperty('page_update_notification_to', 'contact@example.tld');
        $this->getApps()->get()->setCustomProperty('page_update_notification_interval', 'P1D');

        // Clear pages from previous tests that may have recent createdAt timestamps
        // But save their data first so we can restore them after the test
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);
        $pages = $pageRepo->findByHost('localhost.dev');

        /** @var array<array{slug: string, h1: string, mainContent: string, locale: string, publishedAt: ?DateTimeInterface, createdAt: ?DateTimeInterface, updatedAt: ?DateTimeInterface}> $savedPagesData */
        $savedPagesData = [];
        foreach ($pages as $page) {
            $savedPagesData[] = [
                'slug' => $page->getSlug(),
                'h1' => $page->getH1(),
                'mainContent' => $page->getMainContent(),
                'locale' => $page->locale,
                'publishedAt' => $page->getPublishedAt(),
                'createdAt' => $page->createdAt,
                'updatedAt' => $page->updatedAt,
            ];
            $em->remove($page);
        }

        $em->flush();

        $this->removePageIfExists($em, 'page-updater', 'localhost.dev');

        FileSystem::delete($notifier->getCacheDir());
        self::assertSame(NotificationStatus::NothingToNotify, $notifier->run($this->getPage()));

        self::getContainer()->get('doctrine.orm.default_entity_manager')->persist($this->getPage());
        self::getContainer()->get('doctrine.orm.default_entity_manager')->flush();

        self::assertSame('Notification sent', $notifier->run($this->getPage()));

        self::assertSame(NotificationStatus::WasEverRunSinceInterval, $notifier->run($this->getPage()));

        // Restore original pages for other tests
        foreach ($savedPagesData as $pageData) {
            $this->removePageIfExists($em, $pageData['slug'], 'localhost.dev');

            $restoredPage = new Page();
            $restoredPage->setSlug($pageData['slug']);
            $restoredPage->setH1($pageData['h1']);
            $restoredPage->setMainContent($pageData['mainContent']);
            $restoredPage->locale = $pageData['locale'];
            $restoredPage->host = 'localhost.dev';
            $restoredPage->createdAt = $pageData['createdAt'];
            $restoredPage->updatedAt = $pageData['updatedAt'];

            if (null !== $pageData['publishedAt']) {
                $restoredPage->setPublishedAt($pageData['publishedAt']);
            }

            $em->persist($restoredPage);
        }

        $em->flush();
    }

    private function removePageIfExists(EntityManagerInterface $em, string $slug, string $host): void
    {
        $existingPage = $em->getRepository(Page::class)->findOneBy([
            'slug' => $slug,
            'host' => $host,
        ]);
        if (null !== $existingPage) {
            $em->remove($existingPage);
            $em->flush();
        }
    }

    /**
     * @return AbstractTransport&Stub
     */
    protected function getTransporter(): Stub
    {
        $stub = self::createStub(AbstractTransport::class);
        $stub->method('send')->willReturn(null);

        return $stub;
    }

    /** @return ExecutionContextInterface&MockObject */
    protected function getExceptionContextInterface(): MockObject
    {
        $mockConstraintViolationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $mockConstraintViolationBuilder->method('atPath')->willReturnSelf();
        $mockConstraintViolationBuilder->method('addViolation')->willReturnSelf();

        $mock = $this->createMock(ExecutionContextInterface::class);
        $mock->method('buildViolation')->willReturnCallback(static function ($arg) use ($mockConstraintViolationBuilder): MockObject {
            if (\in_array($arg, ['pageCustomPropertiesMalformed', 'page.customProperties.notStandAlone'], true)) {
                throw new Error();
            }

            return $mockConstraintViolationBuilder;
        });

        return $mock;
    }
}
