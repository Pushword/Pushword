<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Repurpose\Entity\SocialPost;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('integration')]
final class RepurposeLinksTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $em;

    private User $user;

    private Page $page;

    private SocialPost $post;

    /** @var list<Page|SocialPost> */
    private array $entities = [];

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->client->disableReboot();

        $this->em = self::getContainer()->get('doctrine.orm.default_entity_manager');

        /** @var class-string<User> $userClass */
        $userClass = self::getContainer()->getParameter('pw.entity_user');
        $this->user = new $userClass();
        $this->user->email = 'repurpose-links-'.uniqid().'@example.com';
        $this->user->setPassword('hashed-password');
        $this->user->setRoles(['ROLE_SUPER_ADMIN']);

        $this->em->persist($this->user);
        $this->page = $this->persistPage('blog/repurpose-links-'.uniqid());
        $this->post = $this->persistPost($this->page);
        $this->client->loginUser($this->user);
    }

    protected function tearDown(): void
    {
        $em = $this->client->getContainer()->get('doctrine.orm.default_entity_manager');
        foreach (array_reverse($this->entities) as $entity) {
            $managed = $em->find($entity::class, $entity->id);
            if (null !== $managed) {
                $em->remove($managed);
            }
        }

        $user = $em->find($this->user::class, $this->user->id);
        if (null !== $user) {
            $em->remove($user);
        }

        $em->flush();
        parent::tearDown();
    }

    public function testPageEditorLinksDirectlyToItsOnlyCarousel(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/page/'.$this->page->id.'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.action-repurpose', 'Linked carousels');
        $this->client->request(Request::METHOD_GET, (string) $crawler->filter('.action-repurpose')->attr('href'));

        self::assertResponseRedirects('/admin/repurpose/studio/'.$this->post->id);
    }

    public function testStudioLinksBackToTheSourceEditor(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$this->post->id);

        self::assertResponseIsSuccessful();
        $link = $crawler->selectLink('Edit source page');
        self::assertCount(1, $link);
        $this->client->request(Request::METHOD_GET, (string) $link->attr('href'));

        self::assertResponseRedirects('/admin/page/'.$this->page->id.'/edit');
    }

    public function testMultipleCarouselsAreFilteredByExactHostAndSlug(): void
    {
        $second = $this->persistPost($this->page, 'instagram');
        $otherHost = $this->persistPage($this->page->slug, 'repurpose-other.example');
        $otherHostPost = $this->persistPost($otherHost);
        $otherSlug = $this->persistPage($this->page->slug.'-another');
        $otherSlugPost = $this->persistPost($otherSlug);

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/from-page/'.$this->page->id);

        self::assertResponseRedirects();
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('tbody tr[data-id]'));
        foreach ([$this->post, $second] as $post) {
            self::assertSelectorExists('tr[data-id="'.$post->id.'"]');
        }

        foreach ([$otherHostPost, $otherSlugPost] as $post) {
            self::assertSelectorNotExists('tr[data-id="'.$post->id.'"]');
        }
    }

    public function testPageWithoutACarouselRetainsTheCreationAction(): void
    {
        $page = $this->persistPage('blog/no-carousel-'.uniqid());
        $otherHost = $this->persistPage($page->slug, 'repurpose-other.example');
        $this->persistPost($otherHost);
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/page/'.$page->id.'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.action-repurpose', 'Repurpose');
        $this->client->request(Request::METHOD_GET, (string) $crawler->filter('.action-repurpose')->attr('href'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Create a carousel draft from this page?');
        self::assertSame(0, $this->em->getRepository(SocialPost::class)->count(['host' => $page->host, 'page' => $page->slug]));
    }

    public function testSourceLinkUsesTheCurrentStoredPage(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$this->post->id);
        $href = (string) $crawler->selectLink('Edit source page')->attr('href');
        $newPage = $this->persistPage('blog/new-source-'.uniqid());
        $this->post->spec = [...$this->post->spec, 'page' => $newPage->slug];
        $this->em->flush();

        $this->client->request(Request::METHOD_GET, $href);

        self::assertResponseRedirects('/admin/page/'.$newPage->id.'/edit');
    }

    public function testSourceResolutionUsesTheHost(): void
    {
        $other = $this->persistPage($this->page->slug, 'repurpose-other.example');
        $this->post->host = $other->host;
        $this->em->flush();

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/source-page/'.$this->post->id);

        self::assertResponseRedirects('/admin/page/'.$other->id.'/edit');
    }

    public function testMissingSourceIsNotLinked(): void
    {
        $this->post->spec = [...$this->post->spec, 'page' => 'missing-source-'.uniqid()];
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$this->post->id);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->selectLink('Edit source page'));

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/source-page/'.$this->post->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testStandaloneIsNotLinkedEvenWhenItsSlugMatchesAPage(): void
    {
        $standalone = $this->persistPage('standalone/'.uniqid());
        $this->post->spec = [...$this->post->spec, 'page' => $standalone->slug];
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$this->post->id);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->selectLink('Edit source page'));

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/source-page/'.$this->post->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testSourceLinkRequiresAuthentication(): void
    {
        $this->client->getCookieJar()->clear();
        $this->client->request(Request::METHOD_GET, '/admin/repurpose/source-page/'.$this->post->id);

        self::assertResponseRedirects();
        self::assertStringContainsString('/login', (string) $this->client->getResponse()->headers->get('Location'));
    }

    public function testEmptySourceIsNotLinked(): void
    {
        $this->post->spec = [...$this->post->spec, 'page' => ''];
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$this->post->id);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->selectLink('Edit source page'));

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/source-page/'.$this->post->id);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testMissingCarouselReturnsNotFound(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin/repurpose/source-page/2147483647');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testSourceLinkRequiresTheAdminRole(): void
    {
        $this->user->setRoles(['ROLE_USER']);
        $this->em->flush();
        $this->client->loginUser($this->user);

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/source-page/'.$this->post->id);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function persistPage(string $slug, string $host = 'localhost.dev'): Page
    {
        /** @var class-string<Page> $pageClass */
        $pageClass = self::getContainer()->getParameter('pw.entity_page');
        $page = new $pageClass();
        $page->slug = $slug;
        $page->host = $host;
        $page->h1 = 'Source article';
        $page->mainContent = 'Article content.';

        $this->em->persist($page);
        $this->em->flush();
        $this->entities[] = $page;

        return $page;
    }

    private function persistPost(Page $page, string $network = 'linkedin'): SocialPost
    {
        $post = new SocialPost();
        $post->host = $page->host;
        $post->spec = [
            'page' => $page->slug,
            'network' => $network,
            'format' => 'instagram' === $network ? 'instagram-1-1' : 'linkedin-4-5',
            'slides' => [['title' => 'Article cover']],
        ];
        $this->em->persist($post);
        $this->em->flush();
        $this->entities[] = $post;

        return $post;
    }
}
