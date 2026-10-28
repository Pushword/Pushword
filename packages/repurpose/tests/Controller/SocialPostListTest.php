<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\User;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\CreatorResolverInterface;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class SocialPostListTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private User $user;

    private SocialPost $post;

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $this->user = new $userClass();
        $this->user->email = 'social-post-list-'.uniqid().'@example.com';
        $this->user->setPassword('hashed-password');
        $this->user->setRoles(['ROLE_ADMIN']);

        $this->em->persist($this->user);

        $this->post = new SocialPost();
        $this->post->host = 'social-post-list.example';
        $this->post->spec = [
            'page' => 'blog/cover-'.uniqid(),
            'network' => 'linkedin',
            'format' => 'linkedin-4-5',
            'slides' => [['title' => 'The cover'], ['title' => 'The second slide']],
        ];
        $this->em->persist($this->post);
        $this->em->flush();

        $this->client->loginUser($this->user);
    }

    protected function tearDown(): void
    {
        $em = $this->client->getContainer()->get('doctrine.orm.default_entity_manager');
        foreach ([$em->find(SocialPost::class, $this->post->id), $em->find($this->user::class, $this->user->id)] as $entity) {
            if (null !== $entity) {
                $em->remove($entity);
            }
        }

        $em->flush();
        parent::tearDown();
    }

    public function testListPutsStatusFirstAndOffersCoverPreview(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/social-post', ['query' => $this->post->page]);

        self::assertResponseIsSuccessful();
        self::assertSame('Status', trim($crawler->filter('thead th[data-column]')->first()->text()));
        $row = $crawler->filter('tr[data-id="'.$this->post->id.'"]');
        self::assertCount(1, $row);
        self::assertSame('status', $row->filter('td[data-column]')->first()->attr('data-column'));
        $trigger = $row->filter('button[data-bs-toggle="modal"]');
        self::assertSame('Preview the cover of '.$this->post->page.' · linkedin', $trigger->attr('aria-label'));
        self::assertSame('/admin/repurpose/'.$this->post->id.'/cover.svg', $trigger->filter('img')->attr('src'));
        $modal = $crawler->filter((string) $trigger->attr('data-bs-target'));
        self::assertSame($modal->filter('h2')->attr('id'), $modal->attr('aria-labelledby'));
        self::assertCount(2, $modal->filter('[data-bs-dismiss="modal"]'));
        self::assertSame('/admin/repurpose/studio/'.$this->post->id, $modal->filter('a')->attr('href'));
        self::assertCount(1, $row->filter('td.actions .action-edit'));
        self::assertCount(0, $row->filter('td.actions.actions-as-dropdown'));
    }

    public function testCoverMatchesTheCanonicalFirstSlideWithoutChangingThePost(): void
    {
        $spec = $this->post->spec;
        $container = self::getContainer();
        $carousel = $container->get(CarouselFactory::class)->fromArray($spec);
        $creator = $container->get(CreatorResolverInterface::class)->resolve($carousel, $this->post->host);
        $expected = $container->get(SlideRenderer::class)->renderSlide($carousel, 0, $creator);

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/'.$this->post->id.'/cover.svg');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSame($expected, $this->client->getResponse()->getContent());
        $this->em->refresh($this->post);
        self::assertSame($spec, $this->post->spec);
    }

    public function testEmptyCarouselHasNoPreviewTrigger(): void
    {
        $this->post->spec = [...$this->post->spec, 'slides' => []];
        $this->em->flush();

        $this->client->request(Request::METHOD_GET, '/admin/social-post', ['query' => $this->post->page]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('td[data-column="id"]', 'No slides');
        self::assertSelectorNotExists('button[data-bs-target^="#repurpose-cover-"]');

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/'.$this->post->id.'/cover.svg');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCoverReflectsSavedEdits(): void
    {
        $url = '/admin/repurpose/'.$this->post->id.'/cover.svg';
        $this->client->request(Request::METHOD_GET, $url);
        self::assertResponseIsSuccessful();
        $original = $this->client->getResponse()->getContent();

        $this->post->spec = [...$this->post->spec, 'slides' => [['title' => 'The updated cover']]];
        $this->em->flush();
        $this->client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertNotSame($original, $this->client->getResponse()->getContent());
        $svg = new Crawler((string) $this->client->getResponse()->getContent());
        $text = $svg->filter('text')->each(static fn (Crawler $node): string => $node->text());
        self::assertStringContainsString('The updated cover', implode(' ', $text));
    }

    public function testMissingCarouselReturnsNotFound(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin/repurpose/2147483647/cover.svg');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCoverRequiresAuthentication(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request(Request::METHOD_GET, '/admin/repurpose/'.$this->post->id.'/cover.svg');

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testCoverRequiresTheAdminRole(): void
    {
        $this->user->setRoles(['ROLE_USER']);
        $this->em->flush();
        $this->client->loginUser($this->user);

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/'.$this->post->id.'/cover.svg');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
