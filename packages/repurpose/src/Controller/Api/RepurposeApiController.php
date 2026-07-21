<?php

namespace Pushword\Repurpose\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Repository\SocialPostRepository;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\CarouselSchemaProvider;
use Pushword\Repurpose\Service\ChromiumRasterizer;
use Pushword\Repurpose\Service\ContactSheet;
use Pushword\Repurpose\Service\ContrastAdvisor;
use Pushword\Repurpose\Service\CreatorResolverInterface;
use Pushword\Repurpose\Service\FontPairingRegistry;
use Pushword\Repurpose\Service\FontResolver;
use Pushword\Repurpose\Service\FormatRegistry;
use Pushword\Repurpose\Service\NetworkRegistry;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Token-authenticated REST surface for carousels, built for AI agents: fetch the
 * schema and the network rules, validate a spec without persisting, upsert one
 * addressed by its natural key (host, network, page), and read back a rendered
 * slide SVG or a whole-deck preview PNG. The Symfony Validator is the single
 * source of truth, shared with the CLI lint and the renderer; contrast issues
 * come back as non-blocking `warnings` next to it.
 */
#[IsGranted('ROLE_EDITOR')]
final class RepurposeApiController extends AbstractApiController
{
    public function __construct(
        private readonly CarouselFactory $factory,
        private readonly ValidatorInterface $validator,
        private readonly CarouselSchemaProvider $schemaProvider,
        private readonly FormatRegistry $formats,
        private readonly NetworkRegistry $networks,
        private readonly FontPairingRegistry $fontPairings,
        private readonly FontResolver $fontResolver,
        private readonly SocialPostRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SlideRenderer $renderer,
        private readonly CreatorResolverInterface $creatorResolver,
        private readonly ContrastAdvisor $contrastAdvisor,
        private readonly ContactSheet $contactSheet,
        private readonly ChromiumRasterizer $rasterizer,
    ) {
    }

    #[Route(path: '/api/repurpose/schema', name: 'pushword_api_repurpose_schema', methods: ['GET'])]
    public function schema(): Response
    {
        return new Response($this->schemaProvider->json(), Response::HTTP_OK, ['Content-Type' => 'application/schema+json']);
    }

    #[Route(path: '/api/repurpose/networks', name: 'pushword_api_repurpose_networks', methods: ['GET'])]
    public function networksIndex(): JsonResponse
    {
        $fontPairings = [];
        foreach ($this->fontPairings->all() as $key => $pairing) {
            // `installed: false` means the pairing would silently fall back to
            // Roboto — an agent must only pick installed ones.
            $fontPairings[$key] = [...$pairing, 'installed' => $this->fontResolver->isInstalled($key)];
        }

        return $this->respond([
            'formats' => $this->formats->all(),
            'networks' => $this->networks->all(),
            'fontPairings' => $fontPairings,
        ]);
    }

    #[Route(path: '/api/repurpose/validate', name: 'pushword_api_repurpose_validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $carousel = $this->factory->fromArray($this->decodeJson($request));
        $violations = $this->validator->validate($carousel);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        return $this->respond([
            'valid' => true,
            'slides' => \count($carousel->slides),
            'warnings' => $this->contrastAdvisor->warnings($carousel),
        ]);
    }

    #[Route(path: '/api/repurpose/{host}/{network}/{page}', name: 'pushword_api_repurpose_get', requirements: ['host' => '[^/]+', 'network' => '[a-z]+', 'page' => '.+'], methods: ['GET'])]
    public function get(string $host, string $network, string $page): JsonResponse
    {
        $post = $this->repository->findOneByKey($host, $page, $network);
        if (! $post instanceof SocialPost) {
            return $this->notFound('Carousel not found.');
        }

        return $this->respond($this->payload($post));
    }

    #[Route(path: '/api/repurpose/{host}/{network}/{page}', name: 'pushword_api_repurpose_put', requirements: ['host' => '[^/]+', 'network' => '[a-z]+', 'page' => '.+'], methods: ['PUT', 'PATCH'])]
    public function upsert(Request $request, string $host, string $network, string $page): JsonResponse
    {
        $spec = $this->decodeJson($request);
        // The natural key from the route is authoritative.
        $spec['page'] = $page;
        $spec['network'] = $network;

        $carousel = $this->factory->fromArray($spec);
        $violations = $this->validator->validate($carousel);
        if (\count($violations) > 0) {
            return $this->validationErrors($violations);
        }

        $post = $this->repository->findOneByKey($host, $page, $network) ?? new SocialPost();
        $created = null === $post->id;
        $post->host = $host;
        $post->setSpec($spec);

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        // Echo the persisted slide count and the proof URLs so the agent can
        // confirm and eyeball its work without a follow-up GET.
        return $this->respond(
            [
                'id' => $post->id,
                'page' => $page,
                'network' => $network,
                'status' => $post->getStatus(),
                'warnings' => $this->contrastAdvisor->warnings($carousel),
                ...$this->urls($post, \count($carousel->slides)),
            ],
            $created ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    #[Route(path: '/api/repurpose/{host}/{network}/{page}', name: 'pushword_api_repurpose_delete', requirements: ['host' => '[^/]+', 'network' => '[a-z]+', 'page' => '.+'], methods: ['DELETE'])]
    public function delete(string $host, string $network, string $page): JsonResponse
    {
        $post = $this->repository->findOneByKey($host, $page, $network);
        if (! $post instanceof SocialPost) {
            return $this->notFound('Carousel not found.');
        }

        $this->entityManager->remove($post);
        $this->entityManager->flush();

        return $this->noContent();
    }

    #[Route(path: '/api/repurpose/{id}/slide-{n}.svg', name: 'pushword_api_repurpose_slide', requirements: ['id' => '\d+', 'n' => '\d+'], methods: ['GET'])]
    public function slide(int $id, int $n): Response
    {
        $post = $this->repository->find($id);
        if (! $post instanceof SocialPost) {
            return $this->notFound('Carousel not found.');
        }

        $carousel = $this->factory->fromArray($post->getSpec());
        $creator = $this->creatorResolver->resolve($carousel, $post->host);
        $svg = $this->renderer->renderSlide($carousel, $n - 1, $creator);
        if ('' === $svg) {
            return $this->notFound('Slide out of range.');
        }

        return new Response($svg, Response::HTTP_OK, ['Content-Type' => 'image/svg+xml']);
    }

    /**
     * A contact sheet of the whole deck as one PNG — the one-call, no-browser way
     * for an agent to look at what it just authored. Requires a Chromium binary
     * on the host (`repurpose.chromium_binary` or auto-detected); without one the
     * response degrades to a 501 pointing at the per-slide SVGs.
     */
    #[Route(path: '/api/repurpose/{id}/preview.png', name: 'pushword_api_repurpose_preview', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function preview(int $id): Response
    {
        $post = $this->repository->find($id);
        if (! $post instanceof SocialPost) {
            return $this->notFound('Carousel not found.');
        }

        $carousel = $this->factory->fromArray($post->getSpec());
        $creator = $this->creatorResolver->resolve($carousel, $post->host);
        // Cells at the network's mobile-feed width: text legibility is judged at
        // the realistic worst case, not at a flattering zoom.
        $cellWidth = $this->networks->mobileWidth($carousel->network);
        $sheet = $this->contactSheet->build(
            array_values($this->renderer->renderDeck($carousel, $creator)),
            $this->formats->width($carousel->format),
            $this->formats->height($carousel->format),
            $cellWidth,
            \sprintf('Slides at %dpx — the typical %s mobile feed width', $cellWidth, $carousel->network),
        );

        $png = $this->rasterizer->rasterize($sheet['svg'], $sheet['width'], $sheet['height']);
        if (null === $png) {
            return $this->respond([
                'error' => 'No Chromium binary available to rasterise the preview.',
                'hint' => 'Install chromium (or set repurpose.chromium_binary), or fetch the per-slide SVGs below.',
                'slideUrls' => $this->slideUrls($post, \count($carousel->slides)),
            ], Response::HTTP_NOT_IMPLEMENTED);
        }

        return new Response($png, Response::HTTP_OK, ['Content-Type' => 'image/png']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SocialPost $post): array
    {
        $spec = $post->getSpec();
        $slideCount = \is_array($spec['slides'] ?? null) ? \count($spec['slides']) : 0;

        return [
            'id' => $post->id,
            'host' => $post->host,
            'page' => $post->getPage(),
            'network' => $post->getNetwork(),
            'format' => $post->getFormat(),
            'status' => $post->getStatus(),
            'plannedAt' => $post->getPlannedAt()?->format(\DATE_ATOM),
            'spec' => $spec,
            ...$this->urls($post, $slideCount),
        ];
    }

    /**
     * The slide count plus everything lookable: the studio for humans, the
     * preview PNG and per-slide SVGs for agents.
     *
     * @return array<string, mixed>
     */
    private function urls(SocialPost $post, int $slideCount): array
    {
        return [
            'slides' => $slideCount,
            'studioUrl' => $this->generateUrl('repurpose_studio', ['id' => $post->id], UrlGeneratorInterface::ABSOLUTE_URL),
            'previewUrl' => $this->generateUrl('pushword_api_repurpose_preview', ['id' => $post->id], UrlGeneratorInterface::ABSOLUTE_URL),
            'slideUrls' => $this->slideUrls($post, $slideCount),
        ];
    }

    /**
     * @return list<string>
     */
    private function slideUrls(SocialPost $post, int $slideCount): array
    {
        $urls = [];
        for ($n = 1; $n <= $slideCount; ++$n) {
            $urls[] = $this->generateUrl('pushword_api_repurpose_slide', ['id' => $post->id, 'n' => $n], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return $urls;
    }

    /**
     * @return array<string, mixed>
     */
    public static function describe(): array
    {
        return [
            'paths' => [
                '/api/repurpose/schema' => ['get' => [
                    'summary' => 'JSON Schema of a carousel spec',
                    'tags' => ['Repurpose'],
                    'responses' => ['200' => ['description' => 'JSON Schema']],
                ]],
                '/api/repurpose/networks' => ['get' => [
                    'summary' => 'Formats, per-network rules (limits vs guidance) and font pairings with their installed status',
                    'tags' => ['Repurpose'],
                    'responses' => ['200' => ['description' => 'Formats, networks and fontPairings']],
                ]],
                '/api/repurpose/validate' => ['post' => [
                    'summary' => 'Validate a carousel spec without persisting (plus non-blocking contrast warnings)',
                    'tags' => ['Repurpose'],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
                    'responses' => ['200' => ['description' => 'Valid (may carry warnings)'], '422' => ['description' => 'Validation errors']],
                ]],
                '/api/repurpose/{host}/{network}/{page}' => [
                    'get' => ['summary' => 'Read a carousel (spec, slide count, studio/preview/slide URLs)', 'tags' => ['Repurpose'], 'responses' => ['200' => ['description' => 'Carousel'], '404' => ['description' => 'Not found']]],
                    'put' => ['summary' => 'Create or replace a carousel; echoes the persisted slide count, warnings and proof URLs', 'tags' => ['Repurpose'], 'responses' => ['200' => ['description' => 'Updated'], '201' => ['description' => 'Created'], '422' => ['description' => 'Validation errors']]],
                    'delete' => ['summary' => 'Delete a carousel', 'tags' => ['Repurpose'], 'responses' => ['204' => ['description' => 'Deleted'], '404' => ['description' => 'Not found']]],
                ],
                '/api/repurpose/{id}/slide-{n}.svg' => ['get' => [
                    'summary' => 'Render a slide to a self-contained SVG',
                    'tags' => ['Repurpose'],
                    'responses' => ['200' => ['description' => 'SVG'], '404' => ['description' => 'Not found']],
                ]],
                '/api/repurpose/{id}/preview.png' => ['get' => [
                    'summary' => 'Contact sheet of the whole deck as one PNG (requires Chromium on the host)',
                    'tags' => ['Repurpose'],
                    'responses' => ['200' => ['description' => 'PNG'], '404' => ['description' => 'Not found'], '501' => ['description' => 'No Chromium available (fall back to slide SVGs)']],
                ]],
            ],
        ];
    }
}
