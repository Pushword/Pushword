<?php

namespace Pushword\Core\Tests\EventListener;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\EventListener\PageListener;
use Pushword\Core\Service\PageOpenGraphImageGenerator;
use Pushword\Core\Service\TailwindGenerator;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Guards the fix for the flat-import 25GB RSS leak: the per-page Open Graph image
 * generation (Imagick, off-heap memory) must be skipped while a bulk operation is
 * suppressed, and must still run during a normal save.
 */
#[Group('integration')]
final class PageListenerOpenGraphTest extends TestCase
{
    public function testOpenGraphImageIsNotGeneratedWhenSuppressed(): void
    {
        $generator = $this->createMock(PageOpenGraphImageGenerator::class);
        $generator->method('setPage')->willReturnSelf();
        $generator->expects(self::never())->method('generatePreviewImage');

        $suppressor = new PageCacheSuppressor();
        $listener = $this->buildListener($generator, $suppressor);

        $suppressor->suppress(fn () => $listener->prePersist($this->buildPage()));
    }

    public function testOpenGraphImageIsGeneratedOnNormalSave(): void
    {
        $generator = $this->createMock(PageOpenGraphImageGenerator::class);
        $generator->method('setPage')->willReturnSelf();
        $generator->expects(self::once())->method('generatePreviewImage');

        $listener = $this->buildListener($generator, new PageCacheSuppressor());

        $listener->prePersist($this->buildPage());
    }

    public function testOpenGraphImageIsNotGeneratedWhenSuppressedOnUpdate(): void
    {
        $generator = $this->createMock(PageOpenGraphImageGenerator::class);
        $generator->method('setPage')->willReturnSelf();
        $generator->expects(self::never())->method('generatePreviewImage');

        $suppressor = new PageCacheSuppressor();
        $listener = $this->buildListener($generator, $suppressor);

        $event = $this->createMock(PreUpdateEventArgs::class);
        $event->method('hasChangedField')->willReturn(false);

        $suppressor->suppress(fn () => $listener->preUpdate($this->buildPage(), $event));
    }

    private function buildListener(PageOpenGraphImageGenerator $generator, PageCacheSuppressor $suppressor): PageListener
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        return new PageListener(
            $security,
            $generator,
            $this->createStub(TailwindGenerator::class),
            $suppressor,
        );
    }

    private function buildPage(): Page
    {
        $page = new Page();
        $page->setSlug('og-suppression-test');
        $page->setH1('Test');
        $page->host = 'localhost.dev';

        return $page;
    }
}
