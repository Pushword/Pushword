<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests;

use DateTime;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use IntlDateFormatter;
use Locale;
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
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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

    public function testThePublishedToggleShowsItsDateOnTheEditorialClock(): void
    {
        // Still 30 September on the server, already 1 October in Paris.
        $publishedAt = new DateTime('2026-09-30 23:30:00 UTC');

        $html = $this->renderOnTheParisClock('@pwAdmin/components/published_toggle.html.twig', [
            'entity' => ['instance' => $this->page()],
            'value' => $publishedAt,
            'field' => null,
        ]);

        $shortDate = static fn (string $timezone): string|false => new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::SHORT, IntlDateFormatter::NONE, $timezone)->format($publishedAt);
        self::assertStringContainsString((string) $shortDate('Europe/Paris'), $html);
        self::assertStringNotContainsString((string) $shortDate('UTC'), $html);
    }

    public function testTheScheduledBadgeShowsItsDateOnTheEditorialClock(): void
    {
        $page = $this->page();
        // In the future, so the page list shows it as scheduled, with the date in the badge title.
        $publishedAt = new DateTime('2099-09-30 23:30:00 UTC');
        $page->publishedAt = $publishedAt;

        $html = $this->renderOnTheParisClock('@pwAdmin/page/pageListTitleField.html.twig', ['entity' => ['instance' => $page]]);

        $badge = new Crawler($html)->filter('.badge-info');
        self::assertCount(1, $badge);
        self::assertSame(
            new IntlDateFormatter(Locale::getDefault(), IntlDateFormatter::MEDIUM, IntlDateFormatter::MEDIUM, 'Europe/Paris')->format($publishedAt),
            $badge->attr('title'),
        );
    }

    private function page(): Page
    {
        $page = new Page();
        new ReflectionProperty(Page::class, 'id')->setValue($page, 42);
        $page->host = 'localhost.dev';
        $page->slug = 'scheduled';
        $page->title = 'Scheduled';

        return $page;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderOnTheParisClock(string $template, array $context): string
    {
        self::bootKernel();
        // The list fragments sign their htmx posts with a CSRF token, which lives in the session.
        $request = Request::create('/admin');
        $request->setSession(new Session(new MockArraySessionStorage()));
        self::getContainer()->get('request_stack')->push($request);

        // A context variable shadows the global of the same name.
        return self::getContainer()->get('twig')->render($template, $context + ['editorial_timezone' => new EditorialTimezone('Europe/Paris')]);
    }
}
