<?php

namespace Pushword\PageWorkflow\Tests\Controller\Admin;

use DateTime;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Entity\Page;
use Pushword\PageWorkflow\Pending\PendingModification;
use Pushword\PageWorkflow\Pending\PendingModificationStorageInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Covers the CSRF gate, the saveCompare write-back path, and the approve-then-apply
 * round-trip that touches the live Page row.
 */
#[Group('integration')]
final class PendingModificationControllerTest extends AbstractAdminTestClass
{
    public function testSaveCompareRejectsInvalidCsrfTokenWith403(): void
    {
        $client = $this->loginUser();
        $page = $this->createPublishedPage();

        $client->request(Request::METHOD_POST, '/admin/page/'.$page->id.'/pending/save-compare', [
            '_token' => 'wrong-token',
            'mainContent' => 'whatever',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testSaveCompareWithValidTokenWritesPayloadToStorage(): void
    {
        $client = $this->loginUser();
        $page = $this->createPublishedPage();

        // Seed a pending so compare renders the save form (and the CSRF token).
        $seed = new PendingModification(pageId: (int) $page->id, payload: []);
        $this->storage()->write($page, $seed);

        $token = $this->extractToken(
            $client,
            '/admin/page/'.$page->id.'/pending/compare',
            'form#save-form input[name="_token"]',
        );

        $client->request(Request::METHOD_POST, '/admin/page/'.$page->id.'/pending/save-compare', [
            '_token' => $token,
            'mainContent' => 'proposed body',
            'h1' => 'proposed h1',
            'title' => 'proposed title',
            'name' => 'proposed name',
            'metaRobots' => 'noindex',
            'editMessage' => 'quick fix',
        ]);

        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode());

        $stored = $this->storage()->read($page);
        self::assertNotNull($stored);
        self::assertSame('proposed body', $stored->payload['mainContent']);
        self::assertSame('proposed h1', $stored->payload['h1']);
        self::assertSame('quick fix', $stored->editMessage);

        $this->storage()->delete($page);
    }

    public function testWorkflowTransitionRejectsInvalidCsrfTokenWith403(): void
    {
        $client = $this->loginUser();
        $page = $this->createPublishedPage();

        $client->request(Request::METHOD_POST, '/admin/page/'.$page->id.'/workflow/submit', [
            '_token' => 'wrong-token',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testPendingTransitionRejectsInvalidCsrfTokenWith403(): void
    {
        $client = $this->loginUser();
        $page = $this->createPublishedPage();

        $client->request(Request::METHOD_POST, '/admin/page/'.$page->id.'/pending/transition/submit', [
            '_token' => 'wrong-token',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testApproveAppliesPayloadOnPageAndEmptiesStorage(): void
    {
        $client = $this->loginUser();
        $page = $this->createPublishedPage();
        $originalH1 = $page->getH1();

        // Seed a pending modification directly through the storage layer.
        $modification = new PendingModification(
            pageId: (int) $page->id,
            payload: [
                'h1' => 'Approved H1',
                'mainContent' => 'Approved body',
                'title' => 'Approved title',
                'name' => 'Approved name',
                'metaRobots' => '',
            ],
        );
        $modification->workflowState = 'in_review'; // approve requires this state
        $this->storage()->write($page, $modification);

        // Re-render compare to expose the transition CSRF tokens.
        $token = $this->extractToken(
            $client,
            '/admin/page/'.$page->id.'/pending/compare',
            'form[action$="/pending/transition/approve"] input[name="_token"]',
        );

        $client->request(Request::METHOD_POST, '/admin/page/'.$page->id.'/pending/transition/approve', [
            '_token' => $token,
        ]);

        self::assertSame(Response::HTTP_FOUND, $client->getResponse()->getStatusCode());

        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $em->clear();

        $refreshed = $em->getRepository(Page::class)->find($page->id);
        self::assertNotNull($refreshed);
        self::assertSame('Approved H1', $refreshed->getH1(), 'live H1 must be replaced by the approved payload');
        self::assertSame('Approved body', $refreshed->getMainContent());
        self::assertNotSame($originalH1, $refreshed->getH1());

        self::assertFalse($this->storage()->has($refreshed), 'storage must be emptied after approval');
    }

    public function testCompareSerializesSmallFieldsAsJsArray(): void
    {
        $client = $this->loginUser();
        $page = $this->createPublishedPage();

        $seed = new PendingModification(pageId: (int) $page->id, payload: []);
        $this->storage()->write($page, $seed);

        $client->request(Request::METHOD_GET, '/admin/page/'.$page->id.'/pending/compare');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // mainContent is filtered out of the middle of FIELDS; without re-indexing the
        // remaining array keeps sparse keys and json_encode emits a JS object, breaking
        // smallFields.forEach() in the browser.
        $content = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('const smallFields = ["h1","title","name","metaRobots"];', $content);

        $this->storage()->delete($page);
    }

    public function testPendingDiscardRejectsInvalidCsrfTokenWith403(): void
    {
        $client = $this->loginUser();
        $page = $this->createPublishedPage();

        $client->request(Request::METHOD_POST, '/admin/page/'.$page->id.'/pending/discard', [
            '_token' => 'wrong-token',
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * GET the page and pull the CSRF token straight out of the rendered form —
     * the only reliable way to get a token bound to the client's session.
     */
    private function extractToken(KernelBrowser $client, string $url, string $selector): string
    {
        $crawler = $client->request(Request::METHOD_GET, $url);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), 'GET '.$url.' must succeed to expose CSRF tokens');

        $node = $crawler->filter($selector);
        self::assertGreaterThan(0, $node->count(), 'CSRF token element not found: '.$selector);

        return $node->attr('value') ?? '';
    }

    private function storage(): PendingModificationStorageInterface
    {
        return self::getContainer()->get(PendingModificationStorageInterface::class);
    }

    /**
     * Always create a fresh, isolated page so concurrent tests don't trample each
     * other's payload or storage state.
     */
    private function createPublishedPage(): Page
    {
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $page = new Page();
        $page->host = 'localhost.dev';
        $page->setSlug('pending-ctrl-'.uniqid());
        $page->setH1('Original H1');
        $page->title = 'Original title';
        $page->name = 'Original name';
        $page->setMainContent('Original body');
        $page->setPublishedAt(new DateTime('-1 hour'));

        $em->persist($page);
        $em->flush();

        return $page;
    }

    protected function tearDown(): void
    {
        // KernelBrowser doesn't reset between tests in the same class; explicit cleanup
        // keeps the storage and DB tidy for the next case.
        if (null !== $this->client && $this->client instanceof KernelBrowser) {
            $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
            foreach ($em->getRepository(Page::class)->createQueryBuilder('p')->where("p.slug LIKE 'pending-ctrl-%'")->getQuery()->getResult() as $page) {
                $this->storage()->delete($page);
                $em->remove($page);
            }

            $em->flush();
        }

        parent::tearDown();
    }
}
