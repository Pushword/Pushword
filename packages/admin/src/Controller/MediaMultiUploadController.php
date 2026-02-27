<?php

declare(strict_types=1);

namespace Pushword\Admin\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Pushword\Core\Entity\Media;
use Pushword\Core\Image\ImageCacheManager;
use Pushword\Core\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;
use Throwable;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
final class MediaMultiUploadController extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'media_multi_upload';

    private AdminContextProviderInterface $adminContextProvider;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ImageCacheManager $imageCacheManager,
        private readonly MediaRepository $mediaRepo,
    ) {
    }

    #[Required]
    public function setAdminContextProvider(AdminContextProviderInterface $adminContextProvider): void
    {
        $this->adminContextProvider = $adminContextProvider;
    }

    #[AdminRoute(path: '/multi-upload', name: 'media_multi_upload')]
    public function index(): Response
    {
        return $this->render('@pwAdmin/media/multi_upload.html.twig', [
            'ea' => $this->adminContextProvider->getContext(),
            'all_tags' => $this->mediaRepo->getAllTags(),
        ]);
    }

    #[AdminRoute(path: '/multi-upload/upload', name: 'media_multi_upload_action', options: ['methods' => ['POST']])]
    public function upload(Request $request): JsonResponse
    {
        $token = (string) $request->request->get('_token');
        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $file = $request->files->get('file');
        if (! $file instanceof UploadedFile) {
            return new JsonResponse(['error' => 'No file received.'], Response::HTTP_BAD_REQUEST);
        }

        if (! $file->isValid()) {
            return new JsonResponse(['error' => $file->getErrorMessage()], Response::HTTP_BAD_REQUEST);
        }

        $hash = sha1_file($file->getPathname(), true);
        if (false !== $hash) {
            $existing = $this->mediaRepo->findOneBy(['hash' => $hash]);
            if ($existing instanceof Media) {
                return new JsonResponse(['skipped' => true, 'fileName' => $existing->getFileName()]);
            }
        }

        $media = new Media();
        $media->setMediaFile($file);

        $this->em->persist($media);
        $this->em->flush();

        $thumbnailUrl = '';
        if ($media->isImage()) {
            try {
                $thumbnailUrl = $this->imageCacheManager->getBrowserPath($media, 'md', checkFileExists: true);
            } catch (Throwable) {
            }
        }

        $dimensions = $media->getDimensions();

        return new JsonResponse([
            'id' => $media->id,
            'fileName' => $media->getFileName(),
            'alt' => $media->getAlt(),
            'tags' => $media->getTags(),
            'alts' => $media->getAlts() ?? '',
            'thumbnailUrl' => $thumbnailUrl,
            'width' => $dimensions?->width,
            'height' => $dimensions?->height,
            'size' => $media->getSize(),
            'mimeType' => $media->getMimeType(),
        ]);
    }

    #[AdminRoute(path: '/media/{id}/inline-update', name: 'media_multi_inline_update', options: ['methods' => ['POST']])]
    public function inlineUpdate(Request $request, Media $media): JsonResponse
    {
        $token = (string) $request->request->get('_token');
        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $field = trim((string) $request->request->get('field', ''));
        $value = (string) $request->request->get('value', '');

        $updated = match ($field) {
            'alt' => (bool) $media->setAlt($value),
            'tags' => (bool) $media->setTags($value),
            'alts' => (bool) $media->setAlts($value),
            'slug' => (bool) $media->setSlugForce($value),
            default => false,
        };

        if (! $updated) {
            return new JsonResponse(['error' => 'Field not editable.'], Response::HTTP_BAD_REQUEST);
        }

        $this->em->flush();

        $response = ['success' => true];
        if ('slug' === $field) {
            $response['slug'] = $media->getSlug();
            $response['fileName'] = $media->getFileName();
        }

        return new JsonResponse($response);
    }

    #[AdminRoute(path: '/media/{id}/multi-delete', name: 'media_multi_delete', options: ['methods' => ['POST']])]
    public function delete(Request $request, Media $media): JsonResponse
    {
        $token = (string) $request->request->get('_token');
        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($media);
        $this->em->flush();

        return new JsonResponse(['success' => true]);
    }
}
