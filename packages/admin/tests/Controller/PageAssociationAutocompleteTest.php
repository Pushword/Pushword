<?php

declare(strict_types=1);

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\FormField\AutocompleteSubjectConfigurator;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

/**
 * The page form fetches its association candidates through EasyAdmin's autocomplete
 * endpoint. That endpoint rebuilds the fields against an empty subject, so every
 * filter these fields apply has to survive being re-derived there — otherwise the
 * dropdown silently widens to the wrong host, or to the page being edited itself.
 */
#[Group('integration')]
final class PageAssociationAutocompleteTest extends AbstractAdminTestClass
{
    private const string SECOND_HOST = 'admin-block-editor.test';

    public function testParentPageCandidatesStayOnTheEditedPageHost(): void
    {
        $client = $this->loginUser();
        $page = $this->pageOnSecondHost();

        $results = $this->autocomplete($client, $page, 'parentPage');

        // The empty subject falls back to the *first* configured host, so a page on
        // any other one is what tells a re-derived filter from a lucky default.
        self::assertNotEmpty($results);
        foreach ($results as $result) {
            self::assertStringStartsWith(self::SECOND_HOST.'/', $result['entityAsString']);
        }
    }

    public function testParentPageCandidatesExcludeTheEditedPage(): void
    {
        $client = $this->loginUser();
        $page = $this->pageOnSecondHost();

        $ids = array_column($this->autocomplete($client, $page, 'parentPage'), 'entityId');

        self::assertNotContains((string) $page->id, $ids, 'a page cannot be its own parent');
    }

    public function testVariantOfCandidatesOfferOnlyMasters(): void
    {
        $client = $this->loginUser();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page = $this->pageOnSecondHost();

        $ids = array_column($this->autocomplete($client, $page, 'variantOf'), 'entityId');
        self::assertNotContains((string) $page->id, $ids);

        foreach ($ids as $id) {
            $candidate = $em->getRepository(Page::class)->find((int) $id);
            self::assertNotNull($candidate);
            self::assertNull($candidate->variantOf, 'only a master can be a variant master');
        }
    }

    public function testTranslationCandidatesExcludeTheEditedLocale(): void
    {
        $client = $this->loginUser();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page = $this->pageOnSecondHost();

        $ids = array_column($this->autocomplete($client, $page, 'translations'), 'entityId');
        self::assertNotContains((string) $page->id, $ids);

        foreach ($ids as $id) {
            $candidate = $em->getRepository(Page::class)->find((int) $id);
            self::assertNotNull($candidate);
            self::assertNotSame($page->locale, $candidate->locale);
        }
    }

    /**
     * Deliberately unlike the two fields above: a multilingual site may serve each
     * locale from its own domain, so a translation is looked for across hosts.
     */
    public function testTranslationCandidatesCrossHosts(): void
    {
        $client = $this->loginUser();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $page = $this->pageOnSecondHost();

        $foreign = new Page();
        $foreign->h1 = 'Autocomplete cross-host translation';
        $foreign->slug = 'autocomplete-cross-host-translation';
        $foreign->locale = 'de';
        $foreign->host = 'localhost.dev';

        $em->persist($foreign);
        $em->flush();

        $foreignId = (int) $foreign->id;

        try {
            $ids = array_column($this->autocomplete($client, $page, 'translations'), 'entityId');
            self::assertContains((string) $foreignId, $ids);
        } finally {
            // The request above cleared the identity map, so the instance held here
            // is detached and only a fresh one can be removed.
            $em = self::getContainer()->get(EntityManagerInterface::class);
            $stored = $em->getRepository(Page::class)->find($foreignId);
            if (null !== $stored) {
                $em->remove($stored);
                $em->flush();
            }
        }
    }

    /**
     * A page being created has no id for the endpoint to name, so nothing is passed
     * and both requests fall back the same way — on the host the page will actually
     * be created on, which `createEntity()` takes from the site registry.
     */
    public function testCandidatesForANewPageMatchTheHostItWillBeCreatedOn(): void
    {
        $client = $this->loginUser();

        $crawler = $client->request(Request::METHOD_GET, '/admin/page/new');
        self::assertResponseIsSuccessful();

        $host = $crawler->filter('select[name="Page[host]"] option[selected]')->attr('value');
        self::assertIsString($host, 'the new page form must say which host it creates on');

        $endpoint = $crawler->filter('[name^="Page[parentPage]"][data-ea-autocomplete-endpoint-url]')
            ->attr('data-ea-autocomplete-endpoint-url');
        self::assertIsString($endpoint);
        self::assertStringNotContainsString(AutocompleteSubjectConfigurator::SUBJECT_ID_PARAM, $endpoint);

        $results = $this->fetchResults($client, $endpoint);

        self::assertNotEmpty($results);
        foreach ($results as $result) {
            self::assertStringStartsWith($host.'/', $result['entityAsString']);
        }
    }

    private function pageOnSecondHost(): Page
    {
        $page = self::getContainer()->get(EntityManagerInterface::class)
            ->getRepository(Page::class)
            ->findOneBy(['host' => self::SECOND_HOST, 'slug' => 'kitchen-sink']);

        self::assertInstanceOf(Page::class, $page);

        return $page;
    }

    /**
     * @return list<array{entityId: string, entityAsString: string}>
     */
    private function autocomplete(KernelBrowser $client, Page $page, string $property): array
    {
        $crawler = $client->request(Request::METHOD_GET, '/admin/page/'.$page->id.'/edit');
        self::assertResponseIsSuccessful();

        $endpoint = $crawler->filter(sprintf('[name^="Page[%s]"][data-ea-autocomplete-endpoint-url]', $property))
            ->attr('data-ea-autocomplete-endpoint-url');
        self::assertIsString($endpoint, $property.' must be fetched on demand');
        self::assertStringContainsString('pwSubjectId='.$page->id, $endpoint, 'the endpoint must name the edited page');

        return $this->fetchResults($client, $endpoint);
    }

    /**
     * @return list<array{entityId: string, entityAsString: string}>
     */
    private function fetchResults(KernelBrowser $client, string $endpoint): array
    {
        $client->request(Request::METHOD_GET, $endpoint);
        self::assertResponseIsSuccessful();

        /** @var array{results: list<array{entityId: string, entityAsString: string}>} $payload */
        $payload = json_decode($client->getInternalResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        return $payload['results'];
    }
}
