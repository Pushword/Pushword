<?php

namespace Pushword\PageScanner\Scanner;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;

use function Safe\preg_match_all;

use Symfony\Contracts\Translation\TranslatorInterface;

final class TodoScanner extends AbstractScanner
{
    private const string PATTERN = '/<!--\s*TODO:(linkWhenPublished|doWhenPublished)\s+([\w.\-\/]+)(?:\s+"([^"]*)")?\s*-->/i';

    public function __construct(
        TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct($translator);
    }

    protected function run(): void
    {
        $content = $this->page->getMainContent();
        preg_match_all(self::PATTERN, $content, $matches, \PREG_SET_ORDER);

        if (null === $matches || [] === $matches) {
            return;
        }

        foreach ($matches as $match) {
            /** @var array{0: string, 1: string, 2: string, 3?: string} $match */
            $this->checkTodo($match[1], $match[2], $match[3] ?? '');
        }
    }

    private function checkTodo(string $type, string $target, string $label): void
    {
        [$host, $slug] = $this->resolveTarget($target);

        $targetPage = $this->entityManager->getRepository(Page::class)->getPage($slug, $host);

        if (! $targetPage instanceof Page) {
            $this->addError('<code>'.$target.'</code> '.$this->trans('page_scanTodoUnknownPage'));

            return;
        }

        if (! $targetPage->isPublished()) {
            return;
        }

        $translationKey = str_contains($type, 'link') || str_contains($type, 'Link')
            ? 'page_scanTodoLinkWhenPublished'
            : 'page_scanTodoDoWhenPublished';

        $message = '<code>'.$target.'</code> '.$this->trans($translationKey);
        if ('' !== $label) {
            $message .= ' ('.$label.')';
        }

        $this->addError($message);
    }

    /**
     * @return array{string, string} [host, slug]
     */
    private function resolveTarget(string $target): array
    {
        $slashPos = strpos($target, '/');
        if (false !== $slashPos) {
            return [substr($target, 0, $slashPos), substr($target, $slashPos + 1)];
        }

        return [$this->page->host, $target];
    }
}
