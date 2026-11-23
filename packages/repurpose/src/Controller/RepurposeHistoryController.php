<?php

declare(strict_types=1);

namespace Pushword\Repurpose\Controller;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Entity\SocialPostRevision;
use Pushword\Repurpose\Repository\SocialPostRevisionRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
#[Route('/admin/repurpose/studio/{id}/history', name: 'repurpose_history_', requirements: ['id' => '\d+'])]
final class RepurposeHistoryController extends AbstractController
{
    public function __construct(
        private readonly SocialPostRevisionRepository $revisions,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(#[MapEntity(id: 'id')] SocialPost $post, Request $request): Response
    {
        $versions = $this->revisions->datesFor($post);
        $version = $request->query->has('version') ? $request->query->getInt('version') : array_key_first($versions);
        $selected = null === $version ? null : $this->findRevision($post, $version);
        $compared = '' === $request->query->getString('compare') ? null : $this->findRevision($post, $request->query->getInt('compare'));

        return $this->render('@PushwordRepurpose/history.html.twig', [
            'post' => $post,
            'versions' => $versions,
            'selected' => $selected,
            'compared' => $compared,
            'differences' => $selected instanceof SocialPostRevision ? $this->differences($selected->spec, $compared->spec ?? $post->spec) : [],
        ], new Response(headers: ['Cache-Control' => 'no-store']));
    }

    #[Route('/{version}/restore', name: 'restore', requirements: ['version' => '\d+'], methods: ['POST'])]
    public function restore(#[MapEntity(id: 'id')] SocialPost $post, int $version, Request $request): RedirectResponse
    {
        if (! $this->isCsrfTokenValid('repurpose_restore_'.$post->id.'_'.$version, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $selected = $this->findRevision($post, $version);
        if ($post->revision() !== $request->request->getString('revision')) {
            return $this->restoreFailed($selected, 'repurposeHistoryConflict');
        }

        $post->spec = $selected->spec;

        try {
            $this->entityManager->flush();
        } catch (OptimisticLockException) {
            return $this->restoreFailed($selected, 'repurposeHistoryConflict');
        } catch (UniqueConstraintViolationException) {
            return $this->restoreFailed($selected, 'repurposeHistoryPageConflict');
        }

        $this->addFlash('repurpose_history_restored', 'repurposeHistoryRestored');

        return $this->redirectToRoute('repurpose_studio', ['id' => $post->id], Response::HTTP_SEE_OTHER);
    }

    private function restoreFailed(SocialPostRevision $revision, string $message): RedirectResponse
    {
        $this->addFlash('repurpose_history_error', $message);

        return $this->redirectToRoute('repurpose_history_index', ['id' => $revision->post->id, 'version' => $revision->version], Response::HTTP_SEE_OTHER);
    }

    private function findRevision(SocialPost $post, int $version): SocialPostRevision
    {
        return $this->revisions->findFor($post, $version) ?? throw $this->createNotFoundException('Carousel revision not found.');
    }

    /**
     * @param array<array-key, mixed> $left
     * @param array<array-key, mixed> $right
     *
     * @return list<array{field: string, left: ?string, right: ?string}>
     */
    private function differences(array $left, array $right, string $prefix = ''): array
    {
        $changes = [];
        foreach (array_keys($left + $right) as $key) {
            $previous = $left[$key] ?? null;
            $current = $right[$key] ?? null;
            $hasPrevious = array_key_exists($key, $left);
            $hasCurrent = array_key_exists($key, $right);
            if ($previous === $current && $hasPrevious === $hasCurrent) {
                continue;
            }

            $field = \is_int($key) ? $prefix.'['.($key + 1).']' : ltrim($prefix.'.'.$key, '.');
            if (\is_array($previous) && \is_array($current)) {
                array_push($changes, ...$this->differences($previous, $current, $field));

                continue;
            }

            $changes[] = [
                'field' => $field,
                'left' => $hasPrevious ? $this->displayValue($previous) : null,
                'right' => $hasCurrent ? $this->displayValue($current) : null,
            ];
        }

        return $changes;
    }

    private function displayValue(mixed $value): string
    {
        return \is_string($value) ? $value : json_encode($value, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
