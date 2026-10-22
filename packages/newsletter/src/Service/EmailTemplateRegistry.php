<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Service;

use InvalidArgumentException;

final readonly class EmailTemplateRegistry
{
    /** @param array<string, array{label: string, template: string}> $templates */
    public function __construct(private array $templates)
    {
    }

    /** @return array<string, string> */
    public function choices(): array
    {
        $choices = [];
        foreach ($this->templates as $name => $template) {
            $choices[$template['label']] = 'twig:'.$name;
        }

        return $choices;
    }

    public function template(string $name): string
    {
        return $this->templates[$name]['template'] ?? throw new InvalidArgumentException('Unknown newsletter email template "'.$name.'".');
    }
}
