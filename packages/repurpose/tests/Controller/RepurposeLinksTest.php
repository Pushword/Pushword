<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Tests\Controller;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\FormField\Event as FormEvent;
use Pushword\Core\Entity\Media;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Pushword\Core\Repository\MediaRepository;
use Pushword\Repurpose\Entity\SocialPost;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
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

    public function testPageEditorShowsItsCarouselsInTheSidebar(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/page/'.$this->page->id.'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.content-header .action-repurpose');
        self::assertSelectorTextContains('.columnFields', 'Linked carousels (1)');
        $link = $crawler->filter('.columnFields .rp-page-carousels a');
        self::assertCount(1, $link);
        self::assertSame('/admin/repurpose/studio/'.$this->post->id, $link->attr('href'));
        self::assertCount(1, $link->filter('img'));
        self::assertStringContainsString('Article cover', $link->text());
        self::assertStringContainsString('linkedin', $link->text());
        self::assertSelectorNotExists('.rp-page-carousels input, .rp-page-carousels select, .rp-page-carousels textarea');
        $this->client->request(Request::METHOD_GET, '/admin/repurpose/from-page/'.$this->page->id);

        self::assertResponseRedirects('/admin/repurpose/studio/'.$this->post->id);
    }

    public function testStudioLinksBackToTheSourceEditor(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$this->post->id);

        self::assertResponseIsSuccessful();
        $link = $crawler->filter('aside .rp-source-page a');
        self::assertCount(1, $link);
        self::assertSelectorNotExists('header a[href*="/repurpose/source-page/"]');
        $this->client->request(Request::METHOD_GET, (string) $link->attr('href'));

        self::assertResponseRedirects('/admin/page/'.$this->page->id.'/edit');
    }

    public function testEmptyCarouselRemainsLinkedWithoutACover(): void
    {
        $this->post->spec = [...$this->post->spec, 'slides' => []];
        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/page/'.$this->page->id.'/edit');

        self::assertResponseIsSuccessful();
        $link = $crawler->filter('.rp-page-carousels a[data-carousel-id="'.$this->post->id.'"]');
        self::assertCount(1, $link);
        self::assertSame('/admin/repurpose/studio/'.$this->post->id, $link->attr('href'));
        self::assertStringContainsString($this->page->slug, $link->text());
        self::assertCount(0, $link->filter('img'));
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
        self::assertSelectorNotExists('.rp-page-carousels');
        $this->client->request(Request::METHOD_GET, (string) $crawler->filter('.action-repurpose')->attr('href'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Create a carousel draft from this page?');
        self::assertSame(0, $this->em->getRepository(SocialPost::class)->count(['host' => $page->host, 'page' => $page->slug]));
    }

    public function testSourceLinkUsesTheCurrentStoredPage(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$this->post->id);
        $href = (string) $crawler->filter('aside .rp-source-page a')->attr('href');
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
        self::assertCount(0, $crawler->filter('.rp-source-page'));

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
        self::assertCount(0, $crawler->filter('.rp-source-page'));

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
        self::assertCount(0, $crawler->filter('.rp-source-page'));

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

    public function testPageListShowsOnlyPositiveCountsForEachSource(): void
    {
        $this->persistPost($this->page, 'instagram');
        $otherHost = $this->persistPage($this->page->slug, 'repurpose-other.example');
        $this->persistPost($otherHost);
        $empty = $this->persistPage('blog/no-carousel-'.uniqid());

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/page', ['query' => 'Source article']);

        self::assertResponseIsSuccessful();
        $link = $crawler->filter('tr[data-id="'.$this->page->id.'"] .rp-page-carousels-link');
        self::assertCount(1, $link);
        self::assertSame('Linked carousels (2)', trim($link->text()));
        self::assertSame('/admin/repurpose/from-page/'.$this->page->id, $link->attr('href'));
        self::assertSelectorTextSame('tr[data-id="'.$otherHost->id.'"] .rp-page-carousels-link', 'Linked carousels (1)');
        self::assertSelectorExists('tr[data-id="'.$empty->id.'"]');
        self::assertSelectorNotExists('tr[data-id="'.$empty->id.'"] .rp-page-carousels-link');
    }

    public function testSidebarCountRefreshesAfterACarouselIsAdded(): void
    {
        $this->client->request(Request::METHOD_GET, '/admin/page/'.$this->page->id.'/edit');
        self::assertSelectorTextContains('.columnFields', 'Linked carousels (1)');

        $this->persistPost($this->page, 'instagram');
        $this->client->request(Request::METHOD_GET, '/admin/page/'.$this->page->id.'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.columnFields', 'Linked carousels (2)');
        self::assertSelectorCount(2, '.rp-page-carousels a[data-carousel-id]');
    }

    public function testPageCanBeSavedWithLinkedCarousels(): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/page/'.$this->page->id.'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.rp-page-carousels');
        $form = $crawler->filter('form[name="Page"]')->form();
        $form['Page[h1]'] = 'Updated source article';

        $this->client->submit($form);

        self::assertResponseRedirects();
        self::assertSame('Updated source article', $this->em->getConnection()->fetchOne('SELECT h1 FROM page WHERE id = ?', [$this->page->id]));
        self::assertSame(1, $this->em->getRepository(SocialPost::class)->count(['host' => $this->page->host, 'page' => $this->page->slug]));
    }

    public function testSidebarWorksWithoutConfiguredGroups(): void
    {
        self::getContainer()->get(EventDispatcherInterface::class)->addListener(FormEvent::NAME, static function (FormEvent $event): void {
            $fields = $event->getFields();
            $fields[1] = [];
            $event->setFields($fields);
        }, 100);

        $this->client->request(Request::METHOD_GET, '/admin/page/'.$this->page->id.'/edit');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.columnFields', 'Linked carousels (1)');
        self::assertSelectorCount(1, '.rp-page-carousels a[data-carousel-id]');
    }

    public function testStudioSourceCardShowsTheArticleImageAndPublicationDate(): void
    {
        $media = self::getContainer()->get(MediaRepository::class)->findOneByFileNameOrHistory('piedweb-logo.png');
        self::assertInstanceOf(Media::class, $media);
        $publishedAt = new DateTime('2025-06-04T10:30:00+00:00');
        $this->page->mainImage = $media;
        $this->page->publishedAt = $publishedAt;

        $this->em->flush();

        $crawler = $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$this->post->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('aside .rp-source-page .source-page__title', 'Source article');
        $card = $crawler->filter('aside .rp-source-page');
        self::assertSame((string) $this->page->id, $card->attr('data-source-page-id'));
        self::assertCount(1, $card->filter('img'));
        self::assertStringContainsString('piedweb-logo', (string) $card->filter('img')->attr('src'));
        $date = new DateTimeImmutable((string) $card->filter('time')->attr('datetime'));
        self::assertSame($publishedAt->getTimestamp(), $date->getTimestamp());
        self::assertStringContainsString($this->page->host, $card->text());
        self::assertStringContainsString($this->page->slug, $card->text());
    }

    public function testUnpublishedSourceWithoutAnImageHasNoEmptyPreview(): void
    {
        $this->page->publishedAt = null;
        $this->page->mainImage = null;

        $this->em->flush();

        $this->client->request(Request::METHOD_GET, '/admin/repurpose/studio/'.$this->post->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('aside .rp-source-page', 'Not published');
        self::assertSelectorNotExists('.rp-source-page img, .rp-source-page time');
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
