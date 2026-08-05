<?php

namespace Pushword\LinkImprover\DependencyInjection;

use InvalidArgumentException;
use Pushword\LinkImprover\LinkImprover;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Puts the LinkImprover filter into every app's main_content chain, right
 * after Markdown. Splicing here instead of documenting a full `filters:`
 * override keeps sites on core's default chain; the filter stays inert until
 * the app sets `link_improver: true`.
 */
final class AddLinkImproverToFilterChainPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $apps = $this->apps($container);

        foreach ($apps as $host => $app) {
            if (! \is_array($app)) {
                continue;
            }

            $appFilters = $app['filters'] ?? null;
            if (! \is_array($appFilters)) {
                continue;
            }

            $filters = $appFilters['main_content'] ?? null;
            if (\is_string($filters)) {
                $filters = explode(',', $filters);
            }

            if (! \is_array($filters)) {
                continue;
            }

            $filterList = $this->filterNames($filters);
            if (\in_array(LinkImprover::class, $filterList, true)) {
                continue;
            }

            if (\in_array('linkImprover', $filterList, true)) {
                continue;
            }

            array_splice($filterList, $this->afterMarkdown($filterList), 0, [LinkImprover::class]);
            $appFilters['main_content'] = $filterList;
            $app['filters'] = $appFilters;
            $apps[$host] = $app;
        }

        $container->setParameter('pw.apps', $apps);
    }

    /**
     * The generic shape, deliberately: what the dev-app container resolves to
     * at analysis time is one concrete config, not the contract every site's
     * apps follow.
     *
     * @return array<string, mixed>
     */
    private function apps(ContainerBuilder $container): array
    {
        return $container->getParameter('pw.apps');
    }

    /**
     * @param array<mixed> $filters
     *
     * @return list<string>
     */
    private function filterNames(array $filters): array
    {
        $names = [];
        foreach ($filters as $filter) {
            if (! \is_string($filter)) {
                throw new InvalidArgumentException(\sprintf('A main_content filter must be a name or a class, %s given.', get_debug_type($filter)));
            }

            $names[] = $filter;
        }

        return $names;
    }

    /**
     * @param list<string> $filters
     */
    private function afterMarkdown(array $filters): int
    {
        foreach ($filters as $index => $filter) {
            $backslash = strrpos($filter, '\\');
            $shortName = false === $backslash ? $filter : substr($filter, $backslash + 1);
            if ('markdown' === strtolower(trim($shortName))) {
                return $index + 1;
            }
        }

        return \count($filters);
    }
}
