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

final class ConversationFormController extends AbstractController
{
    private TranslatorInterface $translator;

    private $form;

    private array $possibleOrigins = [];

    private ParameterBagInterface $params;

    private AppPool $apps;

    private Twig $twig;

    private string $env;

    public function __construct(
        TranslatorInterface $translator,
        AppPool $apps,
        ParameterBagInterface $params,
        Twig $twig,
        string $env
    ) {
        $this->translator = $translator;
        $this->params = $params;
        $this->apps = $apps;
        $this->env = $env;
        $this->twig = $twig;
    }

    private function getFormManagerClass($type)
    {
        $param = 'conversation_form_'.str_replace('-', '_', $type);

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
    private function getFormManager(string $type, Request $request)
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
            $this->translator,
            $this->apps
        );
    }

    private function getPossibleOrigins(Request $request): array
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
        if ('dev' == $this->env) {
            $this->possibleOrigins[] = 'http://'.$request->getHost().':8000';
            $this->possibleOrigins[] = 'http://'.$request->getHost().':8001';
            $this->possibleOrigins[] = 'http://'.$request->getHost().':8002';
        }

        foreach ($app->getHosts() as $host) {
            $this->possibleOrigins[] = 'https://'.$host;
        }

        return $this->possibleOrigins;
    }

    private function initResponse($request): Response
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
