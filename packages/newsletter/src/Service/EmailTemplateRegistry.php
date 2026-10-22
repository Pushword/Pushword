<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Service;

use InvalidArgumentException;
use Pushword\Newsletter\Entity\EmailTemplate;
use Pushword\Newsletter\Repository\EmailTemplateRepository;

final readonly class EmailTemplateRegistry
{
    /** @param array<string, array{label: string, template: string}> $templates */
    public function __construct(private array $templates, private EmailTemplateRepository $savedTemplates)
    {
    }

    /** @return array<string, array<string, string>> */
    public function choices(): array
    {
        $saved = [];
        foreach ($this->savedTemplates->findBy([], ['name' => 'ASC']) as $template) {
            $saved[$template->name] = 'saved:'.$template->id;
        }

        return array_filter([
            'newsletter.template.savedGroup' => $saved,
            'newsletter.template.siteGroup' => $this->siteChoices(),
        ]);
    }

    /** @return array<string, string> */
    public function siteChoices(): array
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

    public function saved(string $layout): ?EmailTemplate
    {
        if (! str_starts_with($layout, 'saved:')) {
            return null;
        }

        return $this->savedTemplates->find(substr($layout, 6))
            ?? throw new InvalidArgumentException('Unknown newsletter saved template "'.$layout.'".');
    }
}
