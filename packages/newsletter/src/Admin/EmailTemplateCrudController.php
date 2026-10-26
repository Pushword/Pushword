<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Newsletter\Entity\EmailTemplate;
use Pushword\Newsletter\Repository\EmailTemplateRepository;
use Pushword\Newsletter\Service\EmailTemplateRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/** @extends AbstractCrudController<EmailTemplate> */
#[AdminRoute(path: '/newsletter/template', name: 'newsletter_template')]
class EmailTemplateCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly EmailTemplateRepository $templates,
        private readonly RequestStack $requests,
        private readonly UrlGeneratorInterface $urls,
        private readonly TranslatorInterface $translator,
        private readonly EmailTemplateRegistry $registry,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return EmailTemplate::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('layout', '@PushwordNewsletter/admin/layout.html.twig')
            ->overrideTemplate('crud/index', '@PushwordNewsletter/admin/index.html.twig')
            ->overrideTemplate('crud/new', '@PushwordNewsletter/admin/compose_new.html.twig')
            ->overrideTemplate('crud/edit', '@PushwordNewsletter/admin/compose_edit.html.twig')
            ->setEntityLabelInSingular('newsletter.template.label.singular')
            ->setEntityLabelInPlural('newsletter.template.label.plural')
            ->setEntityPermission('ROLE_EDITOR')
            ->setDefaultSort(['name' => 'ASC'])
            ->setSearchFields(['name'])
            ->showEntityActionsInlined();
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $duplicate = Action::new('duplicate', 'newsletter.template.duplicate', 'fa fa-copy')
            ->linkToUrl(fn (EmailTemplate $template): string => $this->urls->generate('admin_newsletter_template_new', ['duplicate' => $template->id]));

        return $actions
            ->disable(Action::DELETE, Action::BATCH_DELETE, Action::SAVE_AND_ADD_ANOTHER)
            ->add(Crud::PAGE_INDEX, $duplicate)
            ->add(Crud::PAGE_EDIT, $duplicate)
            ->update(Crud::PAGE_INDEX, Action::NEW, static fn (Action $action): Action => $action->setLabel('newsletter.template.create'))
            ->update(Crud::PAGE_NEW, Action::SAVE_AND_RETURN, static fn (Action $action): Action => $action->setLabel('newsletter.template.save'));
    }

    #[Override]
    public function createEntity(string $entityFqcn): EmailTemplate
    {
        $id = $this->requests->getCurrentRequest()?->query->getInt('duplicate');
        if (null === $id || 0 === $id) {
            return new EmailTemplate();
        }

        $original = $this->templates->find($id) ?? throw $this->createNotFoundException();
        $copy = clone $original;
        $copySuffix = $this->translator->trans('newsletter.template.copyName', ['%name%' => '']);
        $number = 1;
        do {
            $suffix = $copySuffix.($number > 1 ? ' '.$number : '');
            $copy->name = mb_substr($original->name, 0, 255 - mb_strlen($suffix)).$suffix;
            ++$number;
        } while (null !== $this->templates->findOneBy(['name' => $copy->name]));

        return $copy;
    }

    #[Override]
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $responseParameters->set('site_templates', $this->registry->siteChoices());
        $template = $this->getContext()?->getEntity()->getInstance();
        if ($template instanceof EmailTemplate && null !== $template->id) {
            $saved = $this->entityManager->getUnitOfWork()->getOriginalEntityData($template);
            $responseParameters->set('template_saved_values', [
                $saved['name'],
                true === $saved['wrapped'] ? '1' : null,
                $saved['headerMarkdown'],
                $saved['footerMarkdown'],
            ]);
        }

        return $responseParameters;
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield FormField::addFieldset('newsletter.template.identity')->setIcon('fa fa-palette')
            ->setHelp('newsletter.template.help')
            ->setFormTypeOption('context_help', 'newsletterHelpSharedTemplate');
        yield TextField::new('name', 'newsletter.template.name')->setColumns('col-12');
        yield BooleanField::new('wrapped', 'newsletter.template.wrapped')
            ->renderAsSwitch(false)
            ->hideOnIndex();
        yield ChoiceField::new('wrapped', 'newsletter.template.layout')->onlyOnIndex()
            ->setChoices(['newsletter.design.wrapped' => 1, 'newsletter.design.simple' => 0]);
        yield TextareaField::new('headerMarkdown', 'newsletter.design.header')->hideOnIndex()
            ->setNumOfRows(5)->setColumns('col-12')->setRequired(false)
            ->setHelp('newsletter.template.headerHelp')
            ->setFormTypeOption('context_help', true);
        yield TextareaField::new('footerMarkdown', 'newsletter.design.footer')->hideOnIndex()
            ->setNumOfRows(5)->setColumns('col-12')->setRequired(false)
            ->setHelp('newsletter.template.footerHelp')
            ->setFormTypeOption('context_help', true);
    }
}
