<?php

namespace Pushword\PageScanner;

use App\Entity\Page;
use Pushword\Core\Entity\PageInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment as Twig;
use Twig\Loader\ArrayLoader as TwigLoader;

class PageScannerTest extends KernelTestCase
{
    public function testIt()
    {
        self::bootKernel();

        $scanner = new PageScannerService(//self::$kernel->getContainer()->get('twig')
            new Twig(new TwigLoader()),
            self::$kernel->getContainer()->get('doctrine.orm.default_entity_manager'),
            __DIR__.'/../../skeleton/public',
            self::$kernel->getContainer()->get('pushword.apps'),
            self::$kernel->getContainer()->get('pushword.router'),
            self::$kernel
        );
        $errors = $scanner->scan($this->getPage());

        // bad design test because skeleton is well installed on local and not fully
        // on github action
        $this->assertTrue(
            (\is_array($errors) && false !== strpos($errors[0]['message'], 'introuvable'))
                || true === $errors
        );
    }

    public function getPage(): PageInterface
    {
        $page = (new Page())
            ->setH1('Welcome : this is your first page')
            ->setSlug('homepage')
            ->setLocale('en')
            ->setCreatedAt(new \DateTime('2 days ago'))
            ->setMainContent('...');

        return $page;
    }
}
