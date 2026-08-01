<?php

namespace Pushword\Admin\Form\Extension;

use DateTimeInterface;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\CrudFormType;
use Pushword\Admin\FormField\PageSchemaPropertiesField;
use Pushword\Core\Entity\Page;
use Pushword\Core\PropertySchema\PagePropertySchema;
use Pushword\Core\PropertySchema\PagePropertySchemaRegistry;
use Pushword\Core\PropertySchema\PagePropertyType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

/**
 * Writes the generated schema-property fields back into customProperties,
 * through the form — never by scraping the raw request.
 *
 * Absent is not empty. The server rebuilds every child on submit, so the
 * truth about what the client's form rendered comes from the hidden marker
 * ({@see PageSchemaPropertiesField::RENDERED_MARKER}): no marker means a
 * stale form — nothing is written, and the stale textarea copy writes
 * through the merge instead. A marker-listed key is authoritative: empty
 * clears, an unchecked checkbox clears, a value wins.
 *
 * Two passes around Symfony's ValidationListener (POST_SUBMIT, priority 0):
 * the first lands the values before validation so the schema constraint
 * checks what was submitted; the second re-asserts them after the textarea
 * merge, so a key deliberately retyped into the free-form textarea of a
 * fresh form does not out-write its own field.
 */
final class SchemaPropertiesWritebackExtension extends AbstractTypeExtension
{
    public function __construct(private readonly PagePropertySchemaRegistry $schemaRegistry)
    {
    }

    public static function getExtendedTypes(): iterable
    {
        return [CrudFormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->writeBack(...), 10);
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->writeBack(...), -10);
    }

    private function writeBack(FormEvent $formEvent): void
    {
        $page = $formEvent->getData();
        if (! $page instanceof Page) {
            return;
        }

        $form = $formEvent->getForm();
        $rendered = $this->renderedKeys($form);
        if ([] === $rendered) {
            return;
        }

        foreach ($this->schemaRegistry->for('' !== $page->host ? $page->host : null) as $name => $schema) {
            if (! isset($rendered[$name])) {
                continue;
            }

            if (! $form->has($name)) {
                continue;
            }

            $child = $form->get($name);
            if (false !== $child->getConfig()->getOption('mapped')) {
                continue; // only the generated (unmapped) fields belong to us
            }

            $value = $this->normalize($schema, $child->getData());
            null === $value
                ? $page->removeCustomProperty($name)
                : $page->setCustomProperty($name, $value);
        }
    }

    /**
     * @param FormInterface<mixed> $form
     *
     * @return array<string, true> the keys the client's form actually rendered
     */
    private function renderedKeys(FormInterface $form): array
    {
        if (! $form->has(PageSchemaPropertiesField::RENDERED_MARKER)) {
            return [];
        }

        $marker = $form->get(PageSchemaPropertiesField::RENDERED_MARKER)->getData();
        if (! \is_string($marker) || '' === $marker) {
            return [];
        }

        return array_fill_keys(explode(',', $marker), true);
    }

    /**
     * Empty clears the key rather than storing noise: '' and [] carry no
     * information, and an unchecked bool means "not set" — templates test
     * `null !== page.toc`, so a stored false would still enable them.
     */
    private function normalize(PagePropertySchema $schema, mixed $value): mixed
    {
        if (null === $value || '' === $value || [] === $value || false === $value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            // The same shape an unquoted frontmatter date yields.
            return $value->getTimestamp();
        }

        if (PagePropertyType::List === $schema->type && \is_array($value)) {
            return array_values($value);
        }

        return $value;
    }
}
