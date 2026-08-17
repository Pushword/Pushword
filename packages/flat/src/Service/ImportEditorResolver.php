<?php

namespace Pushword\Flat\Service;

use Pushword\Core\Entity\User;
use Pushword\Core\Repository\UserRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Who a flat import is attributed to.
 *
 * A `pw:flat:sync` run has no authenticated user, so a page born from a file used to land
 * with editedBy/createdBy null and stay that way — until the first admin save silently
 * claimed authorship of it. Two sources answer for it instead: the file itself, which the
 * export stamps with the editor's email (User is Stringable), and failing that the site's
 * editor of record — `default_editor`, or the first super admin.
 *
 * A file naming nobody known is left unattributed rather than attributed wrongly.
 */
final class ImportEditorResolver implements ResetInterface
{
    private ?User $defaultEditor = null;

    private bool $defaultEditorResolved = false;

    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly ?string $defaultEditorEmail = null,
    ) {
    }

    /**
     * Worker-mode safety (kernel.reset): the cached editor is a managed entity, detached
     * as soon as the entity manager is cleared — which every sync does between batches.
     * Handing that stale object to the next import would associate a page with an entity
     * Doctrine no longer knows.
     */
    public function reset(): void
    {
        $this->defaultEditor = null;
        $this->defaultEditorResolved = false;
    }

    /** The user a frontmatter `editedBy:`/`createdBy:` names, when it names one we know. */
    public function resolve(mixed $email): ?User
    {
        if (! \is_string($email) || '' === $email) {
            return null;
        }

        return $this->userRepo->findOneBy(['email' => $email]);
    }

    /** Who wrote a file that signed itself with nothing. */
    public function getDefaultEditor(): ?User
    {
        if ($this->defaultEditorResolved) {
            return $this->defaultEditor;
        }

        $this->defaultEditorResolved = true;

        return $this->defaultEditor = null !== $this->defaultEditorEmail
            ? $this->resolve($this->defaultEditorEmail)
            : $this->findFirstSuperAdmin();
    }

    private function findFirstSuperAdmin(): ?User
    {
        // Roles are a JSON column: LIKE narrows the scan to plausible rows, getRoles()
        // then decides — the pattern would also match a role merely starting with it.
        $candidates = $this->userRepo->createQueryBuilder('u')
            ->where('JSON_TEXT(u.roles) LIKE :role')
            ->setParameter('role', '%'.User::ROLE_SUPER_ADMIN.'%')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($candidates as $candidate) {
            if (\in_array(User::ROLE_SUPER_ADMIN, $candidate->getRoles(), true)) {
                return $candidate;
            }
        }

        return null;
    }
}
