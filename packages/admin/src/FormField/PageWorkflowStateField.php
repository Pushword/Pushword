<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use Pushword\Core\Entity\Page;
use Symfony\Component\Workflow\Exception\InvalidArgumentException;

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
            $this->formFieldManager->workflowRegistry->get($page, 'page_editorial');
        } catch (InvalidArgumentException) {
            return null; // editorial workflow disabled or not registered: hide the field
        }

        return $this->buildEasyAdminField('workflowState', null, [
            'disabled' => true,
            'label' => 'adminPageWorkflowStateLabel',
            'help' => $this->renderTransitions(),
            'help_html' => true,
        ]);
    }

    private function renderTransitions(): string
    {
        $page = $this->admin->getSubject();
        $workflow = $this->formFieldManager->workflowRegistry->get($page, 'page_editorial');

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
