<?php

namespace Pushword\TemplateEditor;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Exception;
use LogicException;
use Pushword\Core\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
#[AutoconfigureTag('controller.service_arguments')]
final class ElementAdmin extends AbstractController
{
    private KernelInterface $kernel;

    private Twig $twig;

    private AdminContextProviderInterface $adminContextProvider;

    /** @param string[] $canBeEditedList */
    public function __construct(
        public array $canBeEditedList, // template_editor_can_be_edited
        public bool $disableCreation,  // template_editor_disable_creation
        Security $security,
        private readonly TranslatorInterface $translator,
    ) {
        $user = $security->getUser();
        if ($user instanceof User && $user->hasRole('ROLE_SUPER_ADMIN')) {
            $this->canBeEditedList = [];
            $this->disableCreation = false;
        }
    }

    #[Required]
    public function setTwig(Twig $twig): void
    {
        $this->twig = $twig;
    }

    #[Required]
    public function setKernel(KernelInterface $kernel): void
    {
        $this->kernel = $kernel;
    }

    #[Required]
    public function setAdminContextProvider(AdminContextProviderInterface $adminContextProvider): void
    {
        $this->adminContextProvider = $adminContextProvider;
    }

    private function getElements(): ElementRepository
    {
        return new ElementRepository($this->kernel->getProjectDir().'/templates', $this->canBeEditedList, $this->disableCreation);
    }

    #[AdminRoute(path: '/template/list', name: 'template_editor_list')]
    public function listElement(): Response
    {
        return $this->renderAdmin('@pwTemplateEditor/list.html.twig', [
            'elements' => $this->getElements()->getAll(),
            'canCreate' => ! $this->disableCreation,
        ]);
    }

    private function getElement(?string $encodedPath): Element
    {
        if (null !== $encodedPath) {
            $element = $this->getElements()->getOneByEncodedPath($encodedPath);
            if (! $element instanceof Element) {
                throw $this->createNotFoundException('`'.$encodedPath.'` element does not exist...');
            }

            return $element;
        }

        return $this->disableCreation ? throw new Exception('creation is disabled') : new Element($this->kernel->getProjectDir().'/templates');
    }

    private function clearTwigCache(): void
    {
        $twigCacheFolder = $this->twig->getCache(true);

        $process = new Process(['rm', '-rf', $twigCacheFolder]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    #[AdminRoute(path: '/template/create', name: 'template_editor_create')]
    #[Route(path: '/template/create', name: 'pushword_template_editor_create', methods: ['GET', 'POST'], priority: 1)]
    #[AdminRoute(path: '/template/edit/{encodedPath}', name: 'template_editor_edit')]
    #[Route(path: '/template/edit/{encodedPath}', name: 'pushword_template_editor_edit', methods: ['GET', 'POST'])]
    public function editElement(?string $encodedPath = null, ?Request $request = null): Response
    {
        $element = $this->getElement($encodedPath);

        $form = $this->editElementForm($element);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $element = $form->getData();

            $element->storeElement();

            $this->clearTwigCache();

            return $this->redirectToRoute('admin_template_editor_edit', [
                'encodedPath' => $element->getEncodedPath(),
            ]);
        }

        return $this->renderAdmin(
            '@pwTemplateEditor/edit.html.twig',
            [
                'element' => $element,
                'form' => $form->createView(),
                'disableCreation' => $this->disableCreation,
            ]
        );
    }

    /**
     * @return FormInterface<Element>
     */
    private function editElementForm(Element $element): FormInterface
    {
        /** @var FormInterface<Element> */
        $form = $this->createFormBuilder($element)
            ->add('path', TextType::class, ['disabled' => $this->disableCreation])
            ->add('code', TextareaType::class, [
                'attr' => [
                    'style' => 'min-height: 90vh;font-size:125%;',
                    'data-editor' => 'twig',
                    'data-gutter' => 0,
                ],
                'required' => false,
            ])

            ->getForm();

        return $form;
    }

    #[AdminRoute(path: '/delete/{encodedPath}', name: 'template_editor_delete')]
    #[Route(path: '/delete/{encodedPath}', name: 'pushword_template_editor_delete', methods: ['GET', 'POST'])]
    public function deleteElement(string $encodedPath, Request $request): Response
    {
        if ($this->disableCreation) {
            $this->addFlash('warning', $this->translator->trans('template_editor.creation_disabled'));

            return $this->redirectToRoute('admin_template_editor_list');
        }

        $element = $this->getElement($encodedPath);

        $form = $this->createFormBuilder()
            ->add('delete', SubmitType::class, ['label' => $this->translator->trans('template_editor.delete.label'), 'attr' => ['class' => 'btn-danger']])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $element->deleteElement();

            $this->addFlash('error', $this->translator->trans('template_editor.element.deleted'));

            return $this->redirectToRoute('admin_template_editor_list');
        }

        return $this->renderAdmin(
            '@pwTemplateEditor/delete.html.twig',
            [
                'form' => $form->createView(),
                'element' => $element,
                'disableCreation' => $this->disableCreation,
            ]
        );
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function renderAdmin(string $view, array $parameters = []): Response
    {
        $context = $this->adminContextProvider->getContext();
        if (null === $context) {
            throw new LogicException('EasyAdmin context is not available. Please use the admin routes (admin_template_editor_*) to access this page.');
        }

        $parameters['ea'] = $context;

        return $this->render($view, $parameters);
    }
}
