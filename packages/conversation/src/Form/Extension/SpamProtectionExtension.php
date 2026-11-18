<?php

declare(strict_types=1);

namespace Pushword\Conversation\Form\Extension;

use DateInterval;
use Override;
use Pushword\Conversation\Controller\ConversationFormController;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Service\NewMessageMailNotifier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Guards every message form the public conversation controller serves, whichever class
 * builds it: forms from initForm() and forms a site builds on FormType alike. It knows
 * the request by its controller, not its route: a template embedding the form with
 * render(controller()) runs it in a sub-request that has no route.
 *
 * - A hidden honeypot field. Its constraint travels with it, so a site field that
 *   takes the same name replaces the trap instead of rejecting real visitors.
 * - A signed, expiring form action. It proves the form was fetched from this endpoint
 *   for this host, type, referring page, locale, workflow and step. It needs no session,
 *   so it survives a static page or another domain embedding the form. It is an
 *   anti-automation check, not CSRF protection: anyone may fetch a form and replay its
 *   action until it expires. Cross-site posts are refused by ConversationCorsListener.
 * - A quota on accepted messages per IP, shared by every host and form, spent when a
 *   valid submission first gives a message an address the team is notified at.
 */
final class SpamProtectionExtension extends AbstractTypeExtension
{
    public const string HONEYPOT = 'homepage';

    private const string CONTROLLER = ConversationFormController::class.'::show';

    /** A form left open in a tab still submits; a scraped action is not a lasting key. */
    private const string ACTION_LIFETIME = 'P1D';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UriSigner $uriSigner,
        #[Autowire(service: 'limiter.conversation_submit')]
        private readonly RateLimiterFactoryInterface $limiter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Override]
    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    #[Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $request = $this->publicRequest($options);
        if (! $request instanceof Request) {
            return;
        }

        $builder->add(self::HONEYPOT, TextType::class, [
            'mapped' => false,
            'required' => false,
            'label' => false,
            'row_attr' => ['hidden' => true],
            'attr' => ['autocomplete' => 'off', 'tabindex' => '-1'],
            'constraints' => [new Blank(message: 'conversationSubmissionRejected')],
            'invalid_message' => 'conversationSubmissionRejected',
            'error_bubbling' => true,
        ]);

        $wasNotifiable = false;
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use (&$wasNotifiable): void {
            $message = $event->getForm()->getData();
            $wasNotifiable = $message instanceof Message && NewMessageMailNotifier::hasReplyAddress($message);
        });

        // After validation: a rejected form spends nothing.
        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) use ($request, &$wasNotifiable): void {
            $this->guardSubmission($event->getForm(), $request, $wasNotifiable);
        }, -2048);
    }

    #[Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $action = $view->vars['action'] ?? '';
        if ($view->parent instanceof FormView || ! \is_string($action) || '' === $action) {
            return;
        }

        if ($this->publicRequest($options) instanceof Request) {
            $view->vars['action'] = $this->uriSigner->sign($action, new DateInterval(self::ACTION_LIFETIME));
        }
    }

    /** @param FormInterface<mixed> $form */
    private function guardSubmission(FormInterface $form, Request $request, bool $wasNotifiable): void
    {
        if (! $this->uriSigner->checkRequest($request)) {
            $form->addError(new FormError($this->translator->trans('conversationSubmissionRejected', domain: 'validators')));

            return;
        }

        $message = $form->getData();
        if ($wasNotifiable || ! $message instanceof Message || ! NewMessageMailNotifier::hasReplyAddress($message) || ! $form->isValid()) {
            return;
        }

        // Spent before the notification is sent or queued, and never refunded: the quota
        // counts accepted messages, not delivered emails.
        if (! $this->limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
            $form->addError(new FormError($this->translator->trans('conversationSubmissionRateLimited', domain: 'validators')));
        }
    }

    /** @param array<string, mixed> $options */
    private function publicRequest(array $options): ?Request
    {
        $request = $this->requestStack->getCurrentRequest();
        $dataClass = $options['data_class'] ?? null;

        if (! $request instanceof Request || self::CONTROLLER !== $request->attributes->get('_controller')
            || ! \is_string($dataClass) || ! is_a($dataClass, Message::class, true)) {
            return null;
        }

        return $request;
    }
}
