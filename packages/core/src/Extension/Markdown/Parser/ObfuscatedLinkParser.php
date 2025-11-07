<?php

namespace Pushword\Core\Extension\Markdown\Parser;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Pushword\Core\Extension\Markdown\Node\ObfuscatedLink;

/**
 * Parse les liens obfusqués dans le markdown.
 * Syntax: #[text](url) or #[text](url){#id} or #[text](url){.class}.
 */
final class ObfuscatedLinkParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('#[');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        // Sauvegarder la position initiale
        $initialState = $cursor->saveState();

        // Avancer de 2 caractères pour passer le '#['
        $cursor->advanceBy(2);

        // Chercher le texte du lien jusqu'à ']'
        $text = $cursor->match('/^([^\]]+)\]/');
        if (null === $text) {
            $cursor->restoreState($initialState);

            return false;
        }

        // Vérifier qu'on a bien un '('
        if ('(' !== $cursor->getCharacter()) {
            $cursor->restoreState($initialState);

            return false;
        }

        $cursor->advanceBy(1);

        // Chercher l'URL jusqu'à ')'
        $url = $cursor->match('/^([^\)]+)\)/');
        if (null === $url) {
            $cursor->restoreState($initialState);

            return false;
        }

        // Vérifier s'il y a des attributs {#id} ou {.class}
        $attributeClass = null;
        $attributeId = null;

        if ('{' === $cursor->getCharacter()) {
            $cursor->advanceBy(1);
            $attributes = $cursor->match('/^([^}]+)/');

            if (null !== $attributes && '}' === $cursor->getCharacter()) {
                $cursor->advanceBy(1);

                if (str_starts_with($attributes, '#')) {
                    $attributeId = substr($attributes, 1);
                } elseif (str_starts_with($attributes, '.')) {
                    $attributeClass = substr($attributes, 1);
                }
            }
        }

        // Créer le node ObfuscatedLink
        $link = new ObfuscatedLink($url, $text);
        if (null !== $attributeClass) {
            $link->setAttributeClass($attributeClass);
        }

        if (null !== $attributeId) {
            $link->setAttributeId($attributeId);
        }

        $inlineContext->getContainer()->appendChild($link);

        return true;
    }
}
