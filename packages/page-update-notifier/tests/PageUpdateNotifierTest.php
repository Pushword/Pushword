<?php

namespace Pushword\PageUpdateNotifier\Tests;

use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Error;
use Nette\Utils\FileSystem;
use PHPUnit\Framework\Attributes\Group;
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

#[Group('integration')]
final class PageUpdateNotifierTest extends KernelTestCase
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
        $page->slug = 'page-updater';
        $page->title = 'Just created';
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

        // Pages left by earlier tests would trip the notifier with their recent
        // createdAt/updatedAt. Backdate them instead of deleting them: deleting
        // cascades the translation join rows away (and drops parent/variant/image
        // links), and no restore from saved scalars can rebuild that — every later
        // test in this worker reading homepage translations would fail.
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $pageRepo = $em->getRepository(Page::class);
        $pages = $pageRepo->findByHost('localhost.dev');

        /** @var array<array{page: Page, createdAt: ?DateTimeInterface, updatedAt: ?DateTimeInterface}> $savedTimestamps */
        $savedTimestamps = [];
        foreach ($pages as $page) {
            $savedTimestamps[] = [
                'page' => $page,
                'createdAt' => $page->createdAt,
                'updatedAt' => $page->updatedAt,
            ];
            $page->skipAutoTimestamp = true;
            $page->createdAt = new DateTime('-2 months');
            $page->updatedAt = new DateTime('-2 months');
        }

        $em->flush();

        $this->removePageIfExists($em, 'page-updater', 'localhost.dev');

        FileSystem::delete($notifier->getCacheDir());
        self::assertSame(NotificationStatus::NothingToNotify, $notifier->run($this->getPage()));

        $em->persist($this->getPage());
        $em->flush();

        self::assertSame('Notification sent', $notifier->run($this->getPage()));

        self::assertSame(NotificationStatus::WasEverRunSinceInterval, $notifier->run($this->getPage()));

        // The page-updater page created above is not an original — drop it so it
        // doesn't leak into the page set seen by sibling tests in this worker
        // (e.g. the parallel static-generation comparison).
        $this->removePageIfExists($em, 'page-updater', 'localhost.dev');

        // Restore the original timestamps (skipAutoTimestamp is still set).
        foreach ($savedTimestamps as $saved) {
            $saved['page']->createdAt = $saved['createdAt'];
            $saved['page']->updatedAt = $saved['updatedAt'];
        }

        $em->flush();
    }

    /**
     * Must stay the last test of this class. testRun once restored the pages it
     * cleared by deleting and recreating them from a few saved scalars, which
     * cascaded the translation join rows away and silently dropped mainImage and
     * parentPage — poisoning every later class in the ParaTest worker (sitemap
     * hreflang, the link graph, pages_list). This pins the surviving invariant.
     */
    public function testTheFixturePagesSurviveThisClass(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $em->clear();

        $pageRepo = $em->getRepository(Page::class);

        $homepage = $pageRepo->findOneBy(['slug' => 'homepage', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $homepage);
        $locales = array_map(static fn (Page $page): string => $page->locale, $homepage->translations->toArray());
        self::assertContains('fr', $locales);
        self::assertContains('fr-CA', $locales);

        $kitchenSink = $pageRepo->findOneBy(['slug' => 'kitchen-sink', 'host' => 'localhost.dev']);
        self::assertInstanceOf(Page::class, $kitchenSink);
        self::assertNotNull($kitchenSink->parentPage, 'kitchen-sink must keep its fixture parent');
        self::assertNotNull($kitchenSink->getMainImage(), 'kitchen-sink must keep its fixture main image');
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
        $mock->method('buildViolation')->willReturnCallback(static function (string $arg) use ($mockConstraintViolationBuilder): MockObject {
            if (\in_array($arg, ['pageCustomPropertiesMalformed', 'pageCustomPropertiesNotStandAlone'], true)) {
                throw new Error();
            }

            return $mockConstraintViolationBuilder;
        });

        return $mock;
    }
}
