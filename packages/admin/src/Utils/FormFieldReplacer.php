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
     * @param class-string<AbstractField<T>>[]|array<class-string<AbstractField<T>>[]> $fields
     */
    public function run(string $formFieldClass, string $newFormFieldClass, array &$fields): void
    {
        foreach ($fields as $k => $field) {
            if (\is_array($field)) {
                $this->run($formFieldClass, $newFormFieldClass, $fields[$k]);

                continue;
            }

            if ($formFieldClass === $field) {
                ++$this->replaced;
                $fields[$k] = $newFormFieldClass;

                break;
            }
        }
    }
}
