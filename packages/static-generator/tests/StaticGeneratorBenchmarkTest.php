<?php

namespace Pushword\StaticGenerator;

use PHPUnit\Framework\Attributes\Group;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

#[Group('benchmark')]
class StaticGeneratorBenchmarkTest extends KernelTestCase
{
    private ?string $isolatedStaticDir = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->isolatedStaticDir = sys_get_temp_dir().'/pushword-bench-'.getmypid();
    }

    protected function tearDown(): void
    {
        if (null !== $this->isolatedStaticDir) {
            (new Filesystem())->remove($this->isolatedStaticDir);
        }

        parent::tearDown();
    }

    public function testBenchmark200Pages(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $siteRegistry = $container->get(SiteRegistry::class);
        $siteConfig = $siteRegistry->switchSite('localhost.dev')->get();
        $siteConfig->setCustomProperty('static_dir', $this->isolatedStaticDir);

        // Clean up PID file
        $pidFile = $container->getParameter('kernel.project_dir').'/var/static-generator.pid';
        (new Filesystem())->remove($pidFile);

        // Create 200 fixture pages
        $em = $container->get('doctrine.orm.entity_manager');
        for ($i = 1; $i <= 200; ++$i) {
            $page = new Page();
            $page->setH1('Benchmark Page '.$i);
            $page->setSlug('bench-page-'.$i);
            $page->setMainContent('<p>Content for benchmark page '.$i.'</p>');
            $page->host = 'localhost.dev';
            $page->locale = 'en';
            $page->createdAt = new \DateTime();
            $em->persist($page);
        }

        $em->flush();

        $application = new Application(static::$kernel); // @phpstan-ignore-line
        $command = $application->find('pw:static');
        $commandTester = new CommandTester($command);

        $memBefore = memory_get_usage(true);
        $start = microtime(true);

        $commandTester->execute(['host' => 'localhost.dev']);

        $elapsed = microtime(true) - $start;
        $memPeak = memory_get_peak_usage(true);

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('success', $output, 'Benchmark generation failed: '.$output);

        // Count generated HTML files
        $staticDir = $this->isolatedStaticDir ?? throw new \LogicException('isolatedStaticDir not set');
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($staticDir, \FilesystemIterator::SKIP_DOTS),
        );
        $pageCount = 0;
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && 'html' === $file->getExtension()) {
                ++$pageCount;
            }
        }

        $pagesPerSecond = $pageCount / $elapsed;

        fwrite(\STDERR, \sprintf(
            "\n[BENCHMARK] %d pages in %.2fs (%.1f pages/sec) | Memory: %.1fMB peak, %.1fMB delta\n",
            $pageCount,
            $elapsed,
            $pagesPerSecond,
            $memPeak / 1024 / 1024,
            ($memPeak - $memBefore) / 1024 / 1024,
        ));

        // Sanity: at least the 200 bench pages + existing fixtures were generated
        self::assertGreaterThanOrEqual(200, $pageCount);
    }
}
