<?php

namespace Pushword\Core\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\EventListener\PageListener;
use Pushword\Core\Service\PageOpenGraphImageGenerator;
use Pushword\Core\Service\TailwindGenerator;
use Pushword\Core\Service\VariantManager;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

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

        $event = self::createStub(PreUpdateEventArgs::class);
        $event->method('hasChangedField')->willReturn(false);

        $suppressor->suppress(fn () => $listener->preUpdate($this->buildPage(), $event));
    }

    private function buildListener(PageOpenGraphImageGenerator $generator, PageCacheSuppressor $suppressor): PageListener
    {
        $security = self::createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        return new PageListener(
            $security,
            $generator,
            self::createStub(TailwindGenerator::class),
            $suppressor,
            new VariantManager(self::createStub(EntityManagerInterface::class)),
            $this->buildSiteRegistry(),
        );
    }

    private function buildSiteRegistry(): SiteRegistry
    {
        return new SiteRegistry(
            ['localhost.dev' => [
                'hosts' => ['localhost.dev'],
                'base_url' => 'https://localhost.dev',
                'name' => 'Test',
                'locale' => 'fr',
                'locales' => ['fr'],
                'template' => '@Pushword',
                'entity_can_override_filters' => false,
            ]],
            new TemplateResolver(new Twig(new FilesystemLoader()), new ArrayAdapter()),
            new ParameterBag(['kernel.project_dir' => sys_get_temp_dir()]),
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
