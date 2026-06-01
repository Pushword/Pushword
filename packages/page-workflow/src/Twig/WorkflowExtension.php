<?php

namespace Pushword\PageWorkflow\Twig;

use Override;
use Pushword\Core\Entity\Page;
use Pushword\PageWorkflow\Entity\PageEditorialState;
use Pushword\PageWorkflow\Repository\PageEditorialStateRepository;
use Symfony\Component\Workflow\Registry;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes editorial state and available transitions to admin templates.
 */
final class WorkflowExtension extends AbstractExtension
{
    public function __construct(
        private readonly PageEditorialStateRepository $editorialStateRepo,
        private readonly Registry $workflowRegistry,
    ) {
    }

    /**
     * @return TwigFunction[]
     */
    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('pw_editorial_state', $this->getStateLabel(...)),
            new TwigFunction('pw_editorial_transitions', $this->getAvailableTransitions(...)),
        ];
    }

    public function getStateLabel(Page $page): string
    {
        return $this->editorialStateRepo->findFor($page)?->workflowState ?? 'draft';
    }

    /**
     * @return string[]
     */
    public function getAvailableTransitions(Page $page): array
    {
        if (null === $page->id) {
            return [];
        }

        // Use an ephemeral state when none exists yet — avoids DB writes on render.
        $state = $this->editorialStateRepo->findFor($page) ?? new PageEditorialState($page);

        if (! $this->workflowRegistry->has($state, 'page_editorial')) {
            return [];
        }

        $workflow = $this->workflowRegistry->get($state, 'page_editorial');

        $transitions = [];
        foreach ($workflow->getEnabledTransitions($state) as $transition) {
            $transitions[] = $transition->getName();
        }

        return $transitions;
    }

    public function getStateFor(Page $page): ?PageEditorialState
    {
        return $this->editorialStateRepo->findFor($page);
    }
}
