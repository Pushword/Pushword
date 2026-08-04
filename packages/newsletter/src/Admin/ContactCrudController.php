<?php

namespace Pushword\Newsletter\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Override;
use Pushword\Newsletter\Entity\Audience;
use Pushword\Newsletter\Entity\Contact;
use Pushword\Newsletter\Enum\ContactStatus;
use Pushword\Newsletter\Repository\AudienceRepository;
use Pushword\Newsletter\Repository\ContactRepository;
use Pushword\Newsletter\Service\ContactManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * @extends AbstractCrudController<Contact>
 */
#[AdminRoute(path: '/newsletter/contact', name: 'newsletter_contact')]
class ContactCrudController extends AbstractCrudController
{
    private const string OPT_IN_CSRF_ID = 'newsletter_contact_opt_in';

    public function __construct(
        private readonly ContactManager $contactManager,
        private readonly ContactRepository $contactRepository,
        private readonly AudienceRepository $audienceRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Contact::class;
    }

    #[Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('newsletter.contact.label.singular')
            ->setEntityLabelInPlural('newsletter.contact.label.plural')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['email', 'phone', 'name', 'tags'])
            ->overrideTemplates([
                'crud/edit' => '@PushwordNewsletter/admin/contact_edit.html.twig',
                'crud/detail' => '@PushwordNewsletter/admin/contact_detail.html.twig',
            ]);
    }

    #[Override]
    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('audience')
            ->add('status')
            ->add('locale')
            ->add('createdAt');
    }

    /**
     * A contact is never written by hand: `new` would produce a row with no
     * recorded opt-in, which is a row that cannot be defended. Every action here
     * goes through {@see ContactManager} instead, so the consent ledger, the
     * campaign counters and the running automations stay in agreement.
     */
    #[Override]
    public function configureActions(Actions $actions): Actions
    {
        $optIn = Action::new('optIn', 'newsletter.contact.action.optIn', 'fa fa-user-plus')
            ->linkToCrudAction('optIn')
            ->createAsGlobalAction()
            ->setCssClass('btn btn-primary');

        // The audience select on the form *moves* a subscription; adding one is
        // a second row, which is what this opens — with the address prefilled.
        $optInAnother = Action::new('optInAnother', 'newsletter.contact.action.optInAnother', 'fa fa-user-plus')
            ->linkToUrl(fn (Contact $contact): string => $this->optInUrl($contact->email ?? '', $contact->phone ?? ''))
            ->setCssClass('btn btn-outline-primary');

        $confirm = Action::new('confirm', 'newsletter.contact.action.confirm', 'fa fa-check')
            ->linkToCrudAction('confirm')
            ->displayIf(static fn (Contact $contact): bool => $contact->isPending())
            ->setCssClass('btn btn-outline-success');

        $unsubscribe = Action::new('unsubscribe', 'newsletter.contact.action.unsubscribe', 'fa fa-user-slash')
            ->linkToCrudAction('unsubscribe')
            ->displayIf(static fn (Contact $contact): bool => null === $contact->unsubscribedAt && null === $contact->bouncedAt)
            ->setCssClass('btn btn-outline-warning');

        $resubscribe = Action::new('resubscribe', 'newsletter.contact.action.resubscribe', 'fa fa-rotate-left')
            ->linkToCrudAction('resubscribe')
            ->displayIf(static fn (Contact $contact): bool => null !== $contact->unsubscribedAt && null === $contact->bouncedAt)
            ->setCssClass('btn btn-outline-success');

        $actions = $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $optIn);

        foreach ([Crud::PAGE_DETAIL, Crud::PAGE_EDIT] as $page) {
            $actions = $actions
                ->add($page, $optInAnother)
                ->add($page, $confirm)
                ->add($page, $unsubscribe)
                ->add($page, $resubscribe);
        }

        return $actions;
    }

    #[Override]
    public function configureFields(string $pageName): iterable
    {
        yield FormField::addColumn('col-12 col-lg-8 mainFields');
        yield FormField::addFieldset('newsletter.contact.fieldset.identity');
        yield EmailField::new('email', 'newsletter.contact.field.email')->setRequired(false);
        yield TelephoneField::new('phone', 'newsletter.contact.field.phone')
            ->setRequired(false)
            ->setHelp('newsletter.contact.field.phone.help');
        yield TextField::new('name', 'newsletter.contact.field.name');
        yield ChoiceField::new('status', 'newsletter.contact.field.status')
            ->renderAsBadges([
                ContactStatus::Pending->value => 'warning',
                ContactStatus::Subscribed->value => 'success',
                ContactStatus::Unsubscribed->value => 'secondary',
                ContactStatus::Bounced->value => 'danger',
            ])
            ->hideOnForm();
        yield TextField::new('tags', 'newsletter.contact.field.tags')->setRequired(false);
        yield TextareaField::new('unmanagedPropertiesAsYaml', 'newsletter.contact.field.customProperties')
            ->hideOnIndex()
            ->setHelp('newsletter.contact.field.customProperties.help');

        yield FormField::addColumn('col-12 col-lg-4 columnFields');
        yield FormField::addFieldset('newsletter.contact.fieldset.consent');
        yield AssociationField::new('audience', 'newsletter.contact.field.audience')
            ->setHelp('newsletter.contact.field.audience.help');
        yield TextField::new('locale', 'newsletter.contact.field.locale');
        yield TextField::new('source', 'newsletter.contact.field.source')->hideOnIndex()->setDisabled();
        yield TextField::new('optinHost', 'newsletter.contact.field.optinHost')->hideOnIndex()->setDisabled();
        yield TextField::new('optinIp', 'newsletter.contact.field.optinIp')->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'newsletter.contact.field.registeredAt')->hideOnForm();
        yield DateTimeField::new('confirmedAt', 'newsletter.contact.field.confirmedAt')->onlyOnDetail();
        yield DateTimeField::new('unsubscribedAt', 'newsletter.contact.field.unsubscribedAt')->onlyOnDetail();
        yield DateTimeField::new('bouncedAt', 'newsletter.contact.field.bouncedAt')->onlyOnDetail();
    }

    /** Every list this address is on, so the edit page shows the person and not only one of their subscriptions. */
    #[Override]
    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        $contact = $this->getContext()?->getEntity()->getInstance();

        if ($contact instanceof Contact) {
            $responseParameters->set('subscriptions', $this->subscriptionsOf($contact));
        }

        return $responseParameters;
    }

    /**
     * Open a subscription by hand — someone who consented elsewhere (a form on
     * paper, a reply to a mail) or an address already on one list to be added to
     * another. It is the same code path as the public form, so the audience's
     * double opt-in rule still decides whether a confirmation mail goes out.
     */
    #[AdminRoute(path: '/opt-in', name: 'opt_in', options: ['methods' => ['GET', 'POST']])]
    public function optIn(Request $request): Response
    {
        $audiences = $this->audienceRepository->findAllOrdered();

        if ([] === $audiences) {
            $this->addFlash('warning', 'newsletter.contact.flash.noAudience');

            return new RedirectResponse($this->indexUrl());
        }

        if ($request->isMethod(Request::METHOD_POST)) {
            return $this->doOptIn($request);
        }

        return $this->render('@PushwordNewsletter/admin/contact_opt_in.html.twig', [
            'audiences' => $audiences,
            'email' => $request->query->getString('email'),
            'phone' => $request->query->getString('phone'),
            'back_url' => $this->indexUrl(),
            'csrf_id' => self::OPT_IN_CSRF_ID,
        ]);
    }

    /** Turn a pending opt-in into a subscription without waiting for the click — the consent was given elsewhere. */
    #[AdminRoute(path: '/{entityId}/confirm', name: 'confirm')]
    public function confirm(): RedirectResponse
    {
        $contact = $this->contact();

        if ($contact instanceof Contact) {
            $this->contactManager->confirm($contact);
            $this->addFlash('success', 'newsletter.contact.flash.confirmed');
        }

        return new RedirectResponse($this->editUrl($contact));
    }

    #[AdminRoute(path: '/{entityId}/unsubscribe', name: 'unsubscribe')]
    public function unsubscribe(): RedirectResponse
    {
        $contact = $this->contact();

        if ($contact instanceof Contact) {
            $this->contactManager->unsubscribe($contact);
            $this->addFlash('success', 'newsletter.contact.flash.unsubscribed');
        }

        return new RedirectResponse($this->editUrl($contact));
    }

    #[AdminRoute(path: '/{entityId}/resubscribe', name: 'resubscribe')]
    public function resubscribe(): RedirectResponse
    {
        $contact = $this->contact();

        if ($contact instanceof Contact) {
            $this->contactManager->resubscribe($contact);
            $this->addFlash('success', 'newsletter.contact.flash.resubscribed');
        }

        return new RedirectResponse($this->editUrl($contact));
    }

    private function doOptIn(Request $request): RedirectResponse
    {
        if (! $this->isCsrfTokenValid(self::OPT_IN_CSRF_ID, (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'newsletter.contact.flash.invalidToken');

            return new RedirectResponse($this->indexUrl());
        }

        $audience = $this->audienceRepository->find($request->request->getInt('audience'));
        $email = trim($request->request->getString('email'));
        $phone = trim($request->request->getString('phone'));

        // A number alone is enough: the person consented over the phone, and
        // there is nothing to confirm by mail. An address that was typed and is
        // not one is a typo either way, and never a phone-only opt-in.
        $hasEmail = '' !== $email && false !== filter_var($email, \FILTER_VALIDATE_EMAIL);
        $hasPhone = null !== Contact::normalizePhone($phone);

        $usable = $audience instanceof Audience
            && ($hasEmail || $hasPhone)
            && ('' === $email || $hasEmail);

        if (! $usable) {
            $this->addFlash('danger', 'newsletter.contact.flash.invalidOptIn');

            return new RedirectResponse($this->optInUrl($email, $phone));
        }

        // Who added the row is the evidence a hand-made opt-in owes: "admin" alone
        // says a human did it, not which one.
        $source = mb_substr('admin:'.($this->getUser()?->getUserIdentifier() ?? ''), 0, 120);

        try {
            $contact = $this->contactManager->subscribe(
                $audience,
                $hasEmail ? $email : null,
                name: $request->request->getString('name'),
                source: $source,
                // Consent given elsewhere skips the confirmation mail; anything
                // else leaves the audience's own rule to decide.
                requireDoubleOptIn: $request->request->getBoolean('alreadyConsented') ? false : null,
                phone: $hasPhone ? $phone : null,
            );
        } catch (Throwable $throwable) {
            // A confirmation mail the transport refuses is the usual one, and it
            // leaves nothing behind: ContactManager sends before it flushes.
            $this->addFlash('danger', $throwable->getMessage());

            return new RedirectResponse($this->optInUrl($email, $phone));
        }

        $this->addFlash('success', $contact->isPending()
            ? 'newsletter.contact.flash.confirmationSent'
            : 'newsletter.contact.flash.subscribed');

        return new RedirectResponse($this->editUrl($contact));
    }

    /**
     * @return list<array{contact: Contact, url: string, current: bool}>
     */
    private function subscriptionsOf(Contact $contact): array
    {
        return array_map(fn (Contact $subscription): array => [
            'contact' => $subscription,
            'url' => $this->editUrl($subscription),
            'current' => $subscription->id === $contact->id,
        ], $this->contactRepository->findAllByEmail($contact->email));
    }

    private function contact(): ?Contact
    {
        $contact = $this->getContext()?->getEntity()->getInstance();

        if (! $contact instanceof Contact) {
            $this->addFlash('danger', 'newsletter.contact.flash.notFound');

            return null;
        }

        return $contact;
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->unset('email')
            ->unset('phone')
            ->generateUrl();
    }

    private function optInUrl(string $email, string $phone = ''): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction('optIn')
            ->unset(EA::ENTITY_ID)
            ->set('email', $email)
            ->set('phone', $phone)
            ->generateUrl();
    }

    private function editUrl(?Contact $contact): string
    {
        if (! $contact instanceof Contact) {
            return $this->indexUrl();
        }

        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::EDIT)
            ->setEntityId($contact->id)
            ->unset('email')
            ->unset('phone')
            ->generateUrl();
    }
}
