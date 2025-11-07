<?php

namespace Pushword\Core\Service\Markdown\Extension\Parser;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Pushword\Core\Service\Markdown\Extension\Node\ObfuscatedEmail;

use function Safe\preg_match;

/**
 * Parse automatiquement les emails et les convertit en liens obfusqués.
 * Note: Ce parser fonctionne en post-processing car league/commonmark
 * nécessite un pattern fixe pour getMatchDefinition().
 */
final class EmailAutolinkParser implements InlineParserInterface
{
    private const string EMAIL_REGEX = '/^([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/i';

    public function getMatchDefinition(): InlineParserMatch
    {
        // On match tous les caractères alphanumériques qui pourraient commencer un email
        return InlineParserMatch::regex('[A-Za-z0-9._+-]');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        // Vérifier qu'on a bien un email
        if (0 === preg_match(self::EMAIL_REGEX, $cursor->getRemainder(), $matches)) {
            return false;
        }

        $email = $matches[1]; // @phpstan-ignore-line

        // Vérifier le caractère qui suit l'email
        $nextChar = $cursor->peek(\strlen($email));
        if (null !== $nextChar && ! \in_array($nextChar, [' ', "\t", "\n", '.', ',', '!', '?', ')', '>', "\x0b", "\x0c", "\x0d", '<', '/'], true)) {
            return false;
        }

        $cursor->advanceBy(\strlen($email));

        // Créer un nœud ObfuscatedEmail
        $emailNode = new ObfuscatedEmail('mailto:'.$email, $email);
        $inlineContext->getContainer()->appendChild($emailNode);

        return true;
    }
}
