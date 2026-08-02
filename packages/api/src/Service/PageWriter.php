<?php

namespace Pushword\Api\Service;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Entity\User;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The single write operation behind every page intake — JSON body or raw
 * markdown file: apply frontmatter, swap the body, stamp the editor, validate,
 * and flush only when valid. Controllers translate the outcome (violations,
 * {@see InvalidFrontmatterException}) into their own response format.
 */
final readonly class PageWriter
{
    public function __construct(
        private PageFrontmatterMapper $mapper,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<string, mixed> $frontmatter
     *
     * @throws InvalidFrontmatterException
     */
    public function update(Page $page, array $frontmatter, ?string $body, User $editor): ConstraintViolationListInterface
    {
        $this->mapper->applyFrontmatter($page, $frontmatter);

        if (null !== $body) {
            $page->mainContent = $body;
        }

        $page->editedBy = $editor;

        $violations = $this->validator->validate($page);
        if (0 === \count($violations)) {
            $this->entityManager->flush();
        }

        return $violations;
    }

    /**
     * Same operation for a new page. The caller pre-sets slug and host so
     * same-host references (parentPage, variantOf, translations) resolve.
     *
     * @param array<string, mixed> $frontmatter
     *
     * @throws InvalidFrontmatterException
     */
    public function create(Page $page, array $frontmatter, ?string $body, User $editor): ConstraintViolationListInterface
    {
        $host = $page->host;
        $this->mapper->applyFrontmatter($page, $frontmatter);

        if (null !== $body) {
            $page->mainContent = $body;
        }

        // The caller-set host (from the URL) wins over any host field in the payload.
        $page->host = $host;
        $page->editedBy = $editor;
        $page->createdBy = $editor;

        $violations = $this->validator->validate($page);
        if (0 === \count($violations)) {
            $this->entityManager->persist($page);
            $this->entityManager->flush();
        }

        return $violations;
    }
}
