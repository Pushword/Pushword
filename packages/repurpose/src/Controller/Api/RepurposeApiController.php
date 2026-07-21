<?php

namespace Pushword\Repurpose\Controller\Api;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Api\Controller\AbstractApiController;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Repository\SocialPostRepository;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\CarouselSchemaProvider;
use Pushword\Repurpose\Service\CreatorResolverInterface;
use Pushword\Repurpose\Service\FormatRegistry;
use Pushword\Repurpose\Service\NetworkRegistry;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Token-authenticated REST surface for carousels, built for AI agents: fetch the
 * schema and the network rules, validate a spec without persisting, upsert one
 * addressed by its natural key (host, network, page), and read back a rendered
 * slide SVG. The Symfony Validator is the single source of truth, shared with the
 * CLI lint and the renderer.
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
        private readonly SocialPostRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SlideRenderer $renderer,
        private readonly CreatorResolverInterface $creatorResolver,
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
        return $this->respond([
            'formats' => $this->formats->all(),
            'networks' => $this->networks->all(),
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

        return $this->respond(['valid' => true, 'slides' => \count($carousel->slides)]);
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

        return $this->respond(
            ['id' => $post->id, 'page' => $page, 'network' => $network, 'status' => $post->getStatus()],
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
     * @return array<string, mixed>
     */
    private function payload(SocialPost $post): array
    {
        return [
            'id' => $post->id,
            'host' => $post->host,
            'page' => $post->getPage(),
            'network' => $post->getNetwork(),
            'format' => $post->getFormat(),
            'status' => $post->getStatus(),
            'plannedAt' => $post->getPlannedAt()?->format(\DATE_ATOM),
            'spec' => $post->getSpec(),
        ];
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
                    'summary' => 'Formats and per-network rules (limits vs guidance)',
                    'tags' => ['Repurpose'],
                    'responses' => ['200' => ['description' => 'Formats and networks']],
                ]],
                '/api/repurpose/validate' => ['post' => [
                    'summary' => 'Validate a carousel spec without persisting',
                    'tags' => ['Repurpose'],
                    'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => ['type' => 'object']]]],
                    'responses' => ['200' => ['description' => 'Valid'], '422' => ['description' => 'Validation errors']],
                ]],
                '/api/repurpose/{host}/{network}/{page}' => [
                    'get' => ['summary' => 'Read a carousel', 'tags' => ['Repurpose'], 'responses' => ['200' => ['description' => 'Carousel'], '404' => ['description' => 'Not found']]],
                    'put' => ['summary' => 'Create or replace a carousel', 'tags' => ['Repurpose'], 'responses' => ['200' => ['description' => 'Updated'], '201' => ['description' => 'Created'], '422' => ['description' => 'Validation errors']]],
                    'delete' => ['summary' => 'Delete a carousel', 'tags' => ['Repurpose'], 'responses' => ['204' => ['description' => 'Deleted'], '404' => ['description' => 'Not found']]],
                ],
                '/api/repurpose/{id}/slide-{n}.svg' => ['get' => [
                    'summary' => 'Render a slide to a self-contained SVG',
                    'tags' => ['Repurpose'],
                    'responses' => ['200' => ['description' => 'SVG'], '404' => ['description' => 'Not found']],
                ]],
            ],
        ];
    }
}
