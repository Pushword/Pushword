<?php

namespace Pushword\Conversation\Controller;

use ErrorException;
use Pushword\Core\Component\App\AppPool;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment as Twig;

class ConversationFormController extends AbstractController
{
    private $translator;

    protected $form;

    /** @var array */
    protected $possibleOrigins = [];

    /** @var ParameterBagInterface */
    protected $params;

    /** @var AppPool */
    protected $apps;

    /** @var Twig */
    protected $twig;

    public function __construct(
        TranslatorInterface $translator,
        AppPool $apps,
        ParameterBagInterface $params,
        Twig $twig
    ) {
        $this->translator = $translator;
        $this->params = $params;
        $this->apps = $apps;
        $this->twig = $twig;
    }

    protected function getFormManagerClass($type)
    {
        $param = 'conversation_form_'.$type;

        if (! $this->apps->get()->has($param)) {
            throw new \Exception('`'.$type.'` does\'nt exist (not configured).');
        }

        $class = $this->apps->get()->get($param);
        if (! class_exists($class)) {
            throw new \Exception('`'.$type.'` does\'nt exist.');
        }

        return $class;
    }

    /**
     * Return current form manager depending on `type` (request).
     */
    protected function getFormManager(string $type, Request $request)
    {
        if (null !== $this->form) {
            return $this->form;
        }

        $class = $this->getFormManagerClass($type);

        return $this->form = new $class(
            $this->params->get('pw.conversation.entity_message'),
            $request,
            $this->get('doctrine'),
            $this->get('security.token_storage'),
            $this->get('form.factory'),
            $this->twig,
            $this->get('router'),
            $this->get('translator'),
            $this->apps
        );
    }

    protected function getPossibleOrigins(Request $request): array
    {
        //$host = $request->getHost();
        $app = $this->apps->get();

        if (! empty($this->possibleOrigins)) {
            return $this->possibleOrigins;
        }

        if ($app->get('conversation_possible_origins')) {
            $this->possibleOrigins = explode(' ', $app->get('conversation_possible_origins'));
        }

        //$this->possibleOrigins[] = 'https://'.$request->getHost(); // ???
        //$this->possibleOrigins[] = 'http://'.$request->getHost();
        // just for dev
        $this->possibleOrigins[] = 'http://'.$request->getHost().':8000';
        $this->possibleOrigins[] = 'http://'.$request->getHost().':8001';
        $this->possibleOrigins[] = 'http://'.$request->getHost().':8002';

        foreach ($app->getHosts() as $host) {
            $this->possibleOrigins[] = 'https://'.$host;
        }

        return $this->possibleOrigins;
    }

    protected function initResponse($request): Response
    {
        $response = new Response();

        if (! \in_array($request->headers->get('origin'), $this->getPossibleOrigins($request))) {
            throw new ErrorException('origin sent is not authorized.');
        }

        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Origin, Content-Type, X-Auth-Token');
        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('origin'));

        return $response;
    }

    public function show(string $type, ?string $host = null, Request $request): Response
    {
        //$host = $host ?? $request->getHost();
        $this->apps->switchCurrentApp($host);

        $response = $this->initResponse($request);

        $form = $this->getFormManager($type, $request)->getCurrentStep()->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            return $response->setContent($this->getFormManager($type, $request)->validCurrentStep($form));
        }

        return $response->setContent($this->getFormManager($type, $request)->showForm($form));
    }
}
