<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Controller;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Pushword\Core\Service\EditorialTimezone;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Block\BlockContent;
use Pushword\Newsletter\Block\BlockContext;
use Pushword\Newsletter\Block\BlockRegistry;
use Pushword\Newsletter\Entity\EmailBlock;
use Pushword\Newsletter\Repository\AudienceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[IsGranted('ROLE_EDITOR')]
final class EmailBlockController extends AbstractController
{
    public function __construct(private readonly BlockRegistry $blocks, private readonly SiteRegistry $sites, private readonly EditorialTimezone $timezone, private readonly TranslatorInterface $translator)
    {
    }

    #[Route('/admin/newsletter/block-content', name: 'admin_newsletter_block_content', methods: ['GET'])]
    public function content(Request $request, AudienceRepository $audiences): JsonResponse
    {
        $type = $request->query->getString('provider');
        if ('' === $type) {
            return $this->respond(['providers' => array_map($this->translator->trans(...), $this->blocks->choices())]);
        }

        $audience = $audiences->find($request->query->getInt('audience'));
        if (null === $audience) {
            return $this->respond(['error' => $this->translator->trans('newsletter.campaign.design.empty')], 422);
        }

        try {
            $date = $request->query->getString('scheduledAt');
            $when = '' === $date ? null : DateTimeImmutable::createFromMutable($this->timezone->parse($date));
            $context = new BlockContext($audience->mainHost, $request->query->getString('locale') ?: $this->sites->get($audience->mainHost)->locale, $when);
            $provider = $this->blocks->get($type);
            $reference = $request->query->getString('reference');
            if ('' === $reference) {
                return $this->respond(['items' => array_map(static fn (BlockContent $block): array => $block->toArray($type), $provider->search($request->query->getString('q'), $context))]);
            }

            $parameters = json_decode($request->query->getString('parameters', '{}'), true, 32, \JSON_THROW_ON_ERROR);
            if (! \is_array($parameters) || array_any(array_keys($parameters), static fn (mixed $key): bool => ! \is_string($key))) {
                throw new InvalidArgumentException('Invalid parameters.');
            }

            /** @var array<string, mixed> $parameters */
            $block = $provider->resolve($reference, $parameters, $context);
            if (! $block instanceof BlockContent) {
                return $this->respond(['error' => $this->translator->trans('newsletter.preflight.blockUnavailable')], 404);
            }

            return $this->respond(['item' => $block->toArray($type)]);
        } catch (Throwable) {
            return $this->respond(['error' => $this->translator->trans('newsletter.block.failed')], 422);
        }
    }

    #[Route('/admin/newsletter/block-copy', name: 'admin_newsletter_block_copy', methods: ['POST'])]
    public function copy(Request $request, EntityManagerInterface $manager, ValidatorInterface $validator): JsonResponse
    {
        if (! $this->isCsrfTokenValid('newsletter_block_copy', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $block = new EmailBlock();
        $block->name = $request->request->getString('name');
        $block->bodyMarkdown = $request->request->getString('markdown');
        if (\count($validator->validate($block)) > 0) {
            return $this->respond(['error' => $this->translator->trans('newsletter.block.nameRequired')], 422);
        }

        $manager->persist($block);
        $manager->flush();

        return $this->respond(['id' => $block->id], 201);
    }

    /** @param array<string, mixed> $data */
    private function respond(array $data, int $status = 200): JsonResponse
    {
        return $this->json($data, $status, ['Cache-Control' => 'private, no-store']);
    }
}
