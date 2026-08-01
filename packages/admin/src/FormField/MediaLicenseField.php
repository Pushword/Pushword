<?php

namespace Pushword\Admin\FormField;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Override;
use Pushword\Admin\EventSubscriber\MediaLicenseFormSubscriber;
use Pushword\Admin\Form\Type\MediaCreatorRowType;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\License\MediaLicense;

use function Safe\json_encode;

/**
 * The six image license properties, as one block.
 *
 * They live in Media::customProperties, so each field is `mapped => false` with its
 * value pushed in as `data`; MediaLicenseFormSubscriber writes the submitted values
 * back. Registering the keys as managed also drops them from the CustomProperties
 * YAML textarea, which would otherwise offer a second, conflicting way to edit them.
 *
 * @extends AbstractField<Media>
 */
final class MediaLicenseField extends AbstractField
{
    /**
     * @return iterable<FieldInterface>
     */
    #[Override]
    public function getEasyAdminField(): iterable
    {
        $subject = $this->admin->getSubject();

        foreach (MediaLicense::KEYS as $key) {
            $subject->registerManagedPropertyKey($key);
        }

        $seed = MediaLicense::normalizeSeed($this->formFieldManager->apps->get()->getArray('media_default_license_seed'));

        yield UrlField::new(MediaLicense::LICENSE, 'adminMediaLicenseLabel')
            ->onlyOnForms()
            ->setHelp('adminMediaLicenseHelp')
            ->setFormTypeOptions($this->options(
                $subject,
                MediaLicense::LICENSE,
                // The state tells the editor why the licensing fields are empty on a
                // media whose file credits somebody else — admin.mediaLicense.js turns
                // it into a note above the buttons.
                ['data-pw-license-state' => $subject->licenseState] + $this->seedAttributes($seed),
            ));

        yield UrlField::new(MediaLicense::ACQUIRE_LICENSE_PAGE, 'adminMediaAcquireLicensePageLabel')
            ->onlyOnForms()
            ->setHelp('adminMediaAcquireLicensePageHelp')
            ->setFormTypeOptions($this->options($subject, MediaLicense::ACQUIRE_LICENSE_PAGE));

        yield TextField::new(MediaLicense::CREDIT_TEXT, 'adminMediaCreditTextLabel')
            ->onlyOnForms()
            ->setFormTypeOptions($this->options($subject, MediaLicense::CREDIT_TEXT));

        // A row per creator, each with its own @type: one node per creator reaches
        // Google, and a photographer credited next to an agency is not the same kind
        // of entity. Rows, not one comma-separated input, so the type cannot drift
        // away from the name it describes.
        yield CollectionField::new(MediaLicense::CREATOR, 'adminMediaCreatorLabel')
            ->onlyOnForms()
            ->setEntryType(MediaCreatorRowType::class)
            // Not "complex": that renders each row as a collapsed accordion whose
            // header is empty, so a list of creators shows as blank chevrons. Two
            // inputs per row are better off inline.
            ->setEntryIsComplex(false)
            ->allowAdd()
            ->allowDelete()
            ->setHelp('adminMediaCreatorHelp')
            ->setFormTypeOptions([
                'required' => false,
                'mapped' => false,
                'by_reference' => false,
                'data' => MediaLicense::creators($subject),
                // row_attr, not attr: the hook has to be the element that carries
                // data-prototype and holds the rows, which is the form row itself.
                'row_attr' => ['data-pw-license-field' => MediaLicense::CREATOR],
            ]);

        // Posted whenever the block was rendered, so removing every row reads as
        // "no creators" instead of "the field was not on this form".
        yield HiddenField::new(MediaLicenseFormSubscriber::CREATOR_MARKER)
            ->onlyOnForms()
            ->setFormTypeOptions(['mapped' => false, 'data' => '1']);

        yield TextField::new(MediaLicense::COPYRIGHT_NOTICE, 'adminMediaCopyrightNoticeLabel')
            ->onlyOnForms()
            ->setFormTypeOptions($this->options($subject, MediaLicense::COPYRIGHT_NOTICE));

        yield ChoiceField::new(MediaLicense::DIGITAL_SOURCE_TYPE, 'adminMediaDigitalSourceTypeLabel')
            ->onlyOnForms()
            // Stored for editorial use only: no schema.org property on ImageObject
            // carries it, so it never reaches Google through our JSON-LD.
            ->setHelp('adminMediaDigitalSourceTypeHelp')
            ->setChoices($this->digitalSourceTypeChoices())
            ->setFormTypeOptions($this->options($subject, MediaLicense::DIGITAL_SOURCE_TYPE));
    }

    /**
     * @param array<string, string> $extraAttributes
     *
     * @return array<string, mixed>
     */
    private function options(Media $media, string $key, array $extraAttributes = []): array
    {
        return [
            'required' => false,
            'mapped' => false,
            'data' => (string) $media->getCustomPropertyScalar($key),
            'attr' => ['data-pw-license-field' => $key] + $extraAttributes,
        ];
    }

    /**
     * The seed reaches admin.mediaLicense.js as data attributes on the first field,
     * so "apply the site license" is a pure client-side fill saved by the normal submit.
     *
     * @param array<string, string|list<array{name: string, type: string}>> $seed
     *
     * @return array<string, string>
     */
    private function seedAttributes(array $seed): array
    {
        $attributes = [];

        foreach ($seed as $key => $value) {
            // Creators travel as JSON: applying the seed means cloning the collection
            // prototype once per name, each with its own type.
            $attributes['data-pw-license-seed-'.strtolower($key)] = \is_array($value)
                ? json_encode($value)
                : $value;
        }

        return $attributes;
    }

    /**
     * @return array<string, string>
     */
    private function digitalSourceTypeChoices(): array
    {
        $choices = [];

        foreach (MediaLicense::DIGITAL_SOURCE_TYPES as $type) {
            $choices[$type] = MediaLicense::DIGITAL_SOURCE_TYPE_PREFIX.$type;
        }

        return $choices;
    }
}
