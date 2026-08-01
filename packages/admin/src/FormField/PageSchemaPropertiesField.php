<?php

namespace Pushword\Admin\FormField;

use DateTimeImmutable;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\HiddenField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Exception;
use Override;
use Pushword\Core\Entity\Page;
use Pushword\Core\PropertySchema\PagePropertySchema;
use Pushword\Core\PropertySchema\PagePropertyType;
use Symfony\Component\Validator\Constraints\Choice;

/**
 * One generated input per `page_properties` declaration for the page's host:
 * the declared type picks the widget, a Choice constraint becomes a dropdown.
 * Fields are unmapped with a preset value; {@see
 * \Pushword\Admin\Form\Extension\SchemaPropertiesWritebackExtension} writes
 * the submitted values back into customProperties. Each key is registered as
 * a schema property so the free-form textarea stops showing it.
 *
 * A hidden marker lists the keys this form actually rendered. The server
 * rebuilds every child on submit, so only a client-side signal can tell a
 * stale form (rendered before a declaration deployed — its fields must not
 * clobber) from a fresh one whose unchecked checkbox is legitimately absent.
 *
 * @extends AbstractField<Page>
 */
class PageSchemaPropertiesField extends AbstractField
{
    public const string RENDERED_MARKER = '_pwSchemaProperties';

    #[Override]
    public function getEasyAdminField(): FieldInterface|iterable|null
    {
        $subject = $this->admin->getSubject();

        $schemas = $this->formFieldManager->schemaRegistry->for('' !== $subject->host ? $subject->host : null);

        $fields = [];
        $names = [];
        foreach ($schemas as $name => $schema) {
            // Dots would break the form child name; managed keys already have
            // a dedicated field of their own.
            if (str_contains($name, '.')) {
                continue;
            }

            if ($subject->isManagedProperty($name)) {
                continue;
            }

            $subject->registerSchemaPropertyKey($name);
            $names[] = $name;
            $fields[] = $this->buildField($subject, $name, $schema);
        }

        if ([] !== $fields) {
            $fields[] = HiddenField::new(self::RENDERED_MARKER, false)
                ->onlyOnForms()
                ->setFormTypeOption('mapped', false)
                ->setFormTypeOption('required', false)
                ->setFormTypeOption('data', implode(',', $names));
        }

        return $fields;
    }

    private function buildField(Page $page, string $name, PagePropertySchema $schema): FieldInterface
    {
        $label = $this->humanize($name);
        $stored = $page->getCustomProperty($name);

        $choices = $this->choiceValues($schema);
        if (null !== $choices) {
            $field = ChoiceField::new($name, $label)
                ->setChoices(array_combine($choices, $choices))
                ->setFormTypeOption('placeholder', '')
                ->setFormTypeOption('data', \is_scalar($stored) ? $stored : null);
        } else {
            $field = match ($schema->type) {
                PagePropertyType::Bool => BooleanField::new($name, $label)
                    ->setFormTypeOption('data', true === $stored),
                PagePropertyType::Int => IntegerField::new($name, $label)
                    ->setFormTypeOption('data', is_numeric($stored) ? (int) $stored : null),
                PagePropertyType::Float => NumberField::new($name, $label)
                    ->setFormTypeOption('data', is_numeric($stored) ? (float) $stored : null),
                PagePropertyType::Date => DateField::new($name, $label)
                    ->setFormTypeOption('data', $this->toDate($stored)),
                PagePropertyType::List => ArrayField::new($name, $label)
                    ->setFormTypeOption('data', \is_array($stored) ? $stored : []),
                PagePropertyType::String => TextField::new($name, $label)
                    ->setFormTypeOption('data', \is_scalar($stored) ? (string) $stored : null),
            };
        }

        return $field->onlyOnForms()
            ->setFormTypeOption('mapped', false)
            ->setFormTypeOption('required', false);
    }

    /** @return non-empty-list<string>|null */
    private function choiceValues(PagePropertySchema $schema): ?array
    {
        foreach ($schema->constraints as $constraint) {
            if (! $constraint instanceof Choice) {
                continue;
            }

            if (null === $constraint->choices) {
                continue;
            }

            $choices = array_values(array_map(static fn (mixed $choice): string => \is_scalar($choice) ? (string) $choice : '', $constraint->choices));
            if ([] !== $choices) {
                return $choices;
            }
        }

        return null;
    }

    /**
     * `price_from` → "Price from", `tocTitle` → "Toc title". The label stays a
     * plain sentence, so a site can translate it as a natural message id.
     */
    private function humanize(string $name): string
    {
        $spaced = preg_replace('/(?<!^)[A-Z]/', ' $0', str_replace('_', ' ', $name)) ?? $name;

        return ucfirst(strtolower(trim($spaced)));
    }

    private function toDate(mixed $stored): ?DateTimeImmutable
    {
        if (\is_int($stored)) {
            return new DateTimeImmutable()->setTimestamp($stored);
        }

        if (\is_string($stored) && '' !== $stored) {
            try {
                return new DateTimeImmutable($stored);
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }
}
