<?php

namespace Pushword\Conversation\Form;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Exception;
use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Repository\MessageRepository;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

abstract class AbstractConversationForm implements ConversationFormInterface
{
    /**
     * Permit to convert integer step to text string for a better readability.
     *
     * @var array<int, string>
     */
    protected static $step = [
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
    ];

    /** @var string */
    protected $successMessage = 'conversationSendSuccess';

    protected ?int $currentStep = null;

    protected ?int $messageId = null;

    protected Message $message;

    protected AppConfig $app;

    public function __construct(
        protected Request $request,
        protected Registry $doctrine,
        protected TokenStorageInterface $security,
        protected FormFactory $formFactory,
        protected Twig $twig,
        protected Router $router,
        protected TranslatorInterface $translator,
        protected AppPool $apps,
        protected MessageRepository $messageRepo,
    ) {
        $this->app = $this->apps->get();
    }

    /**
     * Initiate Message Entity (or load data from previous message)
     * and return form builder instance.
     *
     * @return FormBuilderInterface<Message>
     */
    protected function initForm(): FormBuilderInterface
    {
        if (1 === $this->getStep()) {
            $this->message = new Message();
            $this->message->setAuthorIpRaw((string) $this->request->getClientIp());
            $this->message->setReferring($this->getReferring());
            $this->message->setHost($this->app->getMainHost());
        } else {
            $this->message = $this->messageRepo->find($this->getId())
                ?? throw new NotFoundHttpException('An error occured during the validation ('.$this->getId().')');

            // add a security check ? Comparing current IP and previous one
            // IPUtils::checkIp($request->getClientIp()), $this->message->getAuthorIpRaw())
            // sinon, passer l'id dans la session plutôt que dans la requête
        }

        $form = $this->formFactory->createBuilder(FormType::class, $this->message, ['csrf_protection' => false]); // ['csrf_protection' => false]

        $form->setAction($this->router->generate('pushword_conversation', [
            'type' => $this->getType(),
            'referring' => $this->getReferring(),
            'id' => $this->getId(),
            'step' => $this->getStep(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));

        return $form;
    }

    /**
     * @return FormBuilderInterface<Message>
     */
    public function getCurrentStep(): FormBuilderInterface
    {
        $currentStepMethod = 'getStep'.self::$step[$this->getStep()];

        if (! method_exists($this, $currentStepMethod)) {
            throw new Exception();
        }

        $currentStep = $this->$currentStepMethod(); // @phpstan-ignore-line

        if (! $currentStep instanceof FormBuilderInterface) {
            throw new Exception();
        }

        return $currentStep;
    }

    /**
     * @return FormBuilderInterface<Message>
     */
    abstract protected function getStepOne(): FormBuilderInterface;

    /**
     * Return rendered response (success or error).
     */
    public function validCurrentStep(FormInterface $form): string
    {
        $currentStepMethod = 'valid'.self::$step[$this->getStep()];
        if (method_exists($this, $currentStepMethod)) {
            // @phpstan-ignore-next-line
            return $this->$currentStepMethod($form);
        }

        return $this->defaultStepValidator($form);
    }

    /** @param FormInterface<Message|null> $form */
    protected function validStepOne(FormInterface $form): string
    {
        return $this->defaultStepValidator($form);
    }

    /** @param FormInterface<Message|null> $form */
    protected function defaultStepValidator(FormInterface $form): string
    {
        if ($form->isValid()) {
            $this->sanitizeConversation();
            $this->getDoctrine()->getManager()->persist($this->message);
            $this->getDoctrine()->getManager()->flush();
            $this->messageId = (int) $this->message->getId();

            if (false !== $this->getNextStepFunctionName()) {
                $this->incrementStep();

                return $this->showForm($this->getCurrentStep()->getForm());
                /*
                return $this->redirectToRoute('pushword_conversation', [
                    'type' => $this->getType(),
                    'id' => $this->message->getId(),
                    'step' => $this->getStep() + 1,
                ]);*/
            }

            // $form = $form->createView();

            return $this->showSuccess();
        }

        // return the form with errors highlighted
        return $this->showForm($form);
    }

    private function getView(string $path): string
    {
        return $this->app->getView('/conversation/'.$path, '@PushwordConversation');
    }

    public function getShowFormTemplate(): string
    {
        $view = $this->getView($this->getType().$this->getReferring().'Step'.$this->getStep().'.html.twig');

        if (! $this->twig->getLoader()->exists($view)) {
            $view = $this->getView($this->getType().'Step'.$this->getStep().'.html.twig');
        }

        if (! $this->twig->getLoader()->exists($view)) {
            return $this->getView('conversation.html.twig');
        }

        return $view;
    }

    public function showForm(FormInterface $form): string
    {
        return $this->twig->render($this->getShowFormTemplate(), [
            'conversation' => $form->createView(),
        ]);
    }

    protected function showSuccess(): string
    {
        $view = $this->app->getView('/conversation/alert.html.twig', '@PushwordConversation');

        return $this->twig->render($view, [
            'message' => $this->translator->trans($this->successMessage),
            'context' => 'success',
        ]);
    }

    protected function getNextStepFunctionName(): string|false
    {
        $getFormMethod = 'getStep'.self::$step[$this->getNextStep()];
        if (! method_exists($this, $getFormMethod)) {
            return false;
        }

        return $getFormMethod;
    }

    protected function getNextStep(): int
    {
        return $this->getStep() + 1;
    }

    protected function sanitizeConversation(): void
    {
        $this->message->setContent(
            htmlspecialchars($this->message->getContent())
        );
    }

    protected function getStep(): int
    {
        if (null !== $this->currentStep) {
            return $this->currentStep;
        }

        return $this->currentStep = $this->request->query->getInt('step', 1);
    }

    protected function incrementStep(): void
    {
        $currentStep = $this->getStep();

        $this->currentStep = $currentStep + 1;
    }

    protected function getId(): int
    {
        return $this->messageId ?? $this->request->query->getInt('id', 0);
    }

    private function get(string $key): string
    {
        $attributes = $this->request->attributes->all();
        $query = $this->request->query->all();
        if (! isset($attributes[$key]) && ! isset($query[$key])) {
            throw new Exception($key.' not found');
        }

        $getOrPost = ($attributes[$key] ?? $query[$key]);
        assert(is_scalar($getOrPost));

        return (string) $getOrPost;
    }

    protected function getReferring(): string
    {
        return $this->get('referring');
    }

    protected function getType(): string
    {
        return $this->get('type');
    }

    protected function getDoctrine(): Registry
    {
        return $this->doctrine;
    }

    /**
     * @return Constraint[]
     */
    protected function getAuthorNameConstraints(): array
    {
        return [
            new NotBlank(),
            new Length(max: 100, maxMessage: 'conversation.name.long'),
        ];
    }

    /**
     * @return Constraint[]
     */
    protected function getAuthorEmailConstraints(bool $canBeAPhoneNumber = false): array
    {
        if ($canBeAPhoneNumber) {
            return [
                new NotBlank(),
                new AtLeastOneOf([
                    new Email(message: 'conversationEmailInvalid'),
                    new Regex(pattern: "/^ *(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4} *$/", message: 'user.phoneNumber.invalid'),
                ])];
        }

        return [
            new NotBlank(),
            new Email(message: 'conversationEmailInvalid', mode: 'strict'),
        ];
    }
}
