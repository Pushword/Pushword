<?php

namespace Pushword\Core\Validator\Constraints;

use Pushword\Core\Entity\Page;
use Pushword\Core\PropertySchema\PagePropertySchema;
use Pushword\Core\PropertySchema\PagePropertySchemaRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class constraints run before the `#[Assert\Callback]` that merges the admin
 * textarea into customProperties, so this validator reads the effective view
 * ({@see ExtensiblePropertiesTrait::getEffectiveCustomProperties()}) — the bag
 * as it will be once the merge lands — never the stale pre-submit state.
 */
final class PagePropertiesSchemaValidator extends ConstraintValidator
{
    public function __construct(
        private readonly PagePropertySchemaRegistry $schemaRegistry,
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof PagePropertiesSchema) {
            throw new UnexpectedTypeException($constraint, PagePropertiesSchema::class);
        }

        if (! $value instanceof Page) {
            throw new UnexpectedValueException($value, Page::class);
        }

        $schemas = $this->schemaRegistry->for($value->host);
        if ([] === $schemas) {
            return;
        }

        $path = $value->getValidationAtPath();

        foreach ($value->getEffectiveCustomProperties() as $name => $propertyValue) {
            $schema = $schemas[(string) $name] ?? null;
            if (! $schema instanceof PagePropertySchema || null === $propertyValue) {
                continue; // undeclared keys pass untouched, null clears
            }

            if (! $schema->type->accepts($propertyValue)) {
                $this->context->buildViolation('pagePropertyTypeMismatch')
                    ->setParameter('{{ property }}', (string) $name)
                    ->setParameter('{{ type }}', $schema->type->value)
                    ->atPath($path)
                    ->addViolation();

                continue;
            }

            if ([] === $schema->constraints) {
                continue;
            }

            foreach ($this->validator->validate($propertyValue, $schema->constraints) as $violation) {
                $this->context->buildViolation('pagePropertyInvalid')
                    ->setParameter('{{ property }}', (string) $name)
                    ->setParameter('{{ message }}', (string) $violation->getMessage())
                    ->atPath($path)
                    ->addViolation();
            }
        }
    }
}
