<?php

namespace Pushword\Core\Service\Markdown\Extension\Parser;

use IntlDateFormatter;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use Pushword\Core\Site\SiteRegistry;

use function Safe\date as safeDate;

/**
 * Parse les shortcodes date dans le markdown.
 * Syntax: date(Y), date(M), date(S), date(W), etc.
 */
final readonly class DateShortcodeParser implements InlineParserInterface
{
    public function __construct(
        private SiteRegistry $apps
    ) {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::string('date(');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $cursor = $inlineContext->getCursor();

        // Sauvegarder la position initiale
        $initialState = $cursor->saveState();

        // Avancer de 5 caractères pour passer "date("
        $cursor->advanceBy(5);

        // Chercher le format jusqu'à ')' (le match() avance automatiquement le curseur)
        $format = $cursor->match('/^([^)]+)/');
        if (null === $format) {
            $cursor->restoreState($initialState);

            return false;
        }

        // Avancer d'un caractère pour passer le ')'
        if (')' !== $cursor->getCharacter()) {
            $cursor->restoreState($initialState);

            return false;
        }

        $cursor->advanceBy(1);

        // Nettoyer le format (enlever les quotes si présentes)
        $format = trim($format, '\'"');
        $format = ltrim($format, '%');

        // Convertir le shortcode en date formatée
        $dateString = $this->convertDateShortcode($format);

        // Créer un nœud Text avec la date
        $inlineContext->getContainer()->appendChild(new Text($dateString));

        return true;
    }

    private function getLocale(): string
    {
        return $this->apps->get()->getLocale();
    }

    private function convertDateShortcode(string $format): string
    {
        $locale = $this->convertLocale($this->getLocale());
        $intlDateFormatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE);

        return match ($format) {
            'S' => $this->getSummerYear(),
            'W' => $this->getWinterYear(),
            'Y-1' => date('Y', strtotime('-1 year')),
            'Y+1' => date('Y', strtotime('next year')),
            'Y' => date('Y'),
            'B', 'M' => $this->formatDate($intlDateFormatter, 'MMMM'),
            'A' => $this->formatDate($intlDateFormatter, 'cccc'),
            'e' => $this->formatDate($intlDateFormatter, 'd'),
            default => $format, // Fallback : retourner le format lui-même
        };
    }

    private function formatDate(IntlDateFormatter $formatter, string $pattern): string
    {
        $formatter->setPattern($pattern);

        return (string) $formatter->format(time());
    }

    private function getWinterYear(): string
    {
        return date('m') < 4 ? safeDate('Y') : safeDate('Y', strtotime('next year'));
    }

    private function getSummerYear(): string
    {
        return date('m') < 10 ? safeDate('Y') : safeDate('Y', strtotime('next year'));
    }

    private function convertLocale(string $locale): string
    {
        return match ($locale) {
            'fr' => 'fr_FR',
            'en' => 'en_UK',
            default => $locale,
        };
    }
}
