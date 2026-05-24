<?php

namespace Pushword\Snippet\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Snippet\Entity\Snippet;

/**
 * @extends AbstractCrudController<Snippet>
 */
class SnippetCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Snippet::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('snippet.label.singular')
            ->setEntityLabelInPlural('snippet.label.plural')
            ->setDefaultSort(['slug' => 'ASC']);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('name', 'snippet.field.name');
        yield TextField::new('slug', 'snippet.field.slug')
            ->setHelp('snippet.field.slug.help');
        yield TextField::new('host', 'snippet.field.host')->hideOnIndex();
        yield TextField::new('tags', 'snippet.field.tags')->setRequired(false)->hideOnIndex();
        yield TextareaField::new('content', 'snippet.field.content')
            ->setNumOfRows(14)
            ->hideOnIndex();
    }
}
