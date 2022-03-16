<?php

namespace Pushword\Conversation\Controller;

use Doctrine\Persistence\ManagerRegistry;
use ErrorException;
use Pushword\Conversation\Form\ConversationFormInterface;
use Pushword\Core\Component\App\AppPool;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

final class ConversationFormController extends AbstractController
{
    private TranslatorInterface $translator;

    private ?ConversationFormInterface $form = null;

    /**
     * @var string[]
     */
    private array $possibleOrigins = [];

    private ParameterBagInterface $params;

    private AppPool $apps;

    private Twig $twig;

    private FormFactoryInterface $formFactory;

    private TokenStorageInterface $tokenStorage;

    private RouterInterface $router;

    private string $env;

    private ManagerRegistry $doctrine;

    public function __construct(
        TranslatorInterface $translator,
        AppPool $appPool,
        ParameterBagInterface $parameterBag,
        Twig $twig,
        FormFactoryInterface $formFactory,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        ManagerRegistry $doctrine,
        string $env
    ) {
        $this->translator = $translator;
        $this->params = $parameterBag;
        $this->apps = $appPool;
        $this->env = $env;
        $this->twig = $twig;
        $this->formFactory = $formFactory;
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;
        $this->doctrine = $doctrine;
    }

    /**
     * @return class-string<ConversationFormInterface>
     */
    private function getFormManagerClass(string $type)
    {
        $param = 'conversation_form_'.str_replace('-', '_', $type);

        if (! $this->apps->get()->has($param)) {
            throw new \Exception('`'.$type.'` does\'nt exist (not configured).');
        }

        $class = \strval($this->apps->get()->get($param));
        if (! class_exists($class)
            || ! (new ReflectionClass($class))->implementsInterface(ConversationFormInterface::class)) {
            throw new \Exception('`'.$type.'` does\'nt exist.');
        }

        return $class; // @phpstan-ignore-line
    }

    /**
     * Return current form manager depending on `type` (request).
     */
    private function getFormManager(string $type, Request $request): ConversationFormInterface
    {
        if (null !== $this->form) {
            return $this->form;
        }

        $class = $this->getFormManagerClass($type);

        return $this->form = new $class(
            $this->params->get('pw.conversation.entity_message'),
            $request,
            $this->doctrine,
            $this->tokenStorage,
            $this->formFactory,
            $this->twig,
            $this->router,
            $this->translator,
            $this->apps
        );
    }

    /**
     * @return mixed[]
     */
    private function getPossibleOrigins(Request $request): array
    {
        // $host = $request->getHost();
        $app = $this->apps->get();

        if ([] !== $this->possibleOrigins) {
            return $this->possibleOrigins;
        }

        if (\is_string($app->get('conversation_possible_origins'))) {
            $this->possibleOrigins = explode(' ', $app->get('conversation_possible_origins'));
        }

        if ('dev' == $this->env) {
            $this->possibleOrigins[] = 'http://'.$request->getHost();
            $this->possibleOrigins[] = 'https://'.$request->getHost();
            $this->possibleOrigins[] = 'http://'.$request->getHost().':8000';
            $this->possibleOrigins[] = 'http://'.$request->getHost().':8001';
            $this->possibleOrigins[] = 'http://'.$request->getHost().':8002';
        }

        foreach ($app->getHosts() as $host) {
            $this->possibleOrigins[] = 'https://'.$host;
        }

        return $this->possibleOrigins;
    }

    private function initResponse(Request $request): Response
    {
        $response = new Response();

        if (! \in_array($request->headers->get('origin'), $this->getPossibleOrigins($request), true)) {
            throw new ErrorException('origin sent is not authorized'.' ('.$request->headers->get('origin').') '.\Safe\json_encode($this->getPossibleOrigins($request)).'.');
        }

        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, X-Auth-Token');
        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('origin'));

        return $response;
    }

    public function show(Request $request, string $type, ?string $host = null): Response
    {
        // $host = $host ?? $request->getHost();
        if (null !== $host) {
            $this->apps->switchCurrentApp($host);
        }

        $response = $this->initResponse($request);

        $form = $this->getFormManager($type, $request)->getCurrentStep()->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            return $response->setContent($this->getFormManager($type, $request)->validCurrentStep($form));
        }

        return $response->setContent($this->getFormManager($type, $request)->showForm($form));
    }
}
