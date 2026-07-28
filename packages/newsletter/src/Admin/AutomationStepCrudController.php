<?php

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Newsletter\Entity\AutomationStep;

/**
 * Not reachable on its own: it exists so the automation form can embed steps
 * inline as a collection.
 *
 * @extends AbstractCrudController<AutomationStep>
 */
#[AdminRoute(path: '/newsletter/automation-step', name: 'newsletter_automation_step')]
class AutomationStepCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AutomationStep::class;
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('position', 'newsletter.step.field.position');
        yield IntegerField::new('delayMinutes', 'newsletter.step.field.delay')
            ->setHelp('newsletter.step.field.delay.help');
        yield TextField::new('subject', 'newsletter.step.field.subject');
        yield TextareaField::new('bodyMarkdown', 'newsletter.step.field.body')->setNumOfRows(10);
    }
}
