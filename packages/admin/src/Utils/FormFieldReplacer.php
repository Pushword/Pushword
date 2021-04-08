<?php

namespace Pushword\Admin\Utils;

class FormFieldReplacer
{
    private int $replaced = 0;

    public function count()
    {
        return $this->replaced;
    }

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

    public static function run2(string $formFieldClass, string $newFormFieldClass, $fields): array
    {
        $key = array_search($formFieldClass, $fields[0]);
        if (false !== $key) {
            $fields[0][$key] = $newFormFieldClass;
        }

        return $fields;
    }
}
