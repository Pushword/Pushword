<?php

namespace Pushword\Admin\Utils;

class FormFieldReplacer
{
    private int $replaced = 0;

    public function count(): int
    {
        return $this->replaced;
    }

    /**
     * @param mixed[] $fields
     *
     * @return mixed[]
     */
    public function run(string $formFieldClass, string $newFormFieldClass, array $fields): array
    {
        foreach ($fields as $k => $field) {
            if (\is_array($field)) {
                $fields[$k] = $this->run($formFieldClass, $newFormFieldClass, $field);

                continue;
            }

            if ($formFieldClass === $field) {
                ++$this->replaced;
                $fields[$k] = $newFormFieldClass;

                break;
            }
        }

        return $fields;
    }
}
