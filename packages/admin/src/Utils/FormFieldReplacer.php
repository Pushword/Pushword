<?php

namespace Pushword\Admin\Utils;

use Pushword\Admin\FormField\AbstractField;

/**
 * @template T of object
 */
class FormFieldReplacer
{
    private int $replaced = 0;

    public function count(): int
    {
        return $this->replaced;
    }

    /**
     * @param class-string<AbstractField<T>>[]|array<class-string<AbstractField<T>>[]>|array{0: class-string<AbstractField<T>>[], 1: (class-string<AbstractField<T>>[] | array<string, (class-string<AbstractField<T>>[] | array{fields: class-string<AbstractField<T>>[], expand: bool})>), 2: class-string<AbstractField<T>>[]} $fields
     */
    public function run(string $formFieldClass, string $newFormFieldClass, array &$fields): void
    {
        foreach (array_keys($fields) as $k) {
            if (\is_array($fields[$k])) {
                $this->run($formFieldClass, $newFormFieldClass, $fields[$k]); // @phpstan-ignore-line

                continue;
            }

            if ($formFieldClass === $fields[$k]) {
                ++$this->replaced;
                $fields[$k] = $newFormFieldClass;  // @phpstan-ignore-line

                break;
            }
        }
    }
}
