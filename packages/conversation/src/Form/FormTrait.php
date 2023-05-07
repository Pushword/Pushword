<?php

namespace Pushword\Conversation\Form;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Pushword\Conversation\Entity\MessageInterface;
use Pushword\Core\Component\App\AppConfig;
use Pushword\Core\Component\App\AppPool;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

trait FormTrait
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
    protected $successMessage = 'conversation.send.success';

    protected \Symfony\Component\HttpFoundation\Request $request;

    protected \Doctrine\Bundle\DoctrineBundle\Registry $doctrine;

    protected Twig $twig;

    protected TokenStorageInterface $security;

    protected FormFactory $formFactory;

    protected TranslatorInterface $translator;

    protected Router $router;

    /** @var int */
    protected $currentStep;

    protected ?int $messageId = null;

    /**
     * @var class-string<MessageInterface>
     */
    protected string $messageEntity;

    protected MessageInterface $message;

    protected AppPool $apps;

    protected AppConfig $app;

    /**
     * @param class-string<MessageInterface> $messageEntity
     */
    public function __construct(
        string $messageEntity,
        Request $request,
        Registry $registry,
        TokenStorageInterface $tokenStorage,
        FormFactory $formFactory,
        Twig $twig,
        Router $router,
        TranslatorInterface $translator,
        AppPool $appPool
    ) {
        $this->request = $request;
        $this->doctrine = $registry;
        $this->security = $tokenStorage;
        $this->formFactory = $formFactory;
        $this->twig = $twig;
        $this->router = $router;
        $this->translator = $translator;
        $this->messageEntity = $messageEntity;
        $this->apps = $appPool;
        $this->app = $appPool->get();
    }

    /**
     * Initiate Message Entity (or load data from previous message)
     * and return form builder instance.
     */
    protected function initForm(): FormBuilderInterface
    {
        if (1 === $this->getStep()) {
            $this->message = new $this->messageEntity(); // todo, permit to configure it
            $this->message->setAuthorIpRaw((string) $this->request->getClientIp());
            $this->message->setReferring((string) $this->getReferring());
            $this->message->setHost($this->app->getMainHost());
        } else {
            $this->message = $this->doctrine->getRepository($this->messageEntity)->find($this->getId()); // @phpstan-ignore-line ???
            if (null === $this->message) {
                throw new NotFoundHttpException('An error occured during the validation ('.$this->getId().')');
            }

            // add a security check ? Comparing current IP and previous one
            // IPUtils::checkIp($request->getClientIp()), $this->message->getAuthorIpRaw())
            // sinon, passer l'id dans la session plutôt que dans la requête
        }

        $form = $this->formFactory->createBuilder(FormType::class, $this->message); // ['csrf_protection' => false]

        $form->setAction($this->router->generate('pushword_conversation', [
            'type' => $this->getType(),
            'referring' => $this->getReferring(),
            'id' => $this->getId(),
            'step' => $this->getStep(),
        ], UrlGeneratorInterface::ABSOLUTE_URL));

        return $form;
    }

    public function getCurrentStep(): FormBuilderInterface
    {
        $currentStepMethod = 'getStep'.self::$step[$this->getStep()];

        // @phpstan-ignore-next-line
        return $this->$currentStepMethod();
    }

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

    protected function validStepOne(FormInterface $form): string
    {
        return $this->defaultStepValidator($form);
    }

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
            htmlspecialchars((string) $this->message->getContent())
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
        // $this->request->set('step', $this->getStep()+1)
        ++$this->currentStep;
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
            throw new \Exception($key.' not found');
        }

        return (string) ($attributes[$key] ?? $query[$key]);
    }

    protected function getReferring(): ?string
    {
        return $this->get('referring');
    }

    protected function getType(): ?string
    {
        return $this->get('type');
    }

    /**
     * @return \Doctrine\Bundle\DoctrineBundle\Registry
     */
    protected function getDoctrine()
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
            new Length([
                'max' => 100,
                'maxMessage' => 'conversation.name.long',
            ]),
        ];
    }

    /**
     * @return Constraint[]
     */
    protected function getAuthorEmailConstraints(): array
    {
        return [
            new NotBlank(),
            new Email([
                'message' => 'user.email.invalid',
                'mode' => 'strict',
            ]),
        ];
    }
}
