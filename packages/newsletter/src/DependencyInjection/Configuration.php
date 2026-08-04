<?php

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
                    ->variableNode('app_fallback_properties')->defaultValue(self::DEFAULT_APP_FALLBACK)->cannotBeEmpty()->end()
                    ->scalarNode('newsletter_possible_origins')
                        ->defaultNull()
                        ->info('Regex matching the origins allowed to POST the subscribe endpoint cross-domain (a statically generated site posting to its live host). Falls back to the conversation setting when null.')
                    ->end()
                    ->booleanNode('newsletter_csrf_protection')
                        ->defaultTrue()
                        ->info('Require a token issued by the form endpoint before accepting a subscription. The token lives in the session, so it only survives when the page and the live host are same-site — turn this off where a static build is served from another domain, or the session cookie never comes back and every subscription fails.')
                    ->end()
                    ->integerNode('send_batch')
                        ->defaultValue(50)
                        ->info('Maximum number of mails a single `pw:newsletter:tick` run may send.')
                    ->end()
                    ->scalarNode('bounce_maildir')
                        ->defaultNull()
                        ->info('Absolute path to the maildir delivery failures come back to, which is the mailbox `framework.mailer.envelope.sender` points at. `pw:newsletter:bounces` reads `new/` there and files what it read into `cur/`. Null leaves the command with nothing to read.')
                    ->end()
                ->end()
        ;

        return $treeBuilder;
    }
}
