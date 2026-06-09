<?php

namespace Pushword\Version\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Version\Entity\VersionLog;
use Pushword\Version\Versionner;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Read-only admin journal of versionable actions (who changed what, when),
 * ordered by most recent. Backed by the denormalized version_log table, so
 * sorting, pagination and the type/editor/action/host filters are plain SQL —
 * no snapshot files are reopened to render the list.
 *
 * @extends AbstractCrudController<VersionLog>
 */
class VersionLogCrudController extends AbstractCrudController
{
    /** @var array<string, bool> "type:id" => entity still exists, memoized per request. */
    private array $existsCache = [];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return VersionLog::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('versionActivityLog')
            ->setEntityLabelInPlural('versionActivityLog')
            ->setPageTitle(Crud::PAGE_INDEX, 'versionActivityLog')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(50);
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $diff = Action::new('versionLogDiff', 'versionCompare', 'fa fa-code-compare')
            ->linkToUrl(fn (VersionLog $log): string => $this->generateUrl('admin_version_compare', [
                'type' => $log->type,
                'id' => $log->entityId,
                'versionLeft' => $log->version,
                'versionRight' => 'current',
            ]))
            ->displayIf(static fn (VersionLog $log): bool => null !== $log->version);

        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $diff);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('type')
            ->add('action')
            ->add('editor')
            ->add('host')
            ->add('createdAt');
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield DateTimeField::new('createdAt', 'versionDate');
        yield TextField::new('title', 'versionLogEntity')
            ->renderAsHtml()
            ->setSortable(false)
            ->formatValue(fn (?string $value, VersionLog $log): string => $this->renderEntityCell($log));
        yield TextField::new('type', 'versionLogType');
        yield TextField::new('host', 'versionLogHost');
        yield TextField::new('action', 'versionAction')
            ->formatValue(fn (string $value): string => $this->translator->trans('versionLogAction'.ucfirst($value)));
        yield TextField::new('editor', 'versionEditor');
    }

    /**
     * Two-line entity cell: the H1/title on top, the slug below. The top line
     * links to the entity's edit screen when it still exists (a deleted entity
     * stays in the journal as plain text — the action is history, not a link).
     */
    private function renderEntityCell(VersionLog $log): string
    {
        $title = htmlspecialchars($log->title ?? '', \ENT_QUOTES);
        $slug = htmlspecialchars($log->slug ?? '', \ENT_QUOTES);

        $editUrl = $this->editUrlIfExists($log);
        $firstLine = null !== $editUrl ? '<a href="'.$editUrl.'">'.$title.'</a>' : $title;
        $secondLine = '' !== $slug ? '<small class="text-muted d-block">'.$slug.'</small>' : '';

        return $firstLine.$secondLine;
    }

    private function editUrlIfExists(VersionLog $log): ?string
    {
        $class = Versionner::versionableTypes()[$log->type] ?? null;
        if (null === $class) {
            return null;
        }

        $key = $log->type.':'.$log->entityId;
        $this->existsCache[$key] ??= null !== $this->entityManager->getRepository($class)->find($log->entityId);
        if (! $this->existsCache[$key]) {
            return null;
        }

        $route = 'page' === $log->type ? 'admin_page_edit' : 'admin_snippet_edit';

        return $this->generateUrl($route, ['entityId' => $log->entityId]);
    }
}
