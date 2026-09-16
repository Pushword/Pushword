<?php

declare(strict_types=1);

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;

/**
 * Names the edited entity in an autocompleted association's endpoint URL.
 *
 * EasyAdmin answers an autocomplete request by rebuilding the originating
 * controller's fields against an *empty* subject, then re-applying the field's
 * query-builder callable. A callable filtering on the edited page — "not myself",
 * "same host" — therefore filters on nothing: the dropdown offers the page its own
 * id, and every host's pages. The endpoint URL is generated from `unsetAll()`, so
 * none of the form's context survives into it; naming the edited page is the minimum
 * that puts the callables back in business.
 *
 * A page being created needs nothing here: it has no id to name, and the host its
 * candidates are filtered on comes from the current site in both requests —
 * `PageCrudController::createEntity()` fills it from the site registry, so the two
 * agree without being told.
 *
 * Runs after EasyAdmin's own AssociationConfigurator — which is what generates the
 * URL appended to here — through the priority set in `config/services.php`.
 */
final class AutocompleteSubjectConfigurator implements FieldConfiguratorInterface
{
    public const string SUBJECT_ID_PARAM = 'pwSubjectId';

    private const string ENDPOINT_OPTION = 'attr.data-ea-autocomplete-endpoint-url';

    public function supports(FieldDto $field, EntityDto $entityDto): bool
    {
        return true === $field->getCustomOption(AssociationField::OPTION_AUTOCOMPLETE)
            && \is_string($field->getFormTypeOption(self::ENDPOINT_OPTION));
    }

    public function configure(FieldDto $field, EntityDto $entityDto, AdminContext $context): void
    {
        $id = $entityDto->getPrimaryKeyValue();

        if (! \is_int($id) && ! \is_string($id)) {
            return;
        }

        /** @var string $url */
        $url = $field->getFormTypeOption(self::ENDPOINT_OPTION);

        $field->setFormTypeOption(
            self::ENDPOINT_OPTION,
            $url.'&'.http_build_query([self::SUBJECT_ID_PARAM => $id]),
        );
    }
}
