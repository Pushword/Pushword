<?php

namespace Pushword\StaticGenerator\Tests\Cache\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Core\Entity\Page;
use Pushword\StaticGenerator\Cache\Admin\PageHoldPublicationField;
use ReflectionProperty;

final class PageHoldPublicationFieldTest extends TestCase
{
    /** @var AdminFormFieldManager&Stub */
    private Stub $formFieldManager;

    /** @var AdminInterface<Page>&Stub */
    private Stub $admin;

    protected function setUp(): void
    {
        $this->formFieldManager = self::createStub(AdminFormFieldManager::class);
        $this->admin = self::createStub(AdminInterface::class);
    }

    private function createField(): PageHoldPublicationField
    {
        return new PageHoldPublicationField($this->formFieldManager, $this->admin);
    }

    private function createPage(?int $id, string $host = ''): Page
    {
        $page = new Page();
        $page->host = $host;

        if (null !== $id) {
            $idProperty = new ReflectionProperty(Page::class, 'id');
            $idProperty->setValue($page, $id);
        }

        return $page;
    }

    public function testReturnsNullForNewPage(): void
    {
        $this->admin->method('getSubject')->willReturn($this->createPage(null));

        self::assertNull($this->createField()->getEasyAdminField());
    }

    /**
     * Regression: the switch used to require `cache: static`, hiding it on hosts
     * served by the full static export. A saved page must now expose it whatever
     * the host's cache mode, since `pw:static` honours holdPublicationAt too.
     */
    public function testReturnsFieldForSavedPageRegardlessOfCacheMode(): void
    {
        $this->admin->method('getSubject')->willReturn($this->createPage(2389, 'dolomitesrando.com'));

        $field = $this->createField()->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $field);

        $dto = $field->getAsDto();
        self::assertSame('holdPublication', $dto->getProperty());
        self::assertSame('adminPageHoldPublicationLabel', $dto->getLabel());
    }

    public function testFieldIsOnlyOnForms(): void
    {
        $this->admin->method('getSubject')->willReturn($this->createPage(1));

        $field = $this->createField()->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $field);

        $dto = $field->getAsDto();
        self::assertFalse($dto->isDisplayedOn('index'));
        self::assertFalse($dto->isDisplayedOn('detail'));
        self::assertTrue($dto->isDisplayedOn('edit'));
        self::assertTrue($dto->isDisplayedOn('new'));
    }
}
