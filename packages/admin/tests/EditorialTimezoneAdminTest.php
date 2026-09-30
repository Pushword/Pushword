<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests;

use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\AdminFormFieldManager;
use Pushword\Admin\AdminInterface;
use Pushword\Admin\Controller\DashboardController;
use Pushword\Admin\FormField\CreatedAtField;
use Pushword\Admin\FormField\PagePublishedAtField;
use Pushword\Core\Entity\Page;
use Pushword\Core\Service\EditorialTimezone;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The admin reads and writes dates on the editors' clock. Built without their
 * constructors: only the editorial timezone (and the Twig the help renders with)
 * matter here, and a Paris one must show through where the app runs on UTC.
 */
#[Group('integration')]
final class EditorialTimezoneAdminTest extends KernelTestCase
{
    public function testPageDateFieldsAreEditedOnTheEditorialClock(): void
    {
        self::bootKernel();
        self::getContainer()->get('request_stack')->push(Request::create('/admin'));

        $manager = new ReflectionClass(AdminFormFieldManager::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(AdminFormFieldManager::class, 'editorialTimezone')->setValue($manager, new EditorialTimezone('Europe/Paris'));
        new ReflectionProperty(AdminFormFieldManager::class, 'twig')->setValue($manager, self::getContainer()->get('twig'));

        $page = new Page();
        $page->publishedAt = new DateTime('2026-09-30 14:00:00');
        $admin = self::createStub(AdminInterface::class);
        $admin->method('getSubject')->willReturn($page);

        foreach ([new PagePublishedAtField($manager, $admin), new CreatedAtField($manager, $admin)] as $field) {
            $easyAdminField = $field->getEasyAdminField();
            self::assertInstanceOf(FieldInterface::class, $easyAdminField);
            self::assertSame('Europe/Paris', $easyAdminField->getAsDto()->getFormTypeOptions()['view_timezone'] ?? null, $field::class);
        }
    }

    public function testAdminListsShowDatesOnTheEditorialClock(): void
    {
        $dashboard = new ReflectionClass(DashboardController::class)->newInstanceWithoutConstructor();
        new ReflectionProperty(DashboardController::class, 'editorialTimezone')->setValue($dashboard, new EditorialTimezone('Europe/Paris'));

        self::assertSame('Europe/Paris', $dashboard->configureCrud()->getAsDto()->getTimezone());
    }
}
