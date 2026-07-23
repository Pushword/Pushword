<?php

namespace Pushword\Admin\Tests\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Admin\FormField\PageLocaleField;
use Pushword\Core\Entity\Page;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class PageLocaleFieldTest extends TestCase
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

    private function createDto(): \EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto
    {
        $field = (new PageLocaleField($this->formFieldManager, $this->admin))->getEasyAdminField();

        self::assertInstanceOf(FieldInterface::class, $field);

        return $field->getAsDto();
    }

    public function testFieldUsesLocalePropertyAndTextType(): void
    {
        $dto = $this->createDto();

        self::assertSame('locale', $dto->getProperty());
        self::assertSame(TextType::class, $dto->getFormType());
    }

    /**
     * Page::$locale defaults to '' — an empty locale means "the site's locale", so the
     * field must never be required. It sits in the (collapsed by default) translations
     * fieldset: a `required` attribute there makes the browser abort the submit without
     * being able to focus the control, i.e. the save button silently does nothing.
     */
    public function testFieldIsNotRequired(): void
    {
        $page = new Page();

        self::assertSame('', $page->locale, 'An empty locale is a legit Page state');

        self::assertFalse($this->createDto()->getFormTypeOptions()['required']);
    }
}
