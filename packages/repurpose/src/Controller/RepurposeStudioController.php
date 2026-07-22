<?php

namespace Pushword\Repurpose\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Pushword\Core\Entity\Page;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Repurpose\Entity\SocialPost;
use Pushword\Repurpose\Model\Carousel;
use Pushword\Repurpose\Model\Counter;
use Pushword\Repurpose\Model\Slide;
use Pushword\Repurpose\Repository\SocialPostRepository;
use Pushword\Repurpose\Service\BackgroundEffectRegistry;
use Pushword\Repurpose\Service\CarouselFactory;
use Pushword\Repurpose\Service\ContrastAdvisor;
use Pushword\Repurpose\Service\CreatorAdvisor;
use Pushword\Repurpose\Service\CreatorResolverInterface;
use Pushword\Repurpose\Service\ExportBuilder;
use Pushword\Repurpose\Service\FontPairingRegistry;
use Pushword\Repurpose\Service\FormatRegistry;
use Pushword\Repurpose\Service\NetworkRegistry;
use Pushword\Repurpose\Service\PinImageStore;
use Pushword\Repurpose\Service\PinterestShare;
use Pushword\Repurpose\Service\SlideRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The carousel studio: renders a stored SocialPost's deck to inlined SVG slides
 * for preview and export. The SVGs are the canonical artifact — the same bytes an
 * agent reads and the exporter rasterises.
 */
#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class RepurposeStudioController extends AbstractController
{
    public function __construct(
        private readonly SocialPostRepository $repository,
        private readonly CarouselFactory $factory,
        private readonly SlideRenderer $renderer,
        private readonly ContrastAdvisor $contrastAdvisor,
        private readonly CreatorAdvisor $creatorAdvisor,
        private readonly CreatorResolverInterface $creatorResolver,
        private readonly ExportBuilder $exportBuilder,
        private readonly PinImageStore $pinImageStore,
        private readonly PinterestShare $pinterestShare,
        private readonly NetworkRegistry $networks,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $entityManager,
        private readonly SiteRegistry $siteRegistry,
    ) {
    }

    #[Route('/admin/repurpose/studio/{id}', name: 'repurpose_studio', requirements: ['id' => '\d+'])]
    public function studio(int $id, Request $request): Response
    {
        $post = $this->loadPost($id);

        $carousel = $this->factory->fromArray($post->getSpec());
        $creator = $this->creatorResolver->resolve($carousel, $post->host);

        // Safe to embed inside a <script> block (escapes </script>, quotes, etc.).
        $scriptFlags = \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_HEX_AMP | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE;

        $networkUrls = [];
        foreach (NetworkRegistry::keys() as $network) {
            $networkUrls[$network] = $this->generateUrl('repurpose_studio_network', ['id' => $post->id, 'network' => $network]);
        }

        return $this->render('@PushwordRepurpose/studio.html.twig', [
            'post' => $post,
            'carousel' => $carousel,
            // Slides preview at the network's mobile-feed width, so "is the text
            // big enough?" is judged at the size a scrolling viewer actually sees.
            'feedWidth' => $this->networks->mobileWidth($carousel->network),
            'backUrl' => $this->backUrl($request),
            'specJs' => json_encode($post->getSpec(), $scriptFlags),
            'slidesJs' => json_encode($this->renderer->renderDeck($carousel, $creator), $scriptFlags),
            'vocabJs' => json_encode($this->vocabulary($carousel->network, $post->host), $scriptFlags),
            'backgroundEffectsJs' => json_encode($this->backgroundEffects(), $scriptFlags),
            'defaultsJs' => json_encode([
                'bg' => SlideRenderer::DEFAULT_BG,
                'text' => SlideRenderer::DEFAULT_TEXT,
                'accent' => SlideRenderer::DEFAULT_ACCENT,
            ], $scriptFlags),
            'networkUrlsJs' => json_encode($networkUrls, $scriptFlags),
            'pageSlugs' => $this->pageSlugsForHost($post->host),
        ]);
    }

    /**
     * Create a page-less "standalone" carousel — a blank draft not derived from any
     * page — on the default host and open it in the studio. Standalone posts are
     * keyed by a generated `standalone/{token}` slug so identity, per-network
     * uniqueness and flat-file sync all keep working unchanged; the studio can later
     * link it to a real page (or detach it again).
     */
    #[Route('/admin/repurpose/studio/new', name: 'repurpose_studio_new', methods: ['GET'])]
    public function create(): RedirectResponse
    {
        $post = new SocialPost();
        $post->host = $this->siteRegistry->getHosts()[0] ?? '';
        $post->setSpec([
            'page' => $this->mintStandaloneSlug(),
            'network' => 'linkedin',
            'format' => 'linkedin-4-5',
            'status' => 'draft',
            'slides' => [['layout' => 'center', 'align' => 'center', 'title' => 'New carousel']],
        ]);

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        return $this->redirectToRoute('repurpose_studio', ['id' => $post->id]);
    }

    /**
     * Switch the studio to another network for the same page: reuse that network's
     * existing carousel, or clone this one's content into a fresh draft (retargeted
     * to the network's primary format) so a page can be recut for every platform
     * from one starting point. A carousel's own network is fixed — this navigates
     * to a *sibling* post, it never re-keys the current one.
     */
    #[Route('/admin/repurpose/studio/{id}/network/{network}', name: 'repurpose_studio_network', requirements: ['id' => '\d+', 'network' => '[a-z]+'], methods: ['GET'])]
    public function switchNetwork(int $id, string $network): RedirectResponse
    {
        $post = $this->loadPost($id);

        if (! \in_array($network, NetworkRegistry::keys(), true)) {
            throw new NotFoundHttpException('Unknown network.');
        }

        if ($network === $post->getNetwork()) {
            return $this->redirectToRoute('repurpose_studio', ['id' => $id]);
        }

        $sibling = $this->repository->findOneBy(['host' => $post->host, 'page' => $post->getPage(), 'network' => $network])
            ?? $this->createSiblingForNetwork($post, $network);

        return $this->redirectToRoute('repurpose_studio', ['id' => $sibling->id]);
    }

    /**
     * Re-renders an edited spec to SVG slides **without persisting** — the studio
     * calls this on every keystroke for a live preview. Invalid specs come back as
     * `{path, message}` violations so the editor lints as you type; the deck only
     * updates once the spec is valid.
     */
    #[Route('/admin/repurpose/studio/{id}/preview', name: 'repurpose_studio_preview', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function preview(int $id, Request $request): JsonResponse
    {
        $post = $this->loadPost($id);

        $spec = $this->decodePinnedSpec($post, $request);
        if (null === $spec) {
            return new JsonResponse(['error' => 'Expected a JSON carousel spec.'], Response::HTTP_BAD_REQUEST);
        }

        $carousel = $this->factory->fromArray($spec);
        $violations = $this->violations($carousel);
        if ([] !== $violations) {
            return new JsonResponse(['violations' => $violations], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $creator = $this->creatorResolver->resolve($carousel, $post->host);

        return new JsonResponse([
            'slides' => $this->renderer->renderDeck($carousel, $creator),
            'warnings' => [
                ...$this->contrastAdvisor->warnings($carousel),
                ...$this->creatorAdvisor->warnings($carousel, $post->host),
            ],
        ]);
    }

    /**
     * Persists an edited spec from the studio's editor. The post's identity
     * (host, page, network) is fixed here — the studio edits a carousel's content,
     * never re-keys it — so page/network are forced from the stored post. The spec
     * runs through the same validator the agent and CLI use; violations come back
     * as `{path, message}` for inline display, nothing is saved until it is valid.
     */
    #[Route('/admin/repurpose/studio/{id}/save', name: 'repurpose_studio_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function save(int $id, Request $request): JsonResponse
    {
        $post = $this->loadPost($id);

        $spec = $this->decodePinnedSpec($post, $request);
        if (null === $spec) {
            return new JsonResponse(['error' => 'Expected a JSON carousel spec.'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->violations($this->factory->fromArray($spec));
        if ([] !== $violations) {
            return new JsonResponse(['violations' => $violations], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Re-linking to another page must not collide with an existing carousel on
        // the same (host, page, network) key.
        $page = \is_string($spec['page'] ?? null) ? $spec['page'] : $post->getPage();
        $existing = $this->repository->findOneByKey($post->host, $page, $post->getNetwork());
        if (null !== $existing && $existing->id !== $post->id) {
            return new JsonResponse(['violations' => [['path' => 'page', 'message' => 'A carousel already exists for this page on this network.']]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $post->setSpec($spec);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Returns a downloadable `.zip`. Two payloads, one endpoint:
     *  - `{slides: [dataURL, …]}` — the browser-rasterised PNGs → PNGs + caption
     *    (plus a PDF for document networks);
     *  - `{svgs: [svg, …]}` — the on-screen self-contained SVGs → a vector archive
     *    of `.svg` files + caption, no rasterisation.
     */
    #[Route('/admin/repurpose/studio/{id}/export', name: 'repurpose_studio_export', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function export(int $id, Request $request): Response
    {
        $post = $this->loadPost($id);
        $carousel = $this->factory->fromArray($post->getSpec());

        $data = json_decode($request->getContent(), true);

        if (\is_array($data) && isset($data['svgs']) && \is_array($data['svgs'])) {
            $svgs = array_values(array_filter($data['svgs'], static fn (mixed $s): bool => \is_string($s) && '' !== $s));
            $zip = $this->exportBuilder->buildSvgArchive($svgs, $carousel->caption ?? '', array_values($carousel->hashtags));

            return $this->zipResponse($zip, $this->slugFilename($post->getPage(), $carousel->network).'-svg');
        }

        if (! \is_array($data) || ! isset($data['slides']) || ! \is_array($data['slides'])) {
            return new JsonResponse(['error' => 'Expected {slides: [dataURL, …]} or {svgs: [svg, …]}.'], Response::HTTP_BAD_REQUEST);
        }

        $pngs = [];
        foreach ($data['slides'] as $dataUrl) {
            if (\is_string($dataUrl)) {
                $pngs[] = base64_decode((string) preg_replace('#^data:image/\w+;base64,#', '', $dataUrl), true) ?: '';
            }
        }

        $pngs = array_values(array_filter($pngs, static fn (string $p): bool => '' !== $p));

        $withPdf = 'pdf' === ($this->networks->get($carousel->network)['export'] ?? 'images');

        $zip = $this->exportBuilder->build($pngs, $carousel->caption ?? '', array_values($carousel->hashtags), $withPdf);

        return $this->zipResponse($zip, $this->slugFilename($post->getPage(), $carousel->network));
    }

    private function zipResponse(string $zip, string $filename): Response
    {
        return new Response($zip, Response::HTTP_OK, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.zip"',
        ]);
    }

    /**
     * Stores the browser-rasterised cover slide as a public PNG and returns the
     * Pinterest "create pin" URL pre-filled with it (plus the page URL and caption).
     * The studio opens that URL so the user finishes the pin in Pinterest itself —
     * only Pinterest carousels get this, since the widget takes one image.
     */
    #[Route('/admin/repurpose/studio/{id}/pin-image', name: 'repurpose_studio_pin_image', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function pinImage(int $id, Request $request): JsonResponse
    {
        $post = $this->loadPost($id);
        $carousel = $this->factory->fromArray($post->getSpec());

        if ('pinterest' !== $carousel->network) {
            return new JsonResponse(['error' => 'Direct pinning is only available for Pinterest carousels.'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $dataUrl = \is_array($data) ? ($data['png'] ?? null) : null;
        if (! \is_string($dataUrl)) {
            return new JsonResponse(['error' => 'Expected {png: dataURL}.'], Response::HTTP_BAD_REQUEST);
        }

        $png = base64_decode((string) preg_replace('#^data:image/\w+;base64,#', '', $dataUrl), true);
        if (false === $png || '' === $png) {
            return new JsonResponse(['error' => 'The pin image could not be decoded.'], Response::HTTP_BAD_REQUEST);
        }

        $mediaUrl = $request->getSchemeAndHttpHost().$this->pinImageStore->save($id, $png);
        $pinUrl = $this->pinterestShare->pinUrl($mediaUrl, $this->pageUrl($post), $carousel->caption);

        return new JsonResponse(['url' => $mediaUrl, 'pinUrl' => $pinUrl]);
    }

    /**
     * The public URL of the carousel's source page (`https://{host}/{slug}`), or
     * null for a page-less standalone carousel — used as the pin's link-back.
     */
    private function pageUrl(SocialPost $post): ?string
    {
        $page = $post->getPage();
        if ('' === $page || str_starts_with($page, 'standalone/')) {
            return null;
        }

        return 'https://'.$post->host.'/'.ltrim($page, '/');
    }

    private function loadPost(int $id): SocialPost
    {
        return $this->repository->find($id) ?? throw new NotFoundHttpException('Social post not found.');
    }

    /**
     * A fresh slug for a page-less carousel. The `standalone/` prefix keeps these
     * out of any real page's namespace while giving sync a stable folder.
     */
    private function mintStandaloneSlug(): string
    {
        return 'standalone/'.bin2hex(random_bytes(4));
    }

    /**
     * The host's page slugs, for the studio's page-link datalist (capped — it is a
     * suggestion list, any slug can still be typed).
     *
     * @return list<string>
     */
    private function pageSlugsForHost(string $host): array
    {
        /** @var list<string> $slugs */
        $slugs = $this->entityManager
            ->createQuery('SELECT p.slug FROM '.Page::class.' p WHERE p.host = :host ORDER BY p.slug ASC')
            ->setParameter('host', $host)
            ->setMaxResults(500)
            ->getSingleColumnResult();

        return $slugs;
    }

    /**
     * Clone the current carousel's content into a sibling post for another network,
     * retargeting its format to that network's primary one.
     */
    private function createSiblingForNetwork(SocialPost $post, string $network): SocialPost
    {
        $spec = $post->getSpec();
        $spec['network'] = $network;

        $formats = NetworkRegistry::formatsFor($network);
        if ([] !== $formats) {
            $spec['format'] = $formats[0];
        }

        $sibling = new SocialPost();
        $sibling->host = $post->host;
        $sibling->setSpec($spec);

        $this->entityManager->persist($sibling);
        $this->entityManager->flush();

        return $sibling;
    }

    /**
     * Where "← Back" returns: the page the user came from (the social-post list, a
     * page screen…), falling back to the admin home. A studio-to-studio referer is
     * ignored so Back never loops on itself.
     */
    private function backUrl(Request $request): string
    {
        $referer = $request->headers->get('referer');

        return \is_string($referer) && '' !== $referer && ! str_contains($referer, '/repurpose/studio/')
            ? $referer
            : '/admin';
    }

    /**
     * The controlled vocabularies the visual editor's dropdowns and sliders need:
     * the formats allowed for this network, plus every enum the validator enforces.
     * Sourced from the same registries and model constants, so the editor can only
     * offer choices the validator accepts. `creators` maps this host's configured
     * creator keys to display names for the byline picker.
     *
     * @return array<string, list<string>|array<string, string>>
     */
    private function vocabulary(string $network, string $host): array
    {
        $formats = NetworkRegistry::formatsFor($network);

        return [
            'networks' => NetworkRegistry::keys(),
            'formats' => [] === $formats ? FormatRegistry::ids() : $formats,
            'fontPairings' => FontPairingRegistry::keys(),
            'layouts' => Slide::LAYOUTS,
            'aligns' => Slide::ALIGNS,
            'imageLayouts' => Slide::IMAGE_LAYOUTS,
            'statuses' => Carousel::STATUSES,
            'counterStyles' => Counter::STYLES,
            'counterAligns' => Counter::ALIGNS,
            'creators' => $this->creatorResolver->available($host),
            'creatorOrientations' => Carousel::CREATOR_ORIENTATIONS,
            'creatorOnSlides' => Carousel::CREATOR_ON_SLIDES,
        ];
    }

    /**
     * The background-effect catalogue the studio's picker modal shows: each effect
     * with its category and a self-contained thumbnail SVG rendered by the same
     * painter the deck uses, so the preview always matches the real output.
     *
     * @return list<array{key: string, label: string, category: string, preview: string}>
     */
    private function backgroundEffects(): array
    {
        $effects = [];
        foreach (BackgroundEffectRegistry::EFFECTS as $key => $effect) {
            $effects[] = [
                'key' => $key,
                'label' => $effect['label'],
                'category' => $effect['category'],
                'preview' => $this->renderer->effectPreview($key),
            ];
        }

        return $effects;
    }

    /**
     * Decode the request body into a spec, or null when the body is not a JSON
     * object. The network is fixed here — switching network is a separate navigation
     * that spawns a sibling post, never an in-place re-key — so it is always forced
     * from the stored post. The `page` is editable (the studio can re-link a carousel
     * to another page or detach it to a standalone slug), so it comes from the edited
     * spec; an empty page falls back to the stored one to keep the row keyable.
     *
     * @return array<string, mixed>|null
     */
    private function decodePinnedSpec(SocialPost $post, Request $request): ?array
    {
        $spec = json_decode($request->getContent(), true);
        if (! \is_array($spec)) {
            return null;
        }

        /** @var array<string, mixed> $spec */
        $spec['network'] = $post->getNetwork();
        if (! isset($spec['page']) || ! \is_string($spec['page']) || '' === $spec['page']) {
            $spec['page'] = $post->getPage();
        }

        return $spec;
    }

    /**
     * @return list<array{path: string, message: string}>
     */
    private function violations(Carousel $carousel): array
    {
        $errors = [];
        foreach ($this->validator->validate($carousel) as $violation) {
            $errors[] = ['path' => $violation->getPropertyPath(), 'message' => (string) $violation->getMessage()];
        }

        return $errors;
    }

    private function slugFilename(string $page, string $network): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/i', '-', $page.'-'.$network), '-') ?: 'carousel';
    }
}
