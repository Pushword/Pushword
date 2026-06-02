<?php

namespace Pushword\Snippet\Tests\Perf;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Logging\Middleware as LoggingMiddleware;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\AbstractLogger;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Snippet\Repository\SnippetRepository;
use ReflectionProperty;
use Stringable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Structural regression guard for the SnippetRepository slug cache. A page can
 * reference many snippets, each through a `snippet()` Twig call resolving via
 * findOneBySlugForHost(); without the in-memory cache that was up to 2 queries
 * per snippet (host-specific then global). Counts SQL statements via a DBAL
 * logging middleware and fails if a refactor reintroduces the N+1 lookup.
 */
#[Group('integration')]
final class SnippetQueryRegressionTest extends KernelTestCase
{
    private EntityManager $em;

    private SnippetRepository $repo;

    private SnippetQueryCounter $counter;

    private DriverInterface $originalDriver;

    private string $host = 'localhost.dev';

    /** @var list<string> */
    private array $slugs = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');
        $this->em = $em;

        $this->repo = $em->getRepository(Snippet::class);

        // Seed a handful of host snippets plus a global one to exercise both
        // the host lookup and the global fallback path.
        for ($i = 0; $i < 10; ++$i) {
            $slug = 'perf-snippet-'.$i.'-'.uniqid();
            $this->slugs[] = $slug;
            $snippet = new Snippet();
            $snippet->host = $this->host;
            $snippet->setSlug($slug);
            $snippet->setName('Perf '.$i);
            $snippet->setContent('body '.$i);
            $em->persist($snippet);
        }

        $globalSlug = 'perf-global-'.uniqid();
        $this->slugs[] = $globalSlug;
        $global = new Snippet();
        $global->host = '';
        $global->setSlug($globalSlug);
        $global->setName('Global');
        $global->setContent('global body');

        $em->persist($global);

        $em->flush();
        $em->clear();

        $this->counter = new SnippetQueryCounter();

        // Wrap the live DBAL driver so every subsequent statement is counted.
        $conn = $this->em->getConnection();
        $driverProp = new ReflectionProperty($conn, 'driver');
        /** @var DriverInterface $original */
        $original = $driverProp->getValue($conn);
        $this->originalDriver = $original;

        $wrapped = new LoggingMiddleware($this->counter)->wrap($original);
        $driverProp->setValue($conn, $wrapped);
        $conn->close();
    }

    protected function tearDown(): void
    {
        if (isset($this->em)) {
            $conn = $this->em->getConnection();
            $driverProp = new ReflectionProperty($conn, 'driver');
            $driverProp->setValue($conn, $this->originalDriver);
            $conn->close();

            $this->em->clear();
            foreach ($this->slugs as $slug) {
                $snippet = $this->repo->findOneBy(['slug' => Snippet::normalizeSlug($slug)]);
                if (null !== $snippet) {
                    $this->em->remove($snippet);
                }
            }

            $this->em->flush();
        }

        $this->slugs = [];

        parent::tearDown();
    }

    public function testResolvingManySnippetsDoesNotGrowQueriesLinearly(): void
    {
        $before = $this->counter->count;

        foreach ($this->slugs as $slug) {
            $this->repo->findOneBySlugForHost($slug, $this->host);
        }

        // Two warmups at most: one for the host, one for the global fallback.
        // Independent of how many snippets the page references.
        self::assertLessThanOrEqual(
            2,
            $this->counter->count - $before,
            'resolving '.\count($this->slugs).' snippets must not scale queries with snippet count',
        );
    }

    public function testRepeatedLookupAfterWarmupEmitsZeroQueries(): void
    {
        // Warm both caches first.
        $this->repo->findOneBySlugForHost($this->slugs[0], $this->host);
        $this->repo->findOneBySlugForHost('missing-'.uniqid(), $this->host);

        $before = $this->counter->count;

        for ($i = 0; $i < 50; ++$i) {
            $this->repo->findOneBySlugForHost($this->slugs[0], $this->host);
            $this->repo->findOneBySlugForHost('never-exists-'.$i, $this->host);
        }

        self::assertSame(
            0,
            $this->counter->count - $before,
            'findOneBySlugForHost must not hit the DB after the host is warmed',
        );
    }
}

/**
 * PSR logger that counts DBAL "Executing statement" log messages.
 */
final class SnippetQueryCounter extends AbstractLogger
{
    public int $count = 0;

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $msg = (string) $message;
        if (! str_starts_with($msg, 'Executing statement') && ! str_starts_with($msg, 'Executing query')) {
            return;
        }

        ++$this->count;
    }
}
