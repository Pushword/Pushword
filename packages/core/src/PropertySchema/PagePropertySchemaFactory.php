<?php

namespace Pushword\Core\PropertySchema;

use Error;
use InvalidArgumentException;
use Symfony\Component\Validator\Constraint;

/**
 * Builds PagePropertySchema value objects from the `page_properties`
 * configuration shape:
 *
 *     price_from: { type: int, constraints: [{ Positive: ~ }] }
 *
 * Constraint entries follow Symfony's validation-YAML shape — a constraint
 * name (short name resolved in Symfony\Component\Validator\Constraints, or a
 * FQCN) mapping to named constructor options. Nested constraint definitions
 * (All, AtLeastOneOf…) are not resolved.
 */
final class PagePropertySchemaFactory
{
    private const string CONSTRAINT_NAMESPACE = 'Symfony\Component\Validator\Constraints\\';

    public static function fromConfig(string $name, mixed $descriptor): PagePropertySchema
    {
        if (! \is_array($descriptor)) {
            throw new InvalidArgumentException(\sprintf('page property `%s`: descriptor must be a map, got %s', $name, get_debug_type($descriptor)));
        }

        $unknown = array_diff(array_keys($descriptor), ['type', 'required', 'constraints']);
        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('page property `%s`: unknown option(s) `%s` (allowed: type, required, constraints)', $name, implode('`, `', $unknown)));
        }

        $rawType = $descriptor['type'] ?? PagePropertyType::String->value;
        $type = \is_string($rawType) ? PagePropertyType::tryFrom($rawType) : null;
        if (! $type instanceof PagePropertyType) {
            throw new InvalidArgumentException(\sprintf('page property `%s`: unknown type `%s` (allowed: %s)', $name, \is_scalar($rawType) ? (string) $rawType : get_debug_type($rawType), implode(', ', PagePropertyType::values())));
        }

        $required = $descriptor['required'] ?? false;
        if (! \is_bool($required)) {
            throw new InvalidArgumentException(\sprintf('page property `%s`: `required` must be a boolean', $name));
        }

        return new PagePropertySchema($name, $type, $required, self::createConstraints($name, $descriptor['constraints'] ?? []));
    }

    /** @return list<Constraint> */
    private static function createConstraints(string $property, mixed $entries): array
    {
        if (! \is_array($entries) || ([] !== $entries && ! array_is_list($entries))) {
            throw new InvalidArgumentException(\sprintf('page property `%s`: `constraints` must be a list', $property));
        }

        $constraints = [];
        foreach ($entries as $entry) {
            if (\is_string($entry)) {
                $constraintName = $entry;
                $options = null;
            } elseif (\is_array($entry) && 1 === \count($entry)) {
                $constraintName = (string) array_key_first($entry);
                $options = $entry[$constraintName];
            } else {
                throw new InvalidArgumentException(\sprintf('page property `%s`: each constraint must be a name or a single `Name: { options }` map', $property));
            }

            $constraints[] = self::createConstraint($property, $constraintName, $options);
        }

        return $constraints;
    }

    private static function createConstraint(string $property, string $constraintName, mixed $options): Constraint
    {
        $class = str_contains($constraintName, '\\') ? $constraintName : self::CONSTRAINT_NAMESPACE.$constraintName;

        if (! class_exists($class) || ! is_a($class, Constraint::class, true)) {
            throw new InvalidArgumentException(\sprintf('page property `%s`: unknown constraint `%s`', $property, $constraintName));
        }

        if (null !== $options && ! \is_array($options)) {
            throw new InvalidArgumentException(\sprintf('page property `%s`: options for `%s` must be a map', $property, $constraintName));
        }

        try {
            // Named-argument spread: options map onto the constraint's
            // constructor parameters, so a typo'd option name fails here.
            return null === $options || [] === $options ? new $class() : new $class(...$options);
        } catch (Error $error) {
            throw new InvalidArgumentException(\sprintf('page property `%s`: invalid options for constraint `%s`: %s', $property, $constraintName, $error->getMessage()), 0, $error);
        }
    }
}
