<?php

namespace Pushword\Core\Service\Markdown\Extension\Parser;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Pushword\Core\Service\Markdown\Extension\Node\PhoneNumber;

use function Safe\preg_match;

/**
 * Parse automatiquement les numéros de téléphone français.
 */
final class PhoneAutolinkParser implements InlineParserInterface
{
    // Regex pour les numéros français : +33, 00 33 ou 0 suivi de 9 chiffres
    private const string PHONE_REGEX = '/^(?:(?:\+|00)33|0)(?:\s|&nbsp;|\xC2\xA0)*[1-9](?:(?:[\s.-]|&nbsp;|\xC2\xA0)*\d{2}){4}/u';

    public function getMatchDefinition(): InlineParserMatch
    {
        // Match soit '+', '0' ou '00' qui peuvent commencer un numéro français
        return InlineParserMatch::oneOf('+', '0');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        // Ne pas parser si on est dans du HTML (après un '>')
        $previousChar = $cursor->peek(-1);
        if ('>' === $previousChar) {
            return false;
        }

        // Vérifier qu'on a bien un numéro de téléphone
        if (0 === preg_match(self::PHONE_REGEX, $cursor->getRemainder(), $matches)) {
            return false;
        }

        $phoneNumber = $matches[0]; // @phpstan-ignore-line

        // Vérifier le caractère qui suit
        $nextPos = \strlen($phoneNumber);
        $nextChar = $cursor->peek($nextPos);

        // Le numéro doit être suivi d'un espace, ponctuation ou fin de ligne
        if (null !== $nextChar && ! \in_array($nextChar, [' ', "\t", "\n", '.', ',', '!', '?', ')', '>', "\x0b", "\x0c", "\x0d", '<', '/', ';'], true)) {
            return false;
        }

        $cursor->advanceBy($nextPos);

        // Créer un nœud PhoneNumber
        $phoneNode = new PhoneNumber($phoneNumber);
        $inlineContext->getContainer()->appendChild($phoneNode);

        return true;
    }
}
