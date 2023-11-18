<?php

namespace Pushword\TemplateEditor;

use Pushword\Core\Entity\UserInterface;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment as Twig;

#[IsGranted('ROLE_PUSHWORD_ADMIN')]
#[AutoconfigureTag('controller.service_arguments')]
final class ElementAdmin extends AbstractController
{
    private KernelInterface $kernel;

    private twig $twig;

    /** @param string[] $canBeEditedList */
    public function __construct(
        public array $canBeEditedList, // template_editor_can_be_edited
        public bool $disableCreation,  // template_editor_disable_creation
        Security $security,
    ) {
        /** @var ?UserInterface */
        $user = $security->getUser();
        if (true === $user?->hasRole('ROLE_SUPER_ADMIN')) {
            $this->canBeEditedList = [];
            $this->disableCreation = false;
        }
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setTwig(Twig $twig): void
    {
        $this->twig = $twig;
    }

    #[\Symfony\Contracts\Service\Attribute\Required]
    public function setKernel(KernelInterface $kernel): void
    {
        $this->kernel = $kernel;
    }

    private function getElements(): ElementRepository
    {
        return new ElementRepository($this->kernel->getProjectDir().'/templates', $this->canBeEditedList, $this->disableCreation);
    }

    public function listElement(): Response
    {
        return $this->render('@pwTemplateEditor/list.html.twig', [
            'elements' => $this->getElements()->getAll(),
            'canCreate' => ! $this->disableCreation,
        ]);
    }

    private function getElement(?string $encodedPath): Element
    {
        if (null !== $encodedPath) {
            $element = $this->getElements()->getOneByEncodedPath($encodedPath);
            if (! $element instanceof \Pushword\TemplateEditor\Element) {
                throw $this->createNotFoundException('`'.$encodedPath.'` element does not exist...');
            }
        }

        return $element ??
            ($this->disableCreation ? throw new \Exception('creation is disabled') : new Element($this->kernel->getProjectDir().'/templates'));
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

    public function editElement(string $encodedPath = null, Request $request = null): Response
    {
        $element = $this->getElement($encodedPath);

        $form = $this->editElementForm($element);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $element = $form->getData();

            if (! $element instanceof Element) {
                throw new \Exception('an error occured');
            }

            $element->storeElement();

            $this->clearTwigCache();

            return $this->redirectToRoute(
                'pushword_template_editor_edit',
                [
                    'encodedPath' => $element->getEncodedPath(),
                ]
            );
        }

        return $this->render(
            '@pwTemplateEditor/edit.html.twig',
            [
                'element' => $element,
                'form' => $form->createView(),
                'disableCreation' => $this->disableCreation,
            ]
        );
    }

    private function editElementForm(Element $element): FormInterface
    {
        return $this->createFormBuilder($element)
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
    }

    public function deleteElement(string $encodedPath, Request $request): Response
    {
        if ($this->disableCreation) {
            $this->addFlash('warning', 'Creation (so deletion) are disabled');
            $this->redirectToRoute('pushword_template_editor_list');
        }

        $element = $this->getElement($encodedPath);

        $form = $this->createFormBuilder()
            ->add('delete', SubmitType::class, ['label' => 'Supprimer', 'attr' => ['class' => 'btn-danger']])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $element->deleteElement();

            $this->addFlash('error', 'Element supprimé.');

            return $this->redirectToRoute('pushword_template_editor_list');
        }

        return $this->render(
            '@pwTemplateEditor/delete.html.twig',
            [
                'form' => $form->createView(),
                'element' => $element,
                'disableCreation' => $this->disableCreation,
            ]
        );
    }
}
