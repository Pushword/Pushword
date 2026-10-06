<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\Site;

use PHPUnit\Framework\TestCase;
use Pushword\Core\Site\RequestContext;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Twig\Environment as Twig;
use Twig\Loader\ArrayLoader;

final class SiteRegistryTest extends TestCase
{
    private SiteRegistry $registry;

    private Twig $twig;

    protected function setUp(): void
    {
        $this->twig = new Twig(new ArrayLoader(), ['strict_variables' => true]);
        $this->registry = new SiteRegistry([
            'example.com' => [
                'hosts' => ['example.com'],
                'locale' => 'en',
                'matomo_site_id' => 1,
                'paymentProvider' => 'soge',
                'checkoutEnabled' => true,
            ],
            'fr.example.com' => [
                'hosts' => ['fr.example.com', 'alias.example.com'],
                'locale' => 'fr',
                'matomo_site_id' => 2,
                'paymentProvider' => 'revolut',
                'checkoutEnabled' => false,
            ],
        ], new TemplateResolver($this->twig, new ArrayAdapter()), new ParameterBag());
    }

    public function testGetAppValuePreservesHostSelectionForPhpServices(): void
    {
        self::assertSame('soge', $this->registry->getAppValue('paymentProvider'));
        self::assertSame('revolut', $this->registry->getAppValue('paymentProvider', 'fr.example.com'));
        self::assertSame('revolut', $this->registry->getAppValue('paymentProvider', 'alias.example.com'));
        self::assertSame('soge', $this->registry->getAppValue('paymentProvider', 'unknown.example.com'));
        self::assertFalse($this->registry->getAppValue('checkoutEnabled', 'fr.example.com'));
        self::assertNull($this->registry->getAppValue('missing'));
    }

    public function testGetAppValueFollowsAndResetsTheCurrentRequestSite(): void
    {
        $context = new RequestContext($this->registry);
        $this->registry->setRequestContext($context);
        $context->switchSite('fr.example.com');

        self::assertSame(2, $this->registry->getAppValue('matomo_site_id'));
        self::assertSame(2, $this->registry->getAppValue('matomo_site_id', ''));
        self::assertSame(1, $this->registry->getAppValue('matomo_site_id', 'example.com'));

        $context->reset();

        self::assertSame(1, $this->registry->getAppValue('matomo_site_id'));
    }

    public function testTwigCanReadTheCurrentSitePropertyThroughTheAppsGlobal(): void
    {
        $context = new RequestContext($this->registry);
        $this->registry->setRequestContext($context);
        $context->switchSite('fr.example.com');
        $this->twig->addGlobal('apps', $this->registry);
        $template = $this->twig->createTemplate("{% set matomo_site_id = apps.getAppValue('matomo_site_id') %}{{ matomo_site_id }}");

        self::assertSame('2', $template->render());

        $context->reset();

        self::assertSame('1', $template->render());
    }
}
