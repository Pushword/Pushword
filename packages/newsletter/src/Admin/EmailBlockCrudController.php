<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\AdminBlockEditor\Form\EditorjsType;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Entity\EmailBlock;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** @extends AbstractCrudController<EmailBlock> */
#[IsGranted('ROLE_EDITOR')]
#[AdminRoute(path: '/newsletter/block', name: 'newsletter_block')]
class EmailBlockCrudController extends AbstractCrudController
{
    public function __construct(private readonly SiteRegistry $sites)
    {
    }

    public static function getEntityFqcn(): string
    {
        return EmailBlock::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        $crud->overrideTemplate('layout', '@PushwordNewsletter/admin/layout.html.twig')
            ->overrideTemplate('crud/index', '@PushwordNewsletter/admin/index.html.twig')
            ->setEntityLabelInSingular('newsletter.block.singular')->setEntityLabelInPlural('newsletter.block.plural')
            ->setEntityPermission('ROLE_EDITOR')->setDefaultSort(['name' => 'ASC'])->setSearchFields(['name']);
        if (class_exists(EditorjsType::class)) {
            $crud->addFormTheme('@PushwordAdminBlockEditor/editorjs_widget.html.twig');
        }

        return $crud;
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', 'newsletter.block.name');
        $body = TextareaField::new('bodyMarkdown', 'newsletter.block.content')->hideOnIndex()->setHelp('newsletter.block.help');
        if (class_exists(EditorjsType::class)) {
            $body->setFormType(EditorjsType::class)->setFormTypeOption('attr', ['page_host' => $this->sites->getDefault()->getMainHost(), 'page_id' => 0]);
        }

        yield $body;
    }
}
