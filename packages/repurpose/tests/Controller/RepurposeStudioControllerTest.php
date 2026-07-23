<?php

namespace Pushword\Repurpose\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Service\VideoBuilder;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

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
        // The deck previews at the network's mobile-feed width and says so.
        self::assertStringContainsString('width: 390px', $html);
        self::assertStringContainsString('linkedin mobile feed width', $html);
        // The byline picker offers the host's configured creators, not a blind
        // free-text key (the test host resolves to the default app's config).
        self::assertStringContainsString('"creators":{"robin":"Robin"}', $html);
        self::assertStringContainsString('— brand —', $html);
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
        // Contrast advisories ride along so the studio can surface them.
        self::assertIsArray($body['warnings'] ?? null);

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

    public function testExportSvgReturnsAZipOfVectorSlides(): void
    {
        $post = $this->persistPost();

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';
        $this->client->request(
            Request::METHOD_POST,
            '/admin/repurpose/studio/'.$post->id.'/export',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['svgs' => [$svg]]),
        );

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/zip', $response->headers->get('Content-Type'));
        self::assertStringContainsString('-svg.zip', (string) $response->headers->get('Content-Disposition'));

        $tmp = (string) tempnam(sys_get_temp_dir(), 'pw-studio-svg-');
        file_put_contents($tmp, (string) $response->getContent());
        $zip = new ZipArchive();
        self::assertTrue($zip->open($tmp));
        // The SVG is bundled verbatim alongside the caption — no raster artifacts.
        self::assertSame($svg, $zip->getFromName('slide-1.svg'));
        self::assertNotFalse($zip->getFromName('caption.txt'));
        self::assertFalse($zip->getFromName('slide-1.png'));
        $zip->close();
        @unlink($tmp);
    }

    public function testExportVideoReturnsAnMp4OrWarnsWhenFfmpegIsMissing(): void
    {
        $post = $this->persistPost();

        $this->client->request(
            Request::METHOD_POST,
            '/admin/repurpose/studio/'.$post->id.'/export-video',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['slides' => [$this->pngDataUrl(), $this->pngDataUrl()]]),
        );

        $response = $this->client->getResponse();
        if (Response::HTTP_OK === $response->getStatusCode()) {
            // ffmpeg on the host: a real MP4 (the 'ftyp' box sits at offset 4).
            self::assertSame('video/mp4', $response->headers->get('Content-Type'));
            self::assertStringContainsString('.mp4', (string) $response->headers->get('Content-Disposition'));
            self::assertSame('ftyp', substr((string) $response->getContent(), 4, 4));
        } else {
            // No ffmpeg: an honest install hint, not a broken file.
            self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
            self::assertStringContainsStringIgnoringCase('ffmpeg', (string) $response->getContent());
        }
    }

    public function testExportVideoRejectsAMissingSlidesPayload(): void
    {
        $post = $this->persistPost();

        // Request-shape errors are a 400 regardless of ffmpeg (validated first).
        $this->postJson('/admin/repurpose/studio/'.$post->id.'/export-video', ['notSlides' => 1]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testExportVideoRejectsSlidesThatDecodeToNothing(): void
    {
        $post = $this->persistPost();

        // A slide whose base64 payload cannot be decoded leaves nothing to encode.
        $this->postJson('/admin/repurpose/studio/'.$post->id.'/export-video', ['slides' => ['data:image/png;base64,@@@@']]);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testExportVideoWarnsWhenFfmpegIsUnavailable(): void
    {
        $post = $this->persistPost();

        // Force the "no ffmpeg" branch on any host by pointing the builder at a
        // binary that does not exist.
        self::getContainer()->set(VideoBuilder::class, new VideoBuilder('/nonexistent/ffmpeg-binary'));

        $this->client->request(
            Request::METHOD_POST,
            '/admin/repurpose/studio/'.$post->id.'/export-video',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode(['slides' => [$this->pngDataUrl()]]),
        );

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsStringIgnoringCase('ffmpeg', (string) $this->client->getResponse()->getContent());
    }

    public function testPinImageStoresAPublicPngAndReturnsAPinterestUrl(): void
    {
        $post = $this->persistPinterestPost();

        // A 1×1 transparent PNG as the browser would post it.
        $png = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        $body = $this->postJson('/admin/repurpose/studio/'.$post->id.'/pin-image', ['png' => $png]);

        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertIsString($body['url'] ?? null);
        self::assertStringContainsString('/repurpose-pin/'.$post->id.'.png', $body['url']);
        self::assertIsString($body['pinUrl'] ?? null);
        self::assertStringStartsWith('https://www.pinterest.com/pin/create/button/?', $body['pinUrl']);
        self::assertStringContainsString('A+caption+to+pin', $body['pinUrl']);

        // The PNG landed under the web root where Pinterest can fetch it.
        $publicDir = self::getContainer()->getParameter('pw.public_dir');
        $file = $publicDir.'/repurpose-pin/'.$post->id.'.png';
        self::assertFileExists($file);
        @unlink($file);
    }

    public function testPinImageRejectsANonPinterestNetwork(): void
    {
        $post = $this->persistPost(); // a linkedin carousel

        $this->postJson('/admin/repurpose/studio/'.$post->id.'/pin-image', ['png' => 'data:image/png;base64,AAAA']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testPinImageRejectsAMissingPng(): void
    {
        $post = $this->persistPinterestPost();

        $this->postJson('/admin/repurpose/studio/'.$post->id.'/pin-image', ['notPng' => 'x']);

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    /**
     * A real PNG as a `data:` URL, as the studio's canvas rasteriser would POST it.
     * ffmpeg upscales it to the carousel format — content is irrelevant, only that
     * it decodes.
     */
    private function pngDataUrl(): string
    {
        $image = imagecreatetruecolor(16, 20);

        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();

        return 'data:image/png;base64,'.base64_encode($bytes);
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

    private function persistPinterestPost(): SocialPost
    {
        $post = new SocialPost();
        $post->host = self::HOST;
        $post->setSpec([
            'page' => 'blog/studio-article', 'network' => 'pinterest', 'format' => 'pinterest-2-3',
            'caption' => 'A caption to pin',
            'slides' => [['title' => 'Cover', 'image' => ['media' => 'photo.jpg']]],
        ]);

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
