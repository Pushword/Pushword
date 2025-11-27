<?php

namespace Pushword\Core\Tests\Component;

use Exception;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Component\App\AppConfig;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment as Twig;
use Twig\Loader\FilesystemLoader;

class AppConfigTest extends TestCase
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

        // Create the template file for the host
        $this->filesystem->mkdir(\dirname($hostTemplatePath));
        $this->filesystem->dumpFile($hostTemplatePath, 'Host template content');

        $appConfig = $this->createAppConfig($host, $templateDir);

        // Test getOverridedView directly via reflection to test the new code
        $reflection = new ReflectionMethod($appConfig, 'getOverridedView');

        $result = $reflection->invoke($appConfig, '@PushwordConversation/conversation/review.html.twig');

        // Should return the host-specific override
        self::assertSame('/'.$host.'/conversation/review.html.twig', $result);
    }

    public function testGetViewWithOverridedViewForTemplate(): void
    {
        $host = 'localhost.dev';
        $template = '@Pushword';
        $templateDir = $this->tempDir.'/templates';
        $templatePath = $templateDir.'/Pushword/conversation/review.html.twig';

        // Create the template file for the template (not host)
        $this->filesystem->mkdir(\dirname($templatePath));
        $this->filesystem->dumpFile($templatePath, 'Template content');

        $appConfig = $this->createAppConfig($host, $templateDir, $template);

        // Test getOverridedView directly via reflection
        $reflection = new ReflectionMethod($appConfig, 'getOverridedView');

        $result = $reflection->invoke($appConfig, '@PushwordConversation/conversation/review.html.twig');

        // Should return the template-specific override
        self::assertSame('/Pushword/conversation/review.html.twig', $result);
    }

    public function testGetViewWithGlobalOverride(): void
    {
        $host = 'localhost.dev';
        $templateDir = $this->tempDir.'/templates';
        $globalTemplatePath = $templateDir.'/conversation/review.html.twig';

        // Create the global template file
        $this->filesystem->mkdir(\dirname($globalTemplatePath));
        $this->filesystem->dumpFile($globalTemplatePath, 'Global template content');

        $appConfig = $this->createAppConfig($host, $templateDir);

        // Test getOverridedView directly via reflection
        $reflection = new ReflectionMethod($appConfig, 'getOverridedView');

        $result = $reflection->invoke($appConfig, '@PushwordConversation/conversation/review.html.twig');

        // Should return the global override
        self::assertSame('/conversation/review.html.twig', $result);
    }

    public function testGetOverridedViewWithInvalidViewName(): void
    {
        $host = 'localhost.dev';
        $templateDir = $this->tempDir.'/templates';

        $appConfig = $this->createAppConfig($host, $templateDir);

        // Test that getOverridedView throws exception when name starts with @ but has no /
        $reflection = new ReflectionMethod($appConfig, 'getOverridedView');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid view name: @Invalid');

        $reflection->invoke($appConfig, '@Invalid');
    }

    public function testGetViewWithFullPathReturnsAsIs(): void
    {
        $host = 'localhost.dev';
        $templateDir = $this->tempDir.'/templates';

        $appConfig = $this->createAppConfig($host, $templateDir);
        $result = $appConfig->getView('@PushwordConversation/conversation/review.html.twig');

        // When isFullPath returns true, it should return the path as-is
        // This is the normal behavior for full paths (paths starting with @ and containing /)
        self::assertSame('@PushwordConversation/conversation/review.html.twig', $result);
    }

    public function testGetOverridedViewWithPathStartingWithAt(): void
    {
        $host = 'localhost.dev';
        $templateDir = $this->tempDir.'/templates';
        $hostTemplatePath = $templateDir.'/'.$host.'/conversation/review.html.twig';

        // Create the template file for the host
        $this->filesystem->mkdir(\dirname($hostTemplatePath));
        $this->filesystem->dumpFile($hostTemplatePath, 'Host template content');

        $appConfig = $this->createAppConfig($host, $templateDir);

        // Test getOverridedView with path starting with @
        // This tests the code added in lines 284-290
        $reflection = new ReflectionMethod($appConfig, 'getOverridedView');

        $result = $reflection->invoke($appConfig, '@PushwordConversation/conversation/review.html.twig');

        // Should extract the part after / and find the host override
        self::assertSame('/'.$host.'/conversation/review.html.twig', $result);
    }

    private function createAppConfig(
        string $host,
        string $templateDir,
        string $template = '@Pushword'
    ): AppConfig {
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

        $appConfig = new AppConfig($params, $properties, true);

        // Create a Twig environment with a filesystem loader
        $loader = new FilesystemLoader();
        $twig = new Twig($loader);
        $appConfig->setTwig($twig);

        return $appConfig;
    }
}
