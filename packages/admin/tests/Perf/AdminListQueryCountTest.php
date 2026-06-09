<?php

namespace Pushword\Admin\Tests\Perf;

use DateTime;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Pushword\Admin\Tests\AbstractAdminTestClass;
use Pushword\Core\Cache\PageCacheSuppressor;
use Pushword\Core\Entity\Page;
use Pushword\Core\Tests\Perf\QueryCountingTrait;
use Symfony\Component\HttpFoundation\Request;

/**
 * Structural regression guard for the EasyAdmin page list. The list is paginated
 * (default 10 rows), so the number of SQL statements it issues must stay flat as
 * the corpus grows — a count that climbs with the total number of pages means a
 * query scales with the corpus (e.g. a filter dropdown hydrating every entity, or
 * a per-row lazy association). Counts statements via a DBAL logging middleware
 * wrapped around the live connection.
 */
#[Group('integration')]
final class AdminListQueryCountTest extends AbstractAdminTestClass
{
    use QueryCountingTrait;

    /** @var list<int> */
    private array $seededIds = [];

    protected function tearDown(): void
    {
        $this->stopCountingQueries();
        $this->removeSeededPages();
        parent::tearDown();
    }

    public function testPageListQueryCountIsInvariantToCorpusSize(): void
    {
        $client = $this->loginUser();
        // Keep the same kernel (and thus the same wrapped connection) across the
        // measured requests — KernelBrowser reboots between requests by default.
        $client->disableReboot();

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();

        $homepage = $em->getRepository(Page::class)->findOneBy(['slug' => 'homepage']);
        self::assertNotNull($homepage, 'fixture page with slug=homepage is required');
        $host = $homepage->host;

        // Fill well past one page so the list always renders a full page of rows.
        $this->seedPages($em, $host, 30);

        $listUrl = $this->generateAdminUrl('admin_page_list');

        // Warm one-time, corpus-independent caches (e.g. the persisted media
        // filename index) so both measured requests share the same cache state —
        // otherwise the first request alone pays the warmup and skews the count.
        $client->request(Request::METHOD_GET, $listUrl);
        self::assertResponseIsSuccessful();

        $this->startCountingQueries($connection);

        $baseline = $this->countQueries(static function () use ($client, $listUrl): void {
            $client->request(Request::METHOD_GET, $listUrl);
            self::assertResponseIsSuccessful();
        });

        // Scale the corpus ~5x; the rendered page still shows the same 10 rows.
        $this->seedPages($em, $host, 120);

        $scaled = $this->countQueries(static function () use ($client, $listUrl): void {
            $client->request(Request::METHOD_GET, $listUrl);
            self::assertResponseIsSuccessful();
        });

        fwrite(\STDERR, \sprintf(
            "\n[PERF] admin page list query count: %d (30 pages) vs %d (150 pages)\n",
            $baseline,
            $scaled,
        ));

        self::assertSame(
            $baseline,
            $scaled,
            'admin page list must not issue more queries as the corpus grows (corpus-scaling query or per-row N+1)',
        );
    }

    private function seedPages(EntityManager $em, string $host, int $count): void
    {
        self::getContainer()->get(PageCacheSuppressor::class)->suppress(function () use ($em, $host, $count): void {
            $offset = \count($this->seededIds);
            $pages = [];
            for ($i = 0; $i < $count; ++$i) {
                $page = new Page();
                $page->setH1('Admin list bench '.($offset + $i));
                $page->setSlug('admin-list-bench-'.($offset + $i));
                $page->host = $host;
                $page->locale = 'en';
                $page->createdAt = new DateTime();
                $page->updatedAt = new DateTime();
                $page->setMainContent('admin list bench content '.($offset + $i));
                $em->persist($page);
                $pages[] = $page;
            }

            $em->flush();

            foreach ($pages as $page) {
                if (null !== $page->id) {
                    $this->seededIds[] = $page->id;
                }
            }
        });
    }

    private function removeSeededPages(): void
    {
        if ([] === $this->seededIds) {
            return;
        }

        /** @var EntityManager $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $repo = $em->getRepository(Page::class);
        foreach ($this->seededIds as $id) {
            $page = $repo->find($id);
            if (null !== $page) {
                $em->remove($page);
            }
        }

        $em->flush();
        $this->seededIds = [];
    }
}
