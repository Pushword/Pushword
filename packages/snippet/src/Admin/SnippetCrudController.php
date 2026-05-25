<?php

namespace Pushword\Snippet\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\AdminBlockEditor\Form\EditorjsType;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Snippet\Entity\Snippet;
use Pushword\Version\PushwordVersionBundle;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Snippet>
 */
class SnippetCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SiteRegistry $siteRegistry,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Snippet::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        $crud = $crud
            ->setEntityLabelInSingular('snippet.label.singular')
            ->setEntityLabelInPlural('snippet.label.plural')
            ->setDefaultSort(['host' => 'ASC', 'slug' => 'ASC']);

        // Reuse the page block editor for the content field when it is installed.
        if (class_exists(EditorjsType::class)) {
            return $crud->addFormTheme('@PushwordAdminBlockEditor/editorjs_widget.html.twig');
        }

        return $crud;
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('host')
            ->add('slug')
            ->add('name');
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        if (! class_exists(PushwordVersionBundle::class)) {
            return $actions;
        }

        $versionHistory = Action::new('versionHistory', 'snippet.action.versionHistory', 'fa fa-clock-rotate-left')
            ->linkToUrl(fn (Snippet $snippet): string => $this->generateUrl('admin_version_list', ['type' => 'snippet', 'id' => $snippet->id]));

        return $actions
            ->add(Crud::PAGE_INDEX, $versionHistory)
            ->add(Crud::PAGE_EDIT, $versionHistory);
    }

    /**
     * Auto-fill the slug from a slugified name while the slug is left empty
     * (typically on creation). Stops as soon as the editor edits the slug by hand.
     */
    #[Override]
    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addHtmlContentToBody(<<<'HTML'
            <script>
            document.addEventListener('DOMContentLoaded', function () {
                var name = document.querySelector('input[name$="[name]"]');
                var slug = document.querySelector('input[name$="[slug]"]');
                if (null === name || null === slug || '' !== slug.value.trim()) {
                    return;
                }

                var auto = true;
                slug.addEventListener('input', function () { auto = false; });

                function slugify(value) {
                    return value.normalize('NFD').replace(/[̀-ͯ]/g, '')
                        .toLowerCase().trim()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/^-+|-+$/g, '');
                }

                name.addEventListener('input', function () {
                    if (auto) { slug.value = slugify(name.value); }
                });
            });
            </script>
            HTML);
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        if (Crud::PAGE_INDEX === $pageName) {
            yield from $this->indexFields();

            return;
        }

        // Substance in the wide column, metadata in a side fieldset — the same
        // shape as the Page and User forms.
        yield FormField::addColumn('col-12 col-lg-8 mainFields');
        yield FormField::addFieldset();
        yield TextField::new('name', 'snippet.field.name');
        yield TextField::new('slug', 'snippet.field.slug')
            ->setHelp('snippet.field.slug.help');
        yield $this->contentField();

        yield FormField::addColumn('col-12 col-lg-4 columnFields');
        yield FormField::addFieldset('snippet.fieldset.settings');
        yield $this->hostField();
        yield TextField::new('tags', 'snippet.field.tags')->setRequired(false);
    }

    /**
     * Index columns: lead with the editor-facing name, then the reference and
     * the host it serves ("All hosts" for a global snippet).
     *
     * @return iterable<TextField>
     */
    private function indexFields(): iterable
    {
        $allHosts = $this->translator->trans('snippet.field.host.all');

        yield TextField::new('name', 'snippet.field.name');
        yield TextField::new('slug', 'snippet.field.slug');
        yield TextField::new('host', 'snippet.field.host')
            ->formatValue(static fn (?string $value): string => '' === (string) $value ? $allHosts : (string) $value);
        yield TextField::new('tags', 'snippet.field.tags');
    }

    /**
     * Host picker: the configured hosts plus an explicit "All hosts" choice
     * (stored empty) that makes the snippet a global fallback for every host.
     */
    private function hostField(): ChoiceField
    {
        $hosts = $this->siteRegistry->getHosts();

        return ChoiceField::new('host', 'snippet.field.host')
            ->setChoices(['snippet.field.host.all' => ''] + array_combine($hosts, $hosts))
            ->setHelp('snippet.field.host.help')
            ->renderAsNativeWidget()
            ->setFormTypeOption('placeholder', false)
            ->hideOnIndex();
    }

    private function contentField(): TextareaField
    {
        $content = TextareaField::new('content', 'snippet.field.content')->hideOnIndex();

        if (! class_exists(EditorjsType::class)) {
            return $content->setNumOfRows(14);
        }

        $instance = $this->getContext()?->getEntity()?->getInstance();
        $host = $instance instanceof Snippet ? $instance->host : '';

        // page_id is page-only context (used by the PagesList block preview URL);
        // snippets have no page, so it stays empty but the key must exist.
        return $content
            ->setFormType(EditorjsType::class)
            ->setFormTypeOption('attr', ['page_host' => $host, 'page_id' => '']);
    }
}
