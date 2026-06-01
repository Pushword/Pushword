<?php

namespace Pushword\PageWorkflow\Tests\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\PageWorkflow\Pending\PendingModification;
use Pushword\PageWorkflow\Pending\PendingModificationStorageInterface;
use Pushword\PageWorkflow\Repository\PageEditorialStateRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class PageWorkflowApiControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testToken = '';

    private string $testUserEmail = '';

    /** @var list<int> */
    private array $createdPageIds = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->testToken = bin2hex(random_bytes(32));
        $this->testUserEmail = 'workflow-api-test-'.uniqid().'@example.com';
        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->apiToken = $this->testToken;
        $user->setRoles(['ROLE_EDITOR']);
        $this->em->persist($user);
        $this->em->flush();
    }

    #[Override]
    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        /** @var PendingModificationStorageInterface $storage */
        $storage = $container->get(PendingModificationStorageInterface::class);
        foreach ($this->createdPageIds as $id) {
            $page = $em->getRepository(Page::class)->find($id);
            if ($page instanceof Page) {
                $storage->delete($page);
                $em->remove($page);
            }
        }
        /** @var class-string<User> $userClass */
        $userClass = $container->getParameter('pw.entity_user');
        $user = $em->getRepository($userClass)->findOneBy(['email' => $this->testUserEmail]);
        if (null !== $user) {
            $em->remove($user);
        }
        $em->flush();
        parent::tearDown();
    }

    public function testTransitionRequiresToken(): void
    {
        $this->client->request('POST', '/api/page/example.com/foo/transition');
        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testTransitionUnknownPageReturns404(): void
    {
        $response = $this->request('POST', '/api/page/nope.example.com/missing/transition', ['transition' => 'submit']);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testTransitionMissingTransitionReturns400(): void
    {
        $page = $this->seedPage();
        $response = $this->request('POST', '/api/page/'.$page->host.'/'.$page->getSlug().'/transition', []);
        self::assertSame(400, $response->getStatusCode());
    }

    public function testSubmitMovesPageToInReview(): void
    {
        $page = $this->seedPage();
        $response = $this->request('POST', '/api/page/'.$page->host.'/'.$page->getSlug().'/transition', [
            'transition' => 'submit',
        ]);
        self::assertSame(200, $response->getStatusCode());
        $body = $this->decode();
        self::assertSame('in_review', $body['state']);

        // Verify state is persisted.
        /** @var PageEditorialStateRepository $repo */
        $repo = self::getContainer()->get(PageEditorialStateRepository::class);
        $state = $repo->findFor($page);
        self::assertNotNull($state);
        self::assertSame('in_review', $state->getWorkflowState());
    }

    public function testTransitionDeniedReturns409(): void
    {
        $page = $this->seedPage();
        // 'approve' is not allowed from 'draft'
        $response = $this->request('POST', '/api/page/'.$page->host.'/'.$page->getSlug().'/transition', [
            'transition' => 'approve',
        ]);
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertSame('transition_denied', $this->decode()['error']);
    }

    public function testListPendingReturnsItems(): void
    {
        $page = $this->seedPage();
        $this->seedPendingFor($page, 'in_review');

        $this->request('GET', '/api/page-workflow/pending');
        self::assertResponseIsSuccessful();
        $body = $this->decode();
        self::assertGreaterThanOrEqual(1, $body['total']);
    }

    public function testApplyApprovedPendingMutatesPage(): void
    {
        $page = $this->seedPage();
        $this->seedPendingFor($page, 'approved', ['h1' => 'New H1', 'mainContent' => '## changed']);
        self::assertNotNull($page->id);

        $response = $this->request('POST', '/api/page-workflow/pending/'.$page->id.'/apply');
        self::assertSame(200, $response->getStatusCode());
        $this->em->refresh($page);
        self::assertSame('New H1', $page->getH1());
        self::assertStringContainsString('changed', $page->getMainContent());
    }

    public function testApplyNonApprovedReturns409(): void
    {
        $page = $this->seedPage();
        $this->seedPendingFor($page, 'in_review');
        self::assertNotNull($page->id);

        $response = $this->request('POST', '/api/page-workflow/pending/'.$page->id.'/apply');
        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testDeletePendingDiscards(): void
    {
        $page = $this->seedPage();
        $this->seedPendingFor($page, 'in_review');
        self::assertNotNull($page->id);

        $response = $this->request('DELETE', '/api/page-workflow/pending/'.$page->id);
        self::assertSame(204, $response->getStatusCode());

        /** @var PendingModificationStorageInterface $storage */
        $storage = self::getContainer()->get(PendingModificationStorageInterface::class);
        self::assertFalse($storage->has($page));
    }

    private function seedPage(): Page
    {
        $page = new Page();
        $page->host = 'workflow-host-'.uniqid().'.example.com';
        $page->setSlug('about-'.uniqid());
        $page->setH1('Original');
        $page->setMainContent('## original');
        $page->setPublishedAt(null);
        $this->em->persist($page);
        $this->em->flush();
        $this->createdPageIds[] = $page->id ?? 0;

        return $page;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function seedPendingFor(Page $page, string $state = 'in_review', array $payload = []): void
    {
        self::assertNotNull($page->id);
        $modification = new PendingModification($page->id, $payload);
        $modification->workflowState = $state;
        /** @var PendingModificationStorageInterface $storage */
        $storage = self::getContainer()->get(PendingModificationStorageInterface::class);
        $storage->write($page, $modification);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(string $method, string $url, array $body = []): Response
    {
        $server = ['HTTP_AUTHORIZATION' => 'Bearer '.$this->testToken, 'CONTENT_TYPE' => 'application/json'];
        $this->client->request($method, $url, [], [], $server, [] === $body ? '' : (string) json_encode($body));

        return $this->client->getResponse();
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(): array
    {
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
