<?php

namespace Pushword\Conversation\DependencyInjection;

use Pushword\Conversation\Form\MessageForm;
use Pushword\Conversation\Form\MultiStepMessageForm;
use Pushword\Conversation\Form\NewsletterForm;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    // DeepL free tier: 500,000 chars/month
    public const int DEFAULT_DEEPL_MONTHLY_LIMIT = 450_000;

    // Google Cloud Translation: 500,000 chars/month free
    public const int DEFAULT_GOOGLE_MONTHLY_LIMIT = 450_000;

    /**
     * @var string[]
     */
    final public const array DEFAULT_APP_FALLBACK = [
        'conversation_notification_email_to',
        'conversation_notification_email_from',
        'conversation_notification_interval',
        'conversation_form',
        'conversation_form_message',
        'conversation_form_multistep_message',
        'conversation_form_ms_message',
        'conversation_form_newsletter',
        'possible_origins',
        'translation_deepl_api_key',
        'translation_google_api_key',
        'translation_deepl_use_free_api',
        'translation_deepl_monthly_limit',
        'translation_google_monthly_limit',
        'flat_conversation_global',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('conversation');
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
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
                    ->booleanNode('review_enabled')->defaultTrue()->end()
                    ->scalarNode('translation_deepl_api_key')
                        ->defaultNull()
                        ->info('DeepL API key for translation service')
                    ->end()
                    ->scalarNode('translation_google_api_key')
                        ->defaultNull()
                        ->info('Google Cloud Translation API key')
                    ->end()
                    ->booleanNode('translation_deepl_use_free_api')
                        ->defaultTrue()
                        ->info('Use DeepL free API endpoint')
                    ->end()
                    ->integerNode('translation_deepl_monthly_limit')
                        ->defaultValue(self::DEFAULT_DEEPL_MONTHLY_LIMIT)
                        ->info('Monthly character limit for DeepL (0 = unlimited)')
                    ->end()
                    ->integerNode('translation_google_monthly_limit')
                        ->defaultValue(self::DEFAULT_GOOGLE_MONTHLY_LIMIT)
                        ->info('Monthly character limit for Google Cloud Translation (0 = unlimited)')
                    ->end()
                    ->booleanNode('flat_conversation_global')
                        ->defaultTrue()
                        ->info('If true, export all conversations to a single content/conversation.csv file. If false, export per host to content/{host}/conversation.csv')
                    ->end()
                ->end()
        ;

        return $treeBuilder;
    }
}
