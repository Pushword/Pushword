<?php

namespace Pushword\PageWorkflow\DependencyInjection;

use Override;
use Pushword\Core\DependencyInjection\ExtensionTrait;
use Pushword\PageWorkflow\Entity\PageEditorialState;
use Pushword\PageWorkflow\Pending\PendingModification;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordPageWorkflowExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    protected string $configFolder = __DIR__.'/../config';

    /**
     * @param array<mixed> $mergedConfig
     */
    #[Override]
    protected function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $container->setParameter('pushword_page_workflow.editorial_workflow', $mergedConfig['editorial_workflow']);
        $container->setParameter('pushword_page_workflow.require_approval_before_publish', $mergedConfig['require_approval_before_publish']);

        $this->loadService($container);
    }

    #[Override]
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependPackagesConfig($container);
        $this->registerDefaultWorkflows($container);
    }

    /**
     * Ship the editorial and pending-modification workflows. Skipped per-workflow
     * when the application already defines one, so end users can fully override
     * places/transitions/guards from their own framework config.
     */
    private function registerDefaultWorkflows(ContainerBuilder $container): void
    {
        $config = new Processor()->processConfiguration(new Configuration(), $container->getExtensionConfig('pushword_page_workflow'));
        if (false === $config['editorial_workflow']) {
            return;
        }

        $existing = $this->collectExistingWorkflows($container);
        $workflows = [];

        if (! isset($existing['page_editorial'])) {
            $workflows['page_editorial'] = [
                'type' => 'state_machine',
                'marking_store' => ['type' => 'method', 'property' => 'workflowState'],
                'supports' => [PageEditorialState::class],
                'initial_marking' => 'draft',
                'places' => ['draft', 'in_review', 'approved'],
                'transitions' => [
                    'submit' => ['from' => 'draft', 'to' => 'in_review'],
                    'approve' => ['from' => 'in_review', 'to' => 'approved', 'guard' => "is_granted('ROLE_EDITOR')"],
                    'request_changes' => ['from' => ['in_review', 'approved'], 'to' => 'draft'],
                ],
            ];
        }

        if (! isset($existing['page_pending_modification'])) {
            $workflows['page_pending_modification'] = [
                'type' => 'state_machine',
                'marking_store' => ['type' => 'method', 'property' => 'workflowState'],
                'supports' => [PendingModification::class],
                'initial_marking' => 'draft',
                'places' => ['draft', 'in_review', 'approved'],
                'transitions' => [
                    'submit' => ['from' => 'draft', 'to' => 'in_review'],
                    'approve' => ['from' => 'in_review', 'to' => 'approved', 'guard' => "is_granted('ROLE_EDITOR')"],
                    'request_changes' => ['from' => ['in_review', 'approved'], 'to' => 'draft'],
                ],
            ];
        }

        if ([] === $workflows) {
            return;
        }

        $container->prependExtensionConfig('framework', [
            'workflows' => $workflows,
        ]);
    }

    /**
     * @return array<string, true>
     */
    private function collectExistingWorkflows(ContainerBuilder $container): array
    {
        $existing = [];
        foreach ($container->getExtensionConfig('framework') as $frameworkConfig) {
            if (! isset($frameworkConfig['workflows'])) {
                continue;
            }

            if (! \is_array($frameworkConfig['workflows'])) {
                continue;
            }

            foreach (array_keys($frameworkConfig['workflows']) as $name) {
                if (\is_string($name)) {
                    $existing[$name] = true;
                }
            }
        }

        return $existing;
    }

    #[Override]
    public function getAlias(): string
    {
        return 'pushword_page_workflow';
    }
}
