<?php

namespace Pushword\Version;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use InvalidArgumentException;
use Pushword\Core\Entity\SharedTrait\CustomPropertiesInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AutoconfigureTag('controller.service_arguments')]
class VersionController extends AbstractController
{
    /**
     * Per-type editable fields surfaced in the compare view. The first entry is
     * the large "main content" field rendered in the Monaco diff editor; the
     * rest are small single-line fields.
     *
     * @var array<string, array{editRoute: string, fields: array<string, string>}>
     */
    private const array TYPES = [
        'page' => [
            'editRoute' => 'admin_page_edit',
            'fields' => ['mainContent' => 'versionMainContent', 'h1' => 'H1', 'title' => 'versionTitle', 'slug' => 'Slug'],
        ],
        'snippet' => [
            'editRoute' => 'admin_snippet_edit',
            'fields' => ['content' => 'versionMainContent', 'name' => 'versionTitle', 'slug' => 'Slug'],
        ],
    ];

    private Versionner $versionner;

    private TranslatorInterface $translator;

    private AdminContextProviderInterface $adminContextProvider;

    #[Required]
    public function setVersionner(Versionner $versionner): void
    {
        $this->versionner = $versionner;
    }

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    #[Required]
    public function setAdminContextProvider(AdminContextProviderInterface $adminContextProvider): void
    {
        $this->adminContextProvider = $adminContextProvider;
    }

    #[AdminRoute(path: '/version/{type}/{id}/reset', name: 'version_reset')]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function resetVersioning(Request $request, string $type, int $id): RedirectResponse
    {
        $this->versionner->reset($type, $id);

        $this->getFlashBagFromRequest($request)->add('success', $this->translator->trans('versionResetHistory'));

        return $this->redirectToEdit($type, $id);
    }

    #[AdminRoute(path: '/version/{type}/{id}/list', name: 'version_list')]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function listVersion(string $type, string $id): Response
    {
        $entity = $this->versionner->find($type, $id);

        $versions = [];
        foreach ($this->versionner->getVersions($type, $id) as $version) {
            $versions[$version] = $this->versionner->populate($this->newEntity($type), $type, $id, $version);
        }

        return $this->renderAdmin('@PushwordVersion/list.html.twig', [
            'type' => $type,
            'entity' => $entity,
            'versions' => $versions,
            'editUrl' => $this->generateUrl(self::TYPES[$type]['editRoute'], ['entityId' => $id]),
        ]);
    }

    #[AdminRoute(path: '/version/{type}/{id}/compare/{versionLeft}/{versionRight}', name: 'version_compare')]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function compareVersion(string $type, string $id, string $versionLeft, string $versionRight = 'current'): Response
    {
        $entity = $this->versionner->find($type, $id);

        $left = $this->versionner->populate($this->newEntity($type), $type, $id, $versionLeft);

        if ('current' === $versionRight) {
            $right = $entity;
        } else {
            $right = $this->versionner->populate($this->newEntity($type), $type, $id, $versionRight);
        }

        return $this->renderAdmin('@PushwordVersion/compare.html.twig', [
            'type' => $type,
            'entity' => $entity,
            'leftEntity' => $left,
            'rightEntity' => $right,
            'fields' => self::TYPES[$type]['fields'],
            'versionLeft' => $versionLeft,
            'versionRight' => $versionRight,
            'allVersions' => $this->versionner->getVersions($type, $id),
        ]);
    }

    #[AdminRoute(path: '/version/{type}/{id}/save-compare', name: 'version_save_compare', options: ['methods' => ['POST']])]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function saveCompare(Request $request, string $type, int $id): RedirectResponse
    {
        $entity = $this->versionner->find($type, $id);
        $accessor = PropertyAccess::createPropertyAccessor();

        foreach (array_keys(self::TYPES[$type]['fields']) as $field) {
            $value = $request->request->get($field);
            if (\is_string($value)) {
                $accessor->setValue($entity, $field, $value);
            }
        }

        $yaml = $request->request->get('unmanagedPropertiesAsYaml');
        if ($entity instanceof CustomPropertiesInterface && \is_string($yaml)) {
            try {
                $entity->setUnmanagedPropertiesFromYaml($yaml, merge: true);
            } catch (ParseException|InvalidArgumentException) {
                $this->getFlashBagFromRequest($request)->add('warning', $this->translator->trans('versionCustomPropertiesInvalid'));
            }
        }

        $this->versionner->flush();

        $this->getFlashBagFromRequest($request)->add('success', $this->translator->trans('versionSaveSuccess'));

        return $this->redirectToEdit($type, $id);
    }

    /**
     * Returns one version's editable fields as JSON so the compare view can
     * scrub the slider and refresh the diff without a full page reload.
     */
    #[AdminRoute(path: '/version/{type}/{id}/data/{version}', name: 'version_data')]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function versionData(string $type, string $id, string $version): JsonResponse
    {
        $entity = 'current' === $version
            ? $this->versionner->find($type, $id)
            : $this->versionner->populate($this->newEntity($type), $type, $id, $version);

        $accessor = PropertyAccess::createPropertyAccessor();

        $data = [];
        foreach (array_keys(self::TYPES[$type]['fields']) as $field) {
            $value = $accessor->getValue($entity, $field);
            $data[$field] = \is_scalar($value) ? (string) $value : '';
        }

        if ($entity instanceof CustomPropertiesInterface) {
            $data['unmanagedPropertiesAsYaml'] = $entity->getUnmanagedPropertiesAsYaml();
        }

        return new JsonResponse($data);
    }

    #[AdminRoute(path: '/version/{type}/{id}/{version}', name: 'version_load')]
    #[IsGranted('ROLE_PUSHWORD_ADMIN')]
    public function loadVersion(string $type, string $id, string $version): RedirectResponse
    {
        $this->versionner->loadVersion($type, $id, $version);

        return $this->redirectToEdit($type, (int) $id);
    }

    private function newEntity(string $type): object
    {
        $class = Versionner::versionableTypes()[$type] ?? throw new Exception('Unknown version type `'.$type.'`');

        return new $class();
    }

    private function redirectToEdit(string $type, int $id): RedirectResponse
    {
        return $this->redirectToRoute(self::TYPES[$type]['editRoute'], ['entityId' => $id]);
    }

    private function getFlashBagFromRequest(Request $request): FlashBagInterface
    {
        $session = $request->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            return $session->getFlashBag();
        }

        throw new Exception();
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function renderAdmin(string $view, array $parameters = []): Response
    {
        $parameters['ea'] = $this->adminContextProvider->getContext();

        return $this->render($view, $parameters);
    }
}
