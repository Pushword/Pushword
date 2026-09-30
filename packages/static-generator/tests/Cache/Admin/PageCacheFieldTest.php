<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Cache\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Core\Entity\Page;
use Pushword\StaticGenerator\Cache\Admin\PageCacheField;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('integration')]
final class PageCacheFieldTest extends KernelTestCase
{
    /**
     * localhost.dev runs `cache: static`, so an ordinary page gets the checkbox. The
     * error page is never cached, and a checkbox would be ignored.
     */
    public function testTheErrorPageHasNoCacheCheckbox(): void
    {
        self::assertInstanceOf(FieldInterface::class, $this->fieldFor('about'));
        self::assertNull($this->fieldFor('404'));
    }

    private function fieldFor(string $slug): ?FieldInterface
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->slug = $slug;

        /** @var AdminInterface<Page>&Stub $admin */
        $admin = self::createStub(AdminInterface::class);
        $admin->method('getSubject')->willReturn($page);

        return new PageCacheField(self::getContainer()->get(AdminFormFieldManager::class), $admin)->getEasyAdminField();
    }
}
