<?php

declare(strict_types=1);

namespace Pushword\Newsletter\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Override;
use Pushword\Core\Site\SiteRegistry;
use Pushword\Newsletter\Admin\Form\EmailDesignType;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Service\MailRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Audience>
 */
#[AdminRoute(path: '/newsletter/audience', name: 'newsletter_audience')]
class AudienceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly SiteRegistry $siteRegistry,
        private readonly ContactRepository $contactRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Audience::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('layout', '@PushwordNewsletter/admin/layout.html.twig')
            ->overrideTemplate('crud/index', '@PushwordNewsletter/admin/audience_index.html.twig')
            ->setEntityLabelInSingular('newsletter.audience.label.singular')
            ->setEntityLabelInPlural('newsletter.audience.label.plural')
            ->setDefaultSort(['slug' => 'ASC'])
            ->setFormOptions(['attr' => ['class' => 'pw-compact-form pw-compact-form--single']])
            ->setSearchFields(['slug', 'name', 'fromEmail']);
    }

    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $design = Action::new('emailDesign', 'newsletter.design.action', 'fa fa-palette')
            ->linkToCrudAction('emailDesign')
            ->setCssClass('btn btn-outline-primary');

        foreach ([Crud::PAGE_INDEX, Crud::PAGE_EDIT, Crud::PAGE_DETAIL] as $page) {
            $actions->add($page, $design);
        }

        return $actions->disable(Action::SAVE_AND_ADD_ANOTHER);
    }

    #[AdminRoute(path: '/{entityId}/email-design', name: 'email_design', options: ['methods' => ['GET', 'POST']])]
    #[IsGranted('ROLE_EDITOR')]
    public function emailDesign(Request $request, EntityManagerInterface $entityManager, MailRenderer $renderer, TranslatorInterface $translator): Response
    {
        $audience = $this->getContext()?->getEntity()->getInstance();
        if (! $audience instanceof Audience) {
            throw $this->createNotFoundException();
        }

        // Preview binds to a copy: it must never change the managed audience.
        $design = clone $audience;
        $form = $this->createForm(EmailDesignType::class, $design);
        $form->handleRequest($request);

        $preview = $request->request->getBoolean('preview');

        if ($form->isSubmitted() && $form->isValid() && ! $preview) {
            $audience->emailLayout = $design->emailLayout;
            $audience->headerMarkdown = $design->headerMarkdown;
            $audience->footerMarkdown = $design->footerMarkdown;
            $audience->signatureMarkdown = $design->signatureMarkdown;
            $entityManager->flush();
            $this->addFlash('success', 'newsletter.design.saved');

            return $this->redirect($request->getPathInfo());
        }

        if ($preview && (! $form->isSubmitted() || ! $form->isValid())) {
            return new JsonResponse(['error' => $translator->trans('newsletter.design.failed')], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $body = $form->get('sampleBody')->getData();
        $contact = new Contact($design, 'preview@example.tld');
        $contact->name = 'Alex';

        $site = $this->siteRegistry->get($design->mainHost);
        $html = $renderer->html(
            $design,
            $contact,
            $design->name,
            \is_string($body) ? $body : '',
            null,
            $design->transactional ? null : $site->baseUrl.'/#unsubscribe',
            null,
            locale: $site->locale,
        );

        if ($preview) {
            $response = new JsonResponse(['html' => $html]);
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        }

        return $this->render('@PushwordNewsletter/admin/email_design.html.twig', [
            'audience' => $audience,
            'site_name' => $site->name,
            'default_header' => Audience::DEFAULT_EMAIL_HEADER,
            'default_footer' => Audience::DEFAULT_EMAIL_FOOTER,
            'design_form' => $form->createView(),
            'preview_html' => $html,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        $hosts = $this->siteRegistry->getHosts();

        yield FormField::addFieldset('newsletter.audience.fieldset.identity')->setIcon('fa fa-users');
        yield TextField::new('name', 'newsletter.audience.field.name')->setMaxLength(255);
        if (Crud::PAGE_INDEX === $pageName) {
            $contactCounts = $this->contactRepository->countByAudience();
            yield IntegerField::new('contactCount', 'newsletter.audience.field.contactCount')
                ->onlyOnIndex()
                ->setValue(0)
                ->setSortable(false)
                ->setTextAlign('right')
                ->setTemplatePath('@PushwordNewsletter/admin/field/audience_contact_count.html.twig')
                ->formatValue(static fn (mixed $value, Audience $audience): int => $contactCounts[$audience->id ?? 0] ?? 0);
        }

        yield TextField::new('slug', 'newsletter.audience.field.slug')
            ->setMaxLength(100)
            ->setHelp('newsletter.audience.field.slug.help')
            ->setFormTypeOption('context_help', true);
        yield ChoiceField::new('mainHost', 'newsletter.audience.field.mainHost')
            ->setChoices(array_combine($hosts, $hosts))
            ->renderAsNativeWidget()
            ->setHelp('newsletter.audience.field.mainHost.help')
            ->setFormTypeOption('context_help', true);

        yield FormField::addFieldset('newsletter.audience.fieldset.sender')->setIcon('fa fa-paper-plane');
        yield TextField::new('fromName', 'newsletter.audience.field.fromName')->hideOnIndex();
        yield EmailField::new('fromEmail', 'newsletter.audience.field.fromEmail');
        yield EmailField::new('replyTo', 'newsletter.audience.field.replyTo')->hideOnIndex();
        yield TextareaField::new('postalAddress', 'newsletter.audience.field.postalAddress')->hideOnIndex()
            ->setRequired(false)
            ->setNumOfRows(3)
            ->setHelp('newsletter.audience.field.postalAddress.help')
            ->setFormTypeOption('context_help', true);

        yield FormField::addFieldset('newsletter.audience.fieldset.rules')->setIcon('fa fa-sliders');
        yield BooleanField::new('requireDoubleOptIn', 'newsletter.audience.field.doubleOptIn')
            ->setHelp('newsletter.audience.field.doubleOptIn.help')
            ->setFormTypeOption('context_help', 'newsletterHelpDoubleOptIn');
        yield ArrayField::new('interests', 'newsletter.audience.field.interests')->hideOnIndex()
            ->setHelp('newsletter.audience.field.interests.help')
            ->setFormTypeOption('context_help', true);

        yield FormField::addFieldset('newsletter.audience.fieldset.advanced')->setIcon('fa fa-gear')
            ->collapsible()->renderCollapsed();
        yield BooleanField::new('clickTracking', 'newsletter.audience.field.clickTracking')
            ->hideOnIndex()
            ->setHelp('newsletter.audience.field.clickTracking.help')
            ->setFormTypeOption('context_help', 'newsletterHelpClickTracking');
        yield BooleanField::new('transactional', 'newsletter.audience.field.transactional')
            ->hideOnIndex()
            ->setHelp('newsletter.audience.field.transactional.help')
            ->setFormTypeOption('context_help', 'newsletterHelpTransactional');
        yield IntegerField::new('rateSeconds', 'newsletter.audience.field.rateSeconds')->hideOnIndex()
            ->setHelp('newsletter.audience.field.rateSeconds.help')
            ->setFormTypeOption('context_help', true);
        yield TextField::new('utmSource', 'newsletter.audience.field.utmSource')->hideOnIndex()
            ->setRequired(false)
            ->setHelp('newsletter.audience.field.utmSource.help')
            ->setFormTypeOption('context_help', 'newsletterHelpUtmSource');
    }
}
