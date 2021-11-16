<?php

namespace Pushword\StaticGenerator;

use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepositoryInterface;
use Pushword\StaticGenerator\Generator\CNAMEGenerator;
use Pushword\StaticGenerator\Generator\CopierGenerator;
use Pushword\StaticGenerator\Generator\ErrorPageGenerator;
use Pushword\StaticGenerator\Generator\GeneratorInterface;
use Pushword\StaticGenerator\Generator\HtaccessGenerator;
use Pushword\StaticGenerator\Generator\MediaGenerator;
use Pushword\StaticGenerator\Generator\PagesGenerator;
use Pushword\StaticGenerator\Generator\RedirectionManager;
use Pushword\StaticGenerator\Generator\RobotsGenerator;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

class StaticGeneratorTest extends KernelTestCase
{
    private ?StaticAppGenerator $staticAppGenerator = null;

    public function testStaticCommand()
    {
        $kernel = static::createKernel();
        $application = new Application($kernel);

        $command = $application->find('pushword:static:generate');
        $commandTester = new CommandTester($command);

        $this->assertTrue(true);

        $commandTester->execute(['localhost.dev']);

        // the output of the command in the console
        $output = $commandTester->getDisplay();
        $this->assertTrue(false !== strpos($output, 'success'));

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/.htaccess'));
        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/index.html'));
        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/robots.txt'));

        $staticDir = __DIR__.'/../../skeleton/localhost.dev';
        $filesystem = new Filesystem();
        $filesystem->remove($staticDir);
    }

    private function getStaticAppGenerator()
    {
        if (null !== $this->staticAppGenerator) {
            return $this->staticAppGenerator;
        }
        //$staticAppGenerator = self::$kernel->getContainer()->get('pushword.static_app_generator');

        $generatorBag = $this->getGeneratorBag();

        return new StaticAppGenerator(
            self::$kernel->getContainer()->get('pushword.apps'),
            $generatorBag,
            $generatorBag->get(RedirectionManager::class)
        );
    }

    public function testIt()
    {
        self::bootKernel();

        $this->getStaticAppGenerator()->generate('localhost.dev');

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev'));

        $staticDir = __DIR__.'/../../skeleton/localhost.dev';
        $filesystem = new Filesystem();
        $filesystem->remove($staticDir);
        $filesystem->mkdir($staticDir);
    }

    private function getGenerator(string $name): GeneratorInterface
    {
        return $this->getGeneratorBag()->get($name)->setStaticAppGenerator($this->getStaticAppGenerator());
    }

    public function testGenerateHtaccess()
    {
        self::bootKernel();

        $generator = $this->getGenerator(HtaccessGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/.htaccess'));
    }

    public function testGenerateCNAME()
    {
        self::bootKernel();

        $generator = $this->getGenerator(CNAMEGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/CNAME'));
    }

    public function testCopier()
    {
        self::bootKernel();

        $generator = $this->getGenerator(CopierGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertTrue(file_exists(__DIR__.'/../../skeleton/localhost.dev/assets'));
    }

    public function testError()
    {
        self::bootKernel();

        $generator = $this->getGenerator(ErrorPageGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertFileExists(__DIR__.'/../../skeleton/localhost.dev/404.html');
    }

    public function testDownload()
    {
        self::bootKernel();

        $generator = $this->getGenerator(MediaGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertFileExists(__DIR__.'/../../skeleton/localhost.dev/media');
    }

    public function testPages()
    {
        self::bootKernel();

        $generator = $this->getGenerator(PagesGenerator::class);

        $generator->generate('localhost.dev');

        $this->assertFileExists(__DIR__.'/../../skeleton/localhost.dev/index.html');
    }

    public function getGeneratorBag(): GeneratorBag
    {
        $generatorBag = new GeneratorBag();
        $generators = [
            'redirectionManager' => RedirectionManager::class,
            'cNAMEGenerator' => CNAMEGenerator::class,
            'copierGenerator' => CopierGenerator::class,
            'errorPageGenerator' => ErrorPageGenerator::class,
            'htaccessGenerator' => HtaccessGenerator::class,
            'mediaGenerator' => MediaGenerator::class,
            'pagesGenerator' => PagesGenerator::class,
            'robotsGenerator' => RobotsGenerator::class,
        ];

        foreach ($generators as $name => $class) {
            $generator = new $class(
                $this->getPageRepo(),
                static::getContainer()->get('twig'),
                $this->getParameterBag(),
                new RequestStack(),
                self::$kernel->getContainer()->get('translator'),
                self::$kernel->getContainer()->get('pushword.router'),
                self::$kernel,//->getContainer()->get('kernel'),
                self::$kernel->getContainer()->get('pushword.apps')
            );

            if (property_exists($generator, 'redirectionManager')) {
                $generator->redirectionManager = $generatorBag->get('redirectionManager');
            }

            $generatorBag->set($generator);
        }

        return $generatorBag;
    }

    public function getParameterBag()
    {
        $params = $this->createMock(ParameterBagInterface::class);

        $params->method('get')
             ->willReturnCallback([$this, 'getParams']);

        return $params;
    }

    public static function getParams($name)
    {
        if ('pw.entity_page' == $name) {
            return \App\Entity\Page::class;
        }

        if ('kernel.project_dir' == $name) {
            return __DIR__.'/../../skeleton';
        }

        if ('pw.public_media_dir' == $name) {
            return 'media';
        }

        if ('pw.media_dir' == $name) {
            return realpath(__DIR__.'/../../skeleton/media');
        }

        if ('pw.public_dir' == $name) {
            return realpath(__DIR__.'/../../skeleton/public');
        }
    }

    public function getPageRepo()
    {
        $page = (new Page())
            ->setH1('Welcome : this is your first page')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new \DateTime('2 days ago'))
            ->setMainContent('...');

        $pageRepo = $this->createMock(PageRepositoryInterface::class);
        $pageRepo->method('getPublishedPages')
                  ->willReturn([
                      $page,
                  ]);

        return $pageRepo;
    }
}
