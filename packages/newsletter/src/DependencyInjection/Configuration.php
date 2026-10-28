<?php

declare(strict_types=1);

namespace Pushword\Newsletter\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * Site properties this bundle reads through SiteConfig, so an `apps:` entry
     * can override them per host.
     *
     * @var string[]
     */
    final public const array DEFAULT_APP_FALLBACK = [
        'newsletter_possible_origins',
        'newsletter_csrf_protection',
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('newsletter');
        $treeBuilder
            ->getRootNode()
                ->children()
                    ->scalarNode('catalogue_default_host')->defaultValue('')
                        ->info('Initial site filter for the campaign catalogue. Empty shows every site; ?host= clears the default.')
                    ->end()
                    ->booleanNode('bot_click_qualification')->defaultFalse()
                        ->info('Flag known automated user agents. All other clicks remain unclassified; no human identity is inferred.')
                    ->end()
                    ->arrayNode('click_tracking_scopes')
                        ->defaultValue(['body'])
                        ->enumPrototype()->values(['body', 'navigation', 'footer'])->end()
                        ->info('Eligible explicitly marked link scopes. System and unsubscribe links are always excluded; both consent gates still apply.')
                    ->end()
                    ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
                    ->arrayNode('sender_profiles')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('label')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('from_email')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('from_name')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('reply_to')->defaultNull()->end()
                                ->scalarNode('signature_markdown')->defaultNull()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->arrayNode('email_templates')
                        ->useAttributeAsKey('name')
                        ->arrayPrototype()
                            ->children()
                                ->scalarNode('label')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('template')->isRequired()->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                    ->scalarNode('newsletter_possible_origins')
                        ->defaultNull()
                        ->info('Space-separated origins (exact match) allowed to POST the subscribe endpoint cross-domain (a statically generated site posting to its live host). Falls back to the conversation setting when null.')
                    ->end()
                    ->booleanNode('newsletter_csrf_protection')
                        ->defaultTrue()
                        ->info('Require a token issued by the form endpoint before accepting a subscription, so a post that never fetched a form is refused. The token is signed rather than kept in the session, which is what lets it survive the cross-domain round trip a statically generated site makes — leave this on there too.')
                    ->end()
                    ->integerNode('send_batch')
                        ->defaultValue(50)
                        ->info('Maximum number of mails a single `pw:newsletter:tick` run may send.')
                    ->end()
                    ->scalarNode('bounce_maildir')
                        ->defaultNull()
                        ->info('Absolute path to the maildir delivery failures come back to, which is the mailbox `framework.mailer.envelope.sender` points at. `pw:newsletter:bounces` reads `new/` there and files what it read into `cur/`. Null leaves the command with nothing to read.')
                    ->end()
                    ->scalarNode('bounce_imap_dsn')
                        ->defaultNull()
                        ->info('The same mailbox when it only exists on a remote server: `imaps://user:password@host:993/INBOX`, the folder optional. `pw:newsletter:bounces` searches UNSEEN there and sets \Seen on what it read, which is the IMAP equivalent of the maildir `cur/` move. Needs `webklex/php-imap`. Set this or `bounce_maildir`, never both.')
                    ->end()
                ->end()
        ;

        return $treeBuilder;
    }
}
