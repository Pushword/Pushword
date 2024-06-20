<?php

namespace Pushword\Conversation\DependencyInjection;

use Pushword\Conversation\Entity\Message;
use Pushword\Conversation\Form\MessageForm;
use Pushword\Conversation\Form\MultiStepMessageForm;
use Pushword\Conversation\Form\NewsletterForm;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var string[]
     */
    final public const DEFAULT_APP_FALLBACK = [
        'conversation_notification_email_to',
        'conversation_notification_email_from',
        'conversation_notification_interval',
        'conversation_form',
        'conversation_form_message',
        'conversation_form_multistep_message',
        'conversation_form_ms_message',
        'conversation_form_newsletter',
        'possible_origins',
    ];

    /**
     * @psalm-suppress UndefinedInterfaceMethod
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('conversation');
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
                    ->scalarNode('entity_message')->defaultValue(Message::class)->cannotBeEmpty()->end()
                    ->scalarNode('conversation_notification_email_to')->defaultNull()->end()
                    ->scalarNode('conversation_notification_email_from')->defaultNull()->end()
                    ->scalarNode('conversation_notification_interval')
                        ->defaultValue('P1D')
                        ->info("DateInterval's format")
                    ->end()
                    ->variableNode('conversation_form')
                        ->defaultValue([
                            'message' => MessageForm::class,
                            'ms_message' => MultiStepMessageForm::class,
                            'multistep_message' => MultiStepMessageForm::class,
                            'newsletter' => NewsletterForm::class,
                        ])
                    ->end()

                    // permit compatibility before v1
                    ->scalarNode('conversation_form_message')
                        ->defaultValue(MessageForm::class)
                    ->end()
                    ->scalarNode('conversation_form_multistep_message')
                        ->defaultValue(MultiStepMessageForm::class)
                    ->end()
                    ->scalarNode('conversation_form_ms_message')
                        ->defaultValue(MultiStepMessageForm::class)
                    ->end()
                    ->scalarNode('conversation_form_newsletter')
                        ->defaultValue(NewsletterForm::class)
                    ->end()

                    ->scalarNode('possible_origins')->defaultNull()->end()
                ->end()
        ;

        return $treeBuilder;
    }
}
