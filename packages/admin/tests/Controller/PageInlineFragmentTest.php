<?php

namespace Pushword\Admin\Tests\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The page-list inline fragments (tags, weight, published and hold toggles) are
 * driven by htmx: each POST answers with the HTML that replaces the fragment.
 * Two contracts hide behind a 200 and neither survives a status-code assertion:
 * the change must persist, and the fragment rendered back must still carry its
 * own hx-* attributes — one that loses them swaps in dead HTML, so inline
 * editing works exactly once per page load and then silently stops.
 */
#[Group('integration')]
final class PageInlineFragmentTest extends AbstractAdminTestClass
{
    public function testPageListRendersTheInlineFragmentGrammar(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $pageId = $this->createPage();
        $crawler = $client->request(Request::METHOD_GET, '/admin/page');
        $row = $this->inlineRow($crawler, $pageId);

        $tags = $row->filter('input[hx-vals*="tags"]');
        self::assertCount(1, $tags, 'The row should expose an inline tags input.');
        self::assertSame('/admin/page/'.$pageId.'/inline-update', $this->path((string) $tags->attr('hx-post')));
        // `changed` is what stops a focus+blur from posting a no-op update.
        self::assertSame('blur changed', $tags->attr('hx-trigger'));
        self::assertSame('outerHTML', $tags->attr('hx-swap'));
        self::assertSame('#pw-page-inline-'.$pageId, $tags->attr('hx-target'));

        // The hold toggle rides inside the title cell; published and weight are
        // columns of their own, so they are looked up by their own target id.
        $hold = $row->filter('input[hx-vals*="hold"]');
        self::assertCount(1, $hold, 'The row should expose a hold toggle.');
        self::assertSame('change', $hold->attr('hx-trigger'));
        self::assertSame('#pw-hold-'.$pageId, $hold->attr('hx-target'));

        $published = $crawler->filter('#pw-published-page-'.$pageId.' input[hx-vals*="published"]');
        self::assertCount(1, $published, 'The list should expose a published toggle for the page.');
        self::assertSame('/admin/page/'.$pageId.'/toggle-published', $this->path((string) $published->attr('hx-post')));
        self::assertSame('change', $published->attr('hx-trigger'));
        self::assertSame('#pw-published-page-'.$pageId, $published->attr('hx-target'));

        $weight = $crawler->filter('#pw-weight-page-'.$pageId.' input[hx-vals*="weight"]');
        self::assertCount(1, $weight, 'The list should expose an inline weight field for the page.');
        self::assertSame('change, blur', $weight->attr('hx-trigger'));
        self::assertSame('#pw-weight-page-'.$pageId, $weight->attr('hx-target'));
    }

    public function testInlineTagsUpdatePersistsAndRendersBackAnInteractiveFragment(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $pageId = $this->createPage();
        $row = $this->inlineRow($client->request(Request::METHOD_GET, '/admin/page'), $pageId);
        $token = $this->extractToken((string) $row->filter('input[hx-vals*="tags"]')->attr('hx-vals'));

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/inline-update', [
            'field' => 'tags',
            'value' => 'alpha, beta',
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertSame(['alpha', 'beta'], $this->reloadPage($pageId)->getTagList());

        // The replacement fragment must stay wired, otherwise the next edit is dead.
        $fragment = new Crawler((string) $client->getResponse()->getContent());
        $input = $fragment->filter('input[hx-vals*="tags"]');
        self::assertCount(1, $input, 'The re-rendered row should still carry an htmx-wired tags input.');
        self::assertSame('blur changed', $input->attr('hx-trigger'));
        self::assertSame('alpha beta', $input->attr('value'));
    }

    public function testInlineWeightUpdatePersistsAndRendersBackAnInteractiveFragment(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $pageId = $this->createPage();
        $row = $this->inlineRow($client->request(Request::METHOD_GET, '/admin/page'), $pageId);
        $token = $this->extractToken((string) $row->filter('input[hx-vals*="tags"]')->attr('hx-vals'));

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/inline-update', [
            'field' => 'weight',
            'value' => '42',
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertSame(42, $this->reloadPage($pageId)->weight);

        $fragment = new Crawler((string) $client->getResponse()->getContent());
        $input = $fragment->filter('input[hx-vals*="weight"]');
        self::assertCount(1, $input, 'The weight field should render back its own htmx-wired input.');
        self::assertSame('change, blur', $input->attr('hx-trigger'));
        self::assertSame('#pw-weight-page-'.$pageId, $input->attr('hx-target'));
        self::assertSame('42', $input->attr('value'));
    }

    public function testInlineUpdateRejectsInvalidCsrf(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $pageId = $this->createPage();

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/inline-update', [
            'field' => 'tags',
            'value' => 'should-not-persist',
            '_token' => 'invalid-token',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        self::assertSame('', $this->reloadPage($pageId)->getTags());
    }

    public function testInlineUpdateRejectsAFieldThatIsNotInlineEditable(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $pageId = $this->createPage();
        $row = $this->inlineRow($client->request(Request::METHOD_GET, '/admin/page'), $pageId);
        $token = $this->extractToken((string) $row->filter('input[hx-vals*="tags"]')->attr('hx-vals'));

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/inline-update', [
            'field' => 'mainContent',
            'value' => 'Nope.',
            '_token' => $token,
        ]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        self::assertSame('Fixture body.', $this->reloadPage($pageId)->mainContent);
    }

    public function testTogglePublishedSwitchesBothWaysAndRendersBackAnInteractiveFragment(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $pageId = $this->createPage();
        $crawler = $client->request(Request::METHOD_GET, '/admin/page');
        $this->inlineRow($crawler, $pageId);
        $token = $this->extractToken((string) $crawler->filter('#pw-published-page-'.$pageId.' input')->attr('hx-vals'));

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/toggle-published', [
            'published' => '1',
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertNotNull($this->reloadPage($pageId)->publishedAt);

        $fragment = new Crawler((string) $client->getResponse()->getContent());
        $input = $fragment->filter('input[hx-vals*="published"]');
        self::assertCount(1, $input, 'The toggle should render back its own htmx-wired checkbox.');
        self::assertSame('change', $input->attr('hx-trigger'));
        self::assertNotNull($input->attr('checked'), 'A published page renders a checked toggle.');

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/toggle-published', [
            'published' => '0',
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertNull($this->reloadPage($pageId)->publishedAt);
        self::assertNull(
            new Crawler((string) $client->getResponse()->getContent())
                ->filter('input[hx-vals*="published"]')->attr('checked'),
        );
    }

    public function testTogglePublishedRejectsInvalidCsrf(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $pageId = $this->createPage();
        // A new page comes out published, so ask for the opposite: a rejected
        // request must leave the state exactly where it was.
        $before = $this->reloadPage($pageId)->publishedAt;
        self::assertNotNull($before);

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/toggle-published', [
            'published' => '0',
            '_token' => 'invalid-token',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        self::assertEquals($before, $this->reloadPage($pageId)->publishedAt);
    }

    public function testToggleHoldSwitchesBothWaysAndRendersBackAnInteractiveFragment(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $pageId = $this->createPage();
        $row = $this->inlineRow($client->request(Request::METHOD_GET, '/admin/page'), $pageId);
        $token = $this->extractToken((string) $row->filter('input[hx-vals*="hold"]')->attr('hx-vals'));

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/toggle-hold', [
            'hold' => '1',
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertNotNull($this->reloadPage($pageId)->holdPublicationAt);

        $fragment = new Crawler((string) $client->getResponse()->getContent());
        $input = $fragment->filter('input[hx-vals*="hold"]');
        self::assertCount(1, $input, 'The hold toggle should render back its own htmx-wired checkbox.');
        self::assertSame('change', $input->attr('hx-trigger'));

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/toggle-hold', [
            'hold' => '0',
            '_token' => $token,
        ]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertNull($this->reloadPage($pageId)->holdPublicationAt);
    }

    public function testToggleHoldRejectsInvalidCsrf(): void
    {
        $client = $this->loginUser();
        $client->catchExceptions(false);

        $pageId = $this->createPage();

        $client->request(Request::METHOD_POST, '/admin/page/'.$pageId.'/toggle-hold', [
            'hold' => '1',
            '_token' => 'invalid-token',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
        self::assertNull($this->reloadPage($pageId)->holdPublicationAt);
    }

    /**
     * The list sorts on updatedAt DESC, so a freshly created page is on top of
     * the first paginated screen whatever the fixture set looks like.
     */
    private function createPage(): int
    {
        $page = new Page();
        $page->host = 'localhost.dev';
        $page->locale = 'en';
        $page->slug = 'inline-fragment-'.uniqid();
        $page->h1 = 'Inline fragment fixture';
        $page->mainContent = 'Fixture body.';

        $entityManager = $this->getEntityManager();
        $entityManager->persist($page);
        $entityManager->flush();

        self::assertNotNull($page->id);

        return $page->id;
    }

    private function inlineRow(Crawler $crawler, int $pageId): Crawler
    {
        self::assertResponseIsSuccessful();

        $row = $crawler->filter('#pw-page-inline-'.$pageId);
        self::assertCount(1, $row, 'The created page should own a row on the first list screen.');

        return $row;
    }

    private function reloadPage(int $id): Page
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        $page = $entityManager->getRepository(Page::class)->find($id);
        self::assertInstanceOf(Page::class, $page);

        return $page;
    }

    /**
     * Reverse Twig's `e('js')` escaping (e.g. `-`) applied to the token in hx-vals.
     */
    private function extractToken(string $hxVals): string
    {
        self::assertSame(1, preg_match('/_token:\s*"([^"]*)"/', $hxVals, $matches));

        $token = preg_replace_callback(
            '/\\\\u([0-9A-Fa-f]{4})/',
            static fn (array $match): string => mb_chr((int) hexdec($match[1]), 'UTF-8'),
            $matches[1],
        );

        self::assertNotNull($token);

        return $token;
    }

    private function path(string $url): string
    {
        return (string) parse_url($url, \PHP_URL_PATH);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManager $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.default_entity_manager');

        return $entityManager;
    }
}
