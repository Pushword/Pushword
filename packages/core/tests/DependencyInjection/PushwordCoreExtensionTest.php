<?php

namespace Pushword\Core\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Pushword\Core\DependencyInjection\PushwordCoreExtension;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PushwordCoreExtensionTest extends TestCase
{
    public function testDefaultWorkflowIsRegisteredByDefault(): void
    {
        $container = $this->registerWorkflow([]);

        self::assertTrue($this->hasPageEditorialWorkflow($container));
    }

    public function testDefaultWorkflowIsSkippedWhenDisabled(): void
    {
        $container = $this->registerWorkflow([['editorial_workflow' => false]]);

        self::assertFalse($this->hasPageEditorialWorkflow($container));
    }

    public function testDefaultWorkflowIsSkippedWhenAppAlreadyDefinesIt(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('framework', ['workflows' => ['page_editorial' => ['type' => 'state_machine']]]);

        $this->callRegisterDefaultWorkflow($container);

        // The default is not prepended: only the app-provided framework config remains.
        self::assertCount(1, $container->getExtensionConfig('framework'));
    }

    /**
     * @param array<int, array<string, mixed>> $pushwordConfig
     */
    private function registerWorkflow(array $pushwordConfig): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach ($pushwordConfig as $config) {
            $container->prependExtensionConfig('pushword', $config);
        }

        $this->callRegisterDefaultWorkflow($container);

        return $container;
    }

    private function callRegisterDefaultWorkflow(ContainerBuilder $container): void
    {
        $method = new ReflectionMethod(PushwordCoreExtension::class, 'registerDefaultWorkflow');
        $method->invoke(new PushwordCoreExtension(), $container);
    }

    private function hasPageEditorialWorkflow(ContainerBuilder $container): bool
    {
        return array_any(
            $container->getExtensionConfig('framework'),
            static fn (array $config): bool => \is_array($config['workflows'] ?? null) && isset($config['workflows']['page_editorial']),
        );
    }
}
