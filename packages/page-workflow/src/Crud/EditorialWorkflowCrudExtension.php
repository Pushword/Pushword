<?php

namespace Pushword\PageWorkflow\Crud;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Pushword\Admin\Crud\PageCrudExtensionInterface;
use Pushword\Core\Entity\Page;
use Pushword\PageWorkflow\Controller\Admin\WorkflowTransitionController;
use Pushword\PageWorkflow\Filter\PageWorkflowStateFilter;
use Pushword\PageWorkflow\Twig\WorkflowExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Plugs the editorial workflow UI into PageCrudController via the admin
 * extension hook. Adds:
 *  - one POST/CSRF-protected EasyAdmin action per workflow transition,
 *    shown only when enabled for the current entity's state;
 *  - an index column rendering the current editorial state;
 *  - a workflow-state filter (joins PageEditorialState via OneToOne).
 */
final readonly class EditorialWorkflowCrudExtension implements PageCrudExtensionInterface
{
    /** @var list<string> */
    private const array TRANSITIONS = ['submit', 'approve', 'request_changes'];

    public function __construct(
        private WorkflowExtension $workflowExtension,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function configureActions(Actions $actions): void
    {
        foreach (self::TRANSITIONS as $transition) {
            $action = Action::new('workflow_'.$transition, 'adminPageWorkflowTransition.'.$transition)
                ->linkToUrl(fn (Page $page): string => $this->urlGenerator->generate(
                    'pushword_page_workflow',
                    ['id' => $page->id, 'transition' => $transition],
                ))
                ->displayIf(fn (Page $page): bool => in_array(
                    $transition,
                    $this->workflowExtension->getAvailableTransitions($page),
                    true,
                ))
                ->renderAsForm()
                ->setTemplatePath('@PushwordPageWorkflow/admin/_transition_action.html.twig')
                ->setHtmlAttributes([
                    'data-pw-csrf-token-id' => WorkflowTransitionController::CSRF_TOKEN_ID.':'.$transition,
                ]);

            $actions->add(Crud::PAGE_EDIT, $action);
        }
    }

    public function configureFilters(Filters $filters): void
    {
        $filters->add(PageWorkflowStateFilter::new('editorialState.workflowState', 'adminPageWorkflowStateLabel'));
    }

    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX !== $pageName) {
            return;
        }

        yield TextField::new('slug', 'adminPageWorkflowStateLabel')
            ->setSortable(false)
            ->setTemplatePath('@PushwordPageWorkflow/admin/index_state_field.html.twig')
            ->formatValue(static fn (): string => '');
    }
}
