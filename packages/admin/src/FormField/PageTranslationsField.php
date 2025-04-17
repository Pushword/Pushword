<?php

namespace Pushword\Admin\FormField;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\QueryBuilder;
use Pushword\Core\Entity\Page;
use Pushword\Core\Repository\PageRepository;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * @extends AbstractField<Page>
 */
class PageTranslationsField extends AbstractField
{
    /**
     * @param FormMapper<Page> $form
     */
    public function formField(FormMapper $form): void
    {
        /** @var Page */
        $page = $this->admin->getSubject();

        $form->add('translations', ModelAutocompleteType::class, [
            'required' => false,
            'multiple' => true,
            'class' => $this->admin->getModelClass(),
            'property' => 'slug',
            'label' => 'admin.page.translations.label',
            'help_html' => true,
            'help' => 'admin.page.translations.help',
            'btn_add' => false,
            'callback' => fn (AdminInterface $admin, string $property, string $value) => $this->getCallback($admin, $property, $value),
            'req_params' => [
                'pageId' => $page->getId() ?? 0,
                'pageLocale' => $page->getLocale(),
            ],
            // 'query_builder' => fn (PageRepository $repo): QueryBuilder => $this->getQueryBuilder($repo, $page),
            'to_string_callback' => static fn (Page $entity): string => $entity->getLocale().' ('.$entity->getSlug().')',
        ]);

        $formBuilder = $form->getFormBuilder();
        $formBuilder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($page) {
            $form = $event->getForm();
            /** @var ArrayCollection<int, Page> $translations */
            $translations = $form->get('translations')->getData();

            /** @var \Symfony\Component\HttpFoundation\Session\Session */
            $session = $this->admin->getRequest()->getSession();

            if (! $translations->isEmpty() && '' !== $page->getLocale()) {
                $currentLocale = $page->getLocale();

                foreach ($translations as $translation) {
                    if ($translation->getLocale() === $currentLocale) {
                        // $form->get('translations')->addError(new FormError(
                        //     'Translation pages must have different locales than the current page.'
                        // ));
                        // break;
                        $translations->removeElement($translation);

                        $session->getFlashBag()->add(
                            'warning',
                            sprintf('The page "%s" was removed from translations because it has the same locale (%s) as the current page.', $translation->getSlug(), $currentLocale)
                        );
                    }
                }
            }
        });
    }

    /**
     * @param AdminInterface<Page> $admin
     */
    public function getCallback(AdminInterface $admin, string $property, string $value): void
    {
        $pageId = $admin->getRequest()->get('pageId');
        $pageLocale = $admin->getRequest()->get('pageLocale');

        $isSaved = '0' !== $pageId;

        $datagrid = $admin->getDatagrid();
        $query = $datagrid->getQuery();

        if ($isSaved) {
            /** @var QueryBuilder $query */
            $rootAlias = $query->getRootAliases()[0]; // @phpstan-ignore-line
            $query
                ->andWhere($rootAlias.'.id != :id AND '.$rootAlias.'.locale != :locale')
                ->setParameter('id', $pageId)
                ->setParameter('locale', $pageLocale);
        }

        // $datagrid->setValue($property, null, $value);
    }

    public function getQueryBuilder(PageRepository $repo, Page $page): QueryBuilder
    {
        // TODO change isSaved by a js function wich retrieve value from input[name$="[locale]"]
        $isSaved = null !== $page->getId();

        $qb = $repo->createQueryBuilder('p');

        if ($isSaved) {
            $qb
                ->andWhere('p.id != :id AND p.locale != :locale')
                ->setParameter('id', (int) $page->getId())
                ->setParameter('locale', $page->getLocale());
        }

        return $qb;
    }
}
