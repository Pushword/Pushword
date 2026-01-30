<?php

namespace Pushword\Core\Tests\Component;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Template\TemplateResolver;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

class SiteConfigTest extends TestCase
{
    private string $tempDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tempDir = sys_get_temp_dir().'/pushword_test_'.uniqid();
        $this->filesystem->mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if ($this->filesystem->exists($this->tempDir)) {
            $this->filesystem->remove($this->tempDir);
        }
    }

    public function testGetViewWithOverridedViewForHost(): void
    {
        $host = 'localhost.dev';
        $templateDir = $this->tempDir.'/templates';
        $hostTemplatePath = $templateDir.'/'.$host.'/conversation/review.html.twig';

        $this->filesystem->mkdir(\dirname($hostTemplatePath));
        $this->filesystem->dumpFile($hostTemplatePath, 'Host template content');

        $site = $this->createSiteConfig($host, $templateDir);
        $resolver = $this->createTemplateResolver();

        $result = $resolver->resolve($site, '@PushwordConversation/conversation/review.html.twig');

        // Full path is returned as-is (isFullPath: starts with @ and contains /)
        self::assertSame('@PushwordConversation/conversation/review.html.twig', $result);
    }

    public function testGetViewWithOverridedViewForTemplate(): void
    {
        $host = 'localhost.dev';
        $template = '@Pushword';
        $templateDir = $this->tempDir.'/templates';
        $templatePath = $templateDir.'/Pushword/conversation/review.html.twig';

        $this->filesystem->mkdir(\dirname($templatePath));
        $this->filesystem->dumpFile($templatePath, 'Template content');

        $site = $this->createSiteConfig($host, $templateDir, $template);
        $resolver = $this->createTemplateResolver();

        // Use a non-full-path to trigger the override resolution
        $result = $resolver->resolve($site, '/conversation/review.html.twig');

        // Template-specific override
        self::assertSame('/Pushword/conversation/review.html.twig', $result);
    }

    public function testGetViewWithGlobalOverride(): void
    {
        $host = 'localhost.dev';
        $templateDir = $this->tempDir.'/templates';
        $globalTemplatePath = $templateDir.'/conversation/review.html.twig';

        $this->filesystem->mkdir(\dirname($globalTemplatePath));
        $this->filesystem->dumpFile($globalTemplatePath, 'Global template content');

        $site = $this->createSiteConfig($host, $templateDir);
        $resolver = $this->createTemplateResolver();

        $result = $resolver->resolve($site, '/conversation/review.html.twig');

        self::assertSame('/conversation/review.html.twig', $result);
    }

    public function testGetViewWithFullPathReturnsAsIs(): void
    {
        $host = 'localhost.dev';
        $templateDir = $this->tempDir.'/templates';

        $site = $this->createSiteConfig($host, $templateDir);

        $result = $site->getView('@PushwordConversation/conversation/review.html.twig');

        self::assertSame('@PushwordConversation/conversation/review.html.twig', $result);
    }

    public function testGetViewWithHostOverride(): void
    {
        $host = 'localhost.dev';
        $templateDir = $this->tempDir.'/templates';
        $hostTemplatePath = $templateDir.'/'.$host.'/conversation/review.html.twig';

        $this->filesystem->mkdir(\dirname($hostTemplatePath));
        $this->filesystem->dumpFile($hostTemplatePath, 'Host template content');

        $site = $this->createSiteConfig($host, $templateDir);
        $resolver = $this->createTemplateResolver();

        $result = $resolver->resolve($site, '/conversation/review.html.twig');

        self::assertSame('/'.$host.'/conversation/review.html.twig', $result);
    }

    public function testResolveWithInvalidOverrideNameThrows(): void
    {
        $host = 'localhost.dev';
        $templateDir = $this->tempDir.'/templates';

        $site = $this->createSiteConfig($host, $templateDir);
        $resolver = $this->createTemplateResolver();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid view name: @Invalid');

        // Use non-full path starting with @ but no /
        $resolver->resolve($site, '@Invalid', '@Fallback');
    }

    private function createSiteConfig(
        string $host,
        string $templateDir,
        string $template = '@Pushword',
    ): SiteConfig {
        $params = new ParameterBag([
            'kernel.project_dir' => $this->tempDir,
        ]);

        $properties = [
            'hosts' => [$host],
            'host' => $host,
            'base_url' => 'https://'.$host,
            'name' => 'Test App',
            'locale' => 'fr',
            'locales' => ['fr'],
            'template' => $template,
            'template_dir' => $templateDir,
        ];

        $site = new SiteConfig($params, $properties, true);
        $site->setTemplateResolver($this->createTemplateResolver());

        return $site;
    }

    public function testGetResolvesViaGetterMethod(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');

        self::assertSame('Test App', $site->get('name'));
    }

    public function testGetResolvesSnakeCaseKey(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');

        self::assertSame('https://localhost.dev', $site->get('base_url'));
    }

    public function testGetResolvesCustomProperty(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');
        $site->setCustomProperty('my_custom_key', 'custom_value');

        self::assertSame('custom_value', $site->get('my_custom_key'));
    }

    public function testGetReturnsNullForUnknownKey(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');

        self::assertNull($site->get('nonexistent_key'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');

        self::assertTrue($site->has('name'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');

        self::assertFalse($site->has('nonexistent'));
    }

    public function testGetStrReturnsString(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');

        self::assertSame('Test App', $site->getStr('name'));
    }

    public function testGetStrReturnsDefaultWhenMissing(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');

        self::assertSame('fallback', $site->getStr('missing_key', 'fallback'));
    }

    public function testGetStrThrowsOnNonScalar(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');
        $site->setCustomProperty('arr', ['not', 'scalar']);

        $this->expectException(LogicException::class);
        $site->getStr('arr');
    }

    public function testGetArrayReturnsArray(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');
        $site->setCustomProperty('list', ['a', 'b']);

        self::assertSame(['a', 'b'], $site->getArray('list'));
    }

    public function testGetArrayReturnsDefaultWhenMissing(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');

        self::assertSame(['default'], $site->getArray('missing', ['default']));
    }

    public function testGetArrayThrowsOnNonArray(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');
        $site->setCustomProperty('scalar', 'not_array');

        $this->expectException(LogicException::class);
        $site->getArray('scalar');
    }

    public function testGetBooleanReturnsBool(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');
        $site->setCustomProperty('flag', false);

        self::assertFalse($site->getBoolean('flag'));
    }

    public function testGetBooleanReturnsDefaultWhenMissing(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');

        self::assertTrue($site->getBoolean('missing', true));
    }

    public function testGetBooleanThrowsOnNonBool(): void
    {
        $site = $this->createSiteConfig('localhost.dev', $this->tempDir.'/templates');
        $site->setCustomProperty('my_flag', 'not_bool');

        $this->expectException(LogicException::class);
        $site->getBoolean('my_flag');
    }

    public function testConstructorProcessesCustomPropertiesKey(): void
    {
        $params = new ParameterBag(['kernel.project_dir' => $this->tempDir]);

        $site = new SiteConfig($params, [
            'hosts' => ['localhost'],
            'base_url' => 'https://localhost',
            'name' => 'Site',
            'locale' => 'en',
            'locales' => ['en'],
            'template' => '@Pushword',
            'entity_can_override_filters' => false,
            'custom_properties' => ['color' => 'blue', 'extra' => 42],
        ], false);

        self::assertSame('blue', $site->getCustomProperty('color'));
        self::assertSame(42, $site->getCustomProperty('extra'));
    }

    private function createTemplateResolver(): TemplateResolver
    {
        $loader = new FilesystemLoader();
        $twig = new Twig($loader);

        return new TemplateResolver($twig, new ArrayAdapter());
    }
}
