<?php

namespace Pushword\StaticGenerator;

use DateTime;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Pushword\Core\Component\App\AppPool;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;

use function Safe\realpath;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;

class StaticGeneratorTest extends KernelTestCase
{
    private ?StaticAppGenerator $staticAppGenerator = null;

    public function testStaticCommand(): void
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pw:static:generate');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['localhost.dev']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        self::assertTrue(str_contains($output, 'success'));

        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/.htaccess');
        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/.Caddyfile');
        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/index.html');
        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/index.html.zst');
        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/index.html.br');
        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/index.html.gz');
        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/robots.txt');
        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/favicon.ico');

        $staticDir = __DIR__.'/../../skeleton/static/localhost.dev';
        $filesystem = new Filesystem();
        $filesystem->remove($staticDir);
    }

    private function getStaticAppGenerator(): StaticAppGenerator
    {
        if (null !== $this->staticAppGenerator) {
            return $this->staticAppGenerator;
        }

        $generatorBag = $this->getGeneratorBag();

        $container = self::getContainer();
        $logger = $container->get(LoggerInterface::class);

        return $this->staticAppGenerator = new StaticAppGenerator(
            self::getContainer()->get(AppPool::class),
            $generatorBag,
            $generatorBag->get(RedirectionManager::class), // @phpstan-ignore-line
            $logger,
        );
    }

    public function testIt(): void
    {
        self::bootKernel();

        $this->getStaticAppGenerator()->generate('localhost.dev');

        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev');

        $staticDir = __DIR__.'/../../skeleton/static/localhost.dev';
        $filesystem = new Filesystem();
        $filesystem->remove($staticDir);
        $filesystem->mkdir($staticDir);
    }

    private function getGenerator(string $name): GeneratorInterface
    {
        return $this->getGeneratorBag()->get($name)->setStaticAppGenerator($this->getStaticAppGenerator());
    }

    public function testGenerateHtaccess(): void
    {
        self::bootKernel();

        $generator = $this->getGenerator(HtaccessGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/.htaccess');
    }

    public function testGenerateCNAME(): void
    {
        self::bootKernel();

        $generator = $this->getGenerator(CNAMEGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/CNAME');
    }

    public function testCopier(): void
    {
        self::bootKernel();

        $generator = $this->getGenerator(CopierGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/assets');
    }

    public function testError(): void
    {
        self::bootKernel();

        $generator = $this->getGenerator(ErrorPageGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/404.html');
    }

    public function testDownload(): void
    {
        self::bootKernel();

        $generator = $this->getGenerator(MediaGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/media');
    }

    public function testPages(): void
    {
        self::bootKernel();

        $generator = $this->getGenerator(PagesGenerator::class);

        $generator->generate('localhost.dev');

        self::assertFileExists(__DIR__.'/../../skeleton/static/localhost.dev/index.html');
    }

    public function getGeneratorBag(): GeneratorBag
    {
        self::bootKernel();
        $container = static::getContainer();

        return $container->get(GeneratorBag::class);
    }

    public function getParameterBag(): MockObject
    {
        $params = $this->createMock(ParameterBagInterface::class);

        $params->method('get')
             ->willReturnCallback(self::getParams(...));

        return $params;
    }

    public static function getParams(string $name): string
    {
        if ('kernel.project_dir' === $name) {
            return __DIR__.'/../../skeleton';
        }

        if ('pw.public_media_dir' === $name) {
            return 'media';
        }

        if ('pw.media_dir' === $name) {
            return realpath(__DIR__.'/../../skeleton/media');
        }

        if ('pw.public_dir' === $name) {
            return realpath(__DIR__.'/../../skeleton/public');
        }

        throw new Exception();
    }

    public function getPageRepo(): MockObject
    {
        $page = (new Page())
            ->setH1('Welcome to Pushword !')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new DateTime('2 days ago'))
            ->setMainContent('...');

        $pageRepo = $this->createMock(PageRepository::class);
        $pageRepo->method('getPublishedPages')
                  ->willReturn([
                      $page,
                  ]);

        return $pageRepo;
    }
}
