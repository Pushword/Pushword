<?php

namespace Pushword\Admin\Tests\Controller;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Controller\PageCrudController;
use Pushword\Admin\FormField\PageSchemaPropertiesField;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\HttpFoundation\Request;

/**
 * The generated schema-property fields, end to end: localhost.dev declares
 * subtitle (string), level (Choice beginner|advanced) and priority (int).
 *
 * The three writeback rows: a submitted field wins, a present-but-empty field
 * clears, an absent field (a form rendered before the declaration deployed)
 * leaves the stored value alone.
 */
#[Group('integration')]
final class PageSchemaPropertiesFieldTest extends AbstractAdminTestClass
{
    private const string SLUG = 'schema-field-fixture';

    private ?Page $page = null;

    protected function tearDown(): void
    {
        if (null !== $this->page) {
            $entityManager = $this->getEntityManager();
            $page = $entityManager->getRepository(Page::class)->findOneBy(['slug' => self::SLUG]);
            if (null !== $page) {
                $entityManager->remove($page);
                $entityManager->flush();
            }
        }

        parent::tearDown();
    }

    public function testGeneratedFieldsRenderWriteBackClearAndSurviveAStaleForm(): void
    {
        $client = $this->loginUser();
        $page = $this->createPage();
        $editPath = $this->buildEditPath($page->id ?? 0);

        $crawler = $client->request(Request::METHOD_GET, $editPath);
        self::assertResponseIsSuccessful();

        // Declared properties become real inputs...
        $levelSelect = $crawler->filter('select#Page_level');
        self::assertCount(1, $levelSelect, 'the Choice-constrained property renders as a dropdown');
        self::assertStringContainsString('beginner', $levelSelect->html());
        self::assertStringContainsString('advanced', $levelSelect->html());
        self::assertCount(1, $crawler->filter('input#Page_priority'), 'the int property renders as its own input');

        // Declared by advanced-main-image AND owned by its dedicated ChoiceField,
        // which registers the key managed before the generated fields build:
        // exactly one input, never a generated duplicate.
        self::assertCount(1, $crawler->filter('#Page_mainImageFormat'));

        // ...and leave the free-form textarea, which keeps undeclared keys.
        $textarea = $crawler->filter('textarea#Page_unmanagedPropertiesAsYaml')->text();
        self::assertStringContainsString('freeKey', $textarea);
        self::assertStringNotContainsString('level', $textarea);

        // Row "fresh form, field carries a value": the field wins.
        $form = $crawler->filter('form[method=post]')->form();
        $form['Page[level]'] = 'advanced';
        $form['Page[priority]'] = '7';
        $client->submit($form);
        self::assertResponseRedirects();

        $properties = $this->reloadProperties();
        self::assertSame('advanced', $properties['level']);
        self::assertSame(7, $properties['priority']);
        self::assertSame('kept', $properties['freeKey'], 'the textarea keys survive the generated-field writeback');

        // Row "present but empty": the value clears.
        $crawler = $client->request(Request::METHOD_GET, $editPath);
        $form = $crawler->filter('form[method=post]')->form();
        $form['Page[priority]'] = '';
        $client->submit($form);
        self::assertResponseRedirects();

        $properties = $this->reloadProperties();
        self::assertArrayNotHasKey('priority', $properties, 'an emptied field removes the key instead of storing noise');
        self::assertSame('advanced', $properties['level']);

        // A partial form — no generated fields, no marker, textarea without
        // the keys (e.g. a role-gated subset) — must not clobber anything.
        $crawler = $client->request(Request::METHOD_GET, $editPath);
        $this->submitWithoutSchemaFields($client, $crawler);
        self::assertResponseRedirects();

        $properties = $this->reloadProperties();
        self::assertSame('advanced', $properties['level'], 'an absent field must not clobber the stored value');

        // A true STALE form — rendered before the declaration deployed — has
        // no generated fields, no marker, and still carries the keys in its
        // textarea: the typed YAML value wins (the editor's change is kept).
        $crawler = $client->request(Request::METHOD_GET, $editPath);
        $this->submitWithoutSchemaFields($client, $crawler, "level: beginner\nfreeKey: kept");
        self::assertResponseRedirects();

        $properties = $this->reloadProperties();
        self::assertSame('beginner', $properties['level'], 'a stale textarea value writes through instead of throwing');
        self::assertSame('kept', $properties['freeKey']);

        // Date and list round-trip: the date widget stores the same shape an
        // unquoted frontmatter date yields (a Unix timestamp), the collection
        // stores a plain list; hydration renders both back.
        $crawler = $client->request(Request::METHOD_GET, $editPath);
        self::assertCount(1, $crawler->filter('input#Page_eventDate'), 'the date property renders as a date input');
        $form = $crawler->filter('form[method=post]')->form();
        $values = $form->getPhpValues();
        self::assertIsArray($values['Page']);
        $values['Page']['eventDate'] = '2026-08-15';
        $values['Page']['highlights'] = ['pool', 'sauna'];
        $client->request(Request::METHOD_POST, $form->getUri(), $values);
        self::assertResponseRedirects();

        $properties = $this->reloadProperties();
        self::assertSame(strtotime('2026-08-15'), $properties['eventDate'], 'the date stores as a timestamp, like YAML frontmatter');
        self::assertSame(['pool', 'sauna'], $properties['highlights']);

        $crawler = $client->request(Request::METHOD_GET, $editPath);
        self::assertSame('2026-08-15', $crawler->filter('input#Page_eventDate')->attr('value'), 'the stored timestamp hydrates the widget');

        // Checkbox round-trip: checking stores true, unchecking removes the
        // key — templates test `null !== page.toc`, a stored false would lie.
        $crawler = $client->request(Request::METHOD_GET, $editPath);
        $form = $crawler->filter('form[method=post]')->form();
        $this->checkbox($form, 'Page[toc]')->tick();
        $client->submit($form);
        self::assertResponseRedirects();
        self::assertTrue($this->reloadProperties()['toc'] ?? null);

        $crawler = $client->request(Request::METHOD_GET, $editPath);
        $form = $crawler->filter('form[method=post]')->form();
        $this->checkbox($form, 'Page[toc]')->untick();
        $client->submit($form);
        self::assertResponseRedirects();
        self::assertArrayNotHasKey('toc', $this->reloadProperties(), 'unchecking removes the key');
    }

    private function submitWithoutSchemaFields(KernelBrowser $client, Crawler $crawler, ?string $textareaYaml = null): void
    {
        $form = $crawler->filter('form[method=post]')->form();
        $values = $form->getPhpValues();
        self::assertIsArray($values['Page']);
        unset(
            $values['Page']['level'],
            $values['Page']['priority'],
            $values['Page']['subtitle'],
            $values['Page']['toc'],
            $values['Page']['tocTitle'],
            $values['Page'][PageSchemaPropertiesField::RENDERED_MARKER],
        );

        if (null !== $textareaYaml) {
            $values['Page']['unmanagedPropertiesAsYaml'] = $textareaYaml;
        }

        $client->request(Request::METHOD_POST, $form->getUri(), $values);
    }

    private function createPage(): Page
    {
        $entityManager = $this->getEntityManager();

        $page = new Page();
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->setSlug(self::SLUG);
        $page->setH1('Schema field fixture');
        $page->setMainContent('Content');
        $page->setPublishedAt(new DateTime('-1 day'));
        $page->setCustomProperty('level', 'beginner');
        $page->setCustomProperty('freeKey', 'kept');

        $entityManager->persist($page);
        $entityManager->flush();

        return $this->page = $page;
    }

    /** @return array<mixed> */
    private function reloadProperties(): array
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        $page = $entityManager->getRepository(Page::class)->findOneBy(['slug' => self::SLUG]);
        self::assertInstanceOf(Page::class, $page);

        return $page->customProperties;
    }

    private function checkbox(Form $form, string $name): ChoiceFormField
    {
        $field = $form[$name];
        self::assertInstanceOf(ChoiceFormField::class, $field);

        return $field;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }

    private function buildEditPath(int $pageId): string
    {
        /** @var AdminUrlGenerator $urlGenerator */
        $urlGenerator = clone self::getContainer()->get(AdminUrlGenerator::class);
        $editUrl = $urlGenerator
            ->unsetAll()
            ->setController(PageCrudController::class)
            ->setAction('edit')
            ->setEntityId($pageId)
            ->generateUrl();

        $parsed = parse_url($editUrl);
        $path = \is_array($parsed) ? ($parsed['path'] ?? '/') : '/';
        if (\is_array($parsed) && isset($parsed['query'])) {
            $path .= '?'.$parsed['query'];
        }

        return $path;
    }
}
