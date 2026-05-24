<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Workflow\Exception\InvalidArgumentException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Shows the current editorial state and renders the transitions the current
 * user is allowed to apply (guards are enforced by the workflow component).
 *
 * @extends AbstractField<Page>
 */
class PageWorkflowStateField extends AbstractField
{
    public function getEasyAdminField(): ?FieldInterface
    {
        $page = $this->admin->getSubject();

        try {
            $workflow = $this->formFieldManager->workflowRegistry->get($page, 'page_editorial');
        } catch (InvalidArgumentException) {
            return null; // editorial workflow not registered: hide the field
        }

        return $this->buildEasyAdminField('workflowState', null, [
            'disabled' => true,
            'label' => 'adminPageWorkflowStateLabel',
            'help' => $this->renderTransitions($page, $workflow),
            'help_html' => true,
        ]);
    }

    private function renderTransitions(object $page, WorkflowInterface $workflow): string
    {
        $transitions = [];
        foreach ($workflow->getEnabledTransitions($page) as $transition) {
            $transitions[] = $transition->getName();
        }

        return $this->formFieldManager->twig->render('@pwAdmin/page/page_workflow.html.twig', [
            'page' => $page,
            'transitions' => $transitions,
        ]);
    }
}
