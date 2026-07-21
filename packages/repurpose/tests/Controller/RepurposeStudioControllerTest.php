<?php

namespace Pushword\Repurpose\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Repurpose\Entity\SocialPost;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin studio: an authenticated preview + spec editor. It renders a stored
 * carousel to inline SVG and saves an edited spec through the same validator the
 * agent uses — the human-facing half of the "spec is the primary interface"
 * decision. Session-authenticated (ROLE_PUSHWORD_ADMIN), unlike the token API.
 */
#[Group('integration')]
final class RepurposeStudioControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private string $testUserEmail = '';

    private const string HOST = 'repurpose-studio-test.example';

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        $this->testUserEmail = 'repurpose-studio-test-'.uniqid().'@example.com';

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $user = new $userClass();
        $user->email = $this->testUserEmail;
        $user->setPassword('hashed-password');
        $user->setRoles(['ROLE_ADMIN']);

        $this->em->persist($user);
        $this->em->flush();

        $this->client->loginUser($user);
    }

    protected function tearDown(): void
    {
        $container = $this->client->getContainer();
        $em = $container->get('doctrine.orm.default_entity_manager');
        foreach ($em->getRepository(SocialPost::class)->findBy(['host' => self::HOST]) as $post) {
            $em->remove($post);
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

    public function testStudioRendersTheEditorShell(): void
    {
        $post = $this->persistPost();

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$post->id);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $html = (string) $this->client->getResponse()->getContent();
        // The Alpine studio shell, the source editor, and the deck data the client renders.
        self::assertStringContainsString('x-data="repurposeStudio()"', $html);
        self::assertStringContainsString('window.RP_STUDIO', $html);
        self::assertStringContainsString('id="rp-spec"', $html);
        // A rendered slide SVG is embedded in the config blob (tag-escaped for <script>).
        self::assertStringContainsString('\u003Csvg', $html);
    }

    public function testSaveEditedSpecPersistsNewCopy(): void
    {
        $post = $this->persistPost();

        $spec = $this->validSpec();
        $spec['slides'] = [['title' => 'A brand new headline', 'image' => ['media' => 'photo.jpg']]];
        $this->postJson('/admin/repurpose/studio/'.$post->id.'/save', $spec);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $reloaded = $this->em->getRepository(SocialPost::class)->find($post->id);
        self::assertInstanceOf(SocialPost::class, $reloaded);

        $slides = $reloaded->getSpec()['slides'] ?? null;
        self::assertIsArray($slides);
        $firstSlide = $slides[0] ?? null;
        self::assertIsArray($firstSlide);
        self::assertSame('A brand new headline', $firstSlide['title']);
    }

    public function testSavePinsNetworkButAllowsRelinkingThePage(): void
    {
        $post = $this->persistPost();

        $spec = $this->validSpec();
        $spec['page'] = 'blog/some-other-page';
        $spec['network'] = 'instagram'; // ignored — network is fixed, switched via its own route
        $this->postJson('/admin/repurpose/studio/'.$post->id.'/save', $spec);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $reloaded = $this->em->getRepository(SocialPost::class)->find($post->id);
        self::assertInstanceOf(SocialPost::class, $reloaded);
        self::assertSame('blog/some-other-page', $reloaded->getPage()); // page re-linked
        self::assertSame('linkedin', $reloaded->getNetwork());          // network pinned
    }

    public function testSaveRejectsRelinkingToAPageThatAlreadyHasACarousel(): void
    {
        $post = $this->persistPost();

        $other = new SocialPost();
        $other->host = self::HOST;

        $otherSpec = $this->validSpec();
        $otherSpec['page'] = 'blog/occupied';
        $other->setSpec($otherSpec);
        $this->em->persist($other);
        $this->em->flush();

        $spec = $this->validSpec();
        $spec['page'] = 'blog/occupied';
        $body = $this->postJson('/admin/repurpose/studio/'.$post->id.'/save', $spec);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertNotEmpty($body['violations']);

        $reloaded = $this->em->getRepository(SocialPost::class)->find($post->id);
        self::assertInstanceOf(SocialPost::class, $reloaded);
        self::assertSame('blog/studio-article', $reloaded->getPage()); // unchanged
    }

    public function testCreateStandaloneDraftOpensTheStudio(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/new');

        self::assertSame(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode());
        $location = (string) $this->client->getResponse()->headers->get('Location');
        self::assertMatchesRegularExpression('#/admin/repurpose/studio/\d+#', $location);

        preg_match('#/studio/(\d+)#', $location, $matches);
        $created = $this->em->getRepository(SocialPost::class)->find((int) ($matches[1] ?? 0));
        self::assertInstanceOf(SocialPost::class, $created);
        // A page-less draft: a generated standalone slug, not linked to any page.
        self::assertStringStartsWith('standalone/', $created->getPage());

        $this->em->remove($created); // created on the default host, outside tearDown's HOST scope
        $this->em->flush();
    }

    public function testSavingAStandaloneCarouselKeepsItsGeneratedSlug(): void
    {
        $post = new SocialPost();
        $post->host = self::HOST;

        $spec = $this->validSpec();
        $spec['page'] = 'standalone/deadbeef';
        $post->setSpec($spec);
        $this->em->persist($post);
        $this->em->flush();

        // The studio shows a standalone slug as a blank page field, so it saves with
        // page omitted; the server must restore the generated slug, not trip NotBlank.
        $edited = $this->validSpec();
        unset($edited['page']);
        $edited['slides'] = [['title' => 'Edited standalone']];
        $this->postJson('/admin/repurpose/studio/'.$post->id.'/save', $edited);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());

        $reloaded = $this->em->getRepository(SocialPost::class)->find($post->id);
        self::assertInstanceOf(SocialPost::class, $reloaded);
        self::assertSame('standalone/deadbeef', $reloaded->getPage()); // slug preserved
    }

    public function testSaveRejectsAnInvalidSpecWithViolations(): void
    {
        $post = $this->persistPost();

        // A linkedin post cannot use an instagram-only format.
        $spec = $this->validSpec();
        $spec['format'] = 'instagram-4-5';
        $body = $this->postJson('/admin/repurpose/studio/'.$post->id.'/save', $spec);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertNotEmpty($body['violations']);
    }

    public function testPreviewRendersSvgWithoutPersisting(): void
    {
        $post = $this->persistPost();

        $spec = $this->validSpec();
        $spec['slides'] = [['title' => 'Preview only'], ['title' => 'Second']];
        $body = $this->postJson('/admin/repurpose/studio/'.$post->id.'/preview', $spec);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $slides = $body['slides'] ?? null;
        self::assertIsArray($slides);
        self::assertCount(2, $slides);
        $first = $slides[0] ?? null;
        self::assertIsString($first);
        self::assertStringContainsString('<svg', $first);

        // Preview never touches the row: the stored spec still has its one slide.
        $reloaded = $this->em->getRepository(SocialPost::class)->find($post->id);
        self::assertInstanceOf(SocialPost::class, $reloaded);
        $stored = $reloaded->getSpec()['slides'] ?? null;
        self::assertIsArray($stored);
        self::assertCount(1, $stored);
    }

    public function testPreviewRejectsInvalidSpecWithViolations(): void
    {
        $post = $this->persistPost();

        $spec = $this->validSpec();
        $spec['format'] = 'instagram-4-5';
        $body = $this->postJson('/admin/repurpose/studio/'.$post->id.'/preview', $spec);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
        self::assertNotEmpty($body['violations']);
    }

    public function testSwitchNetworkClonesToANewSiblingCarousel(): void
    {
        $post = $this->persistPost();

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$post->id.'/network/instagram');

        self::assertSame(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode());

        $sibling = $this->em->getRepository(SocialPost::class)
            ->findOneBy(['host' => self::HOST, 'page' => 'blog/studio-article', 'network' => 'instagram']);
        self::assertInstanceOf(SocialPost::class, $sibling);
        self::assertNotSame($post->id, $sibling->id);
        // Format is retargeted to the network's primary one; the slides are carried over.
        self::assertSame('instagram-4-5', $sibling->getFormat());
        $siblingSlides = $sibling->getSpec()['slides'] ?? null;
        self::assertIsArray($siblingSlides);
        self::assertCount(1, $siblingSlides);
        self::assertStringContainsString('/admin/repurpose/studio/'.$sibling->id, (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testSwitchNetworkReusesAnExistingSibling(): void
    {
        $post = $this->persistPost();

        $instagram = new SocialPost();
        $instagram->host = self::HOST;

        $spec = $this->validSpec();
        $spec['network'] = 'instagram';
        $spec['format'] = 'instagram-4-5';
        $instagram->setSpec($spec);
        $this->em->persist($instagram);
        $this->em->flush();

        $before = \count($this->em->getRepository(SocialPost::class)->findBy(['host' => self::HOST]));

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$post->id.'/network/instagram');

        self::assertSame(Response::HTTP_FOUND, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('/admin/repurpose/studio/'.$instagram->id, (string) $this->client->getResponse()->headers->get('Location'));
        // No duplicate created — the existing sibling is reused.
        self::assertCount($before, $this->em->getRepository(SocialPost::class)->findBy(['host' => self::HOST]));
    }

    public function testSwitchNetworkRejectsAnUnknownNetwork(): void
    {
        $post = $this->persistPost();

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$post->id.'/network/tiktok');

        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    private function persistPost(): SocialPost
    {
        $post = new SocialPost();
        $post->host = self::HOST;
        $post->setSpec($this->validSpec());

        $this->em->persist($post);
        $this->em->flush();

        return $post;
    }

    /**
     * @return array<string, mixed>
     */
    private function validSpec(): array
    {
        return [
            'page' => 'blog/studio-article',
            'network' => 'linkedin',
            'format' => 'linkedin-4-5',
            'slides' => [
                ['title' => 'Original headline', 'image' => ['media' => 'photo.jpg']],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    private function postJson(string $url, array $body): array
    {
        $this->client->request(Request::METHOD_POST, $url, [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($body));

        $decoded = json_decode((string) $this->client->getResponse()->getContent(), true);
        if (! \is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
