<?php

namespace Pushword\Core\Utils;

use Throwable;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Extracts error details from Twig exceptions.
 * Used by PageScannerService and PageCrudController for consistent error handling.
 */
final readonly class TwigErrorExtractor
{
    /**
     * Extract code excerpt from Twig error showing context around the error line.
     */
    public function getErrorExcerpt(RuntimeError|SyntaxError $exception, int $context = 1): string
    {
        $sourceContext = $exception->getSourceContext();
        if (null === $sourceContext) {
            return '';
        }

        $code = $sourceContext->getCode();
        $lines = explode("\n", $code);
        $line = $exception->getTemplateLine();

        $start = max(0, $line - $context - 1);
        $end = min(\count($lines) - 1, $line + $context - 1);

        $excerpt = \array_slice($lines, $start, $end - $start + 1, true);

        return trim(implode("\n", $excerpt));
    }

    /**
     * Format error message for display with message and optional code excerpt.
     * HTML format works for both CLI (via strip_tags) and Admin UI.
     */
    public function formatErrorMessage(RuntimeError|SyntaxError $exception): string
    {
        $message = $exception->getRawMessage();
        $excerpt = $this->getErrorExcerpt($exception);

        if ('' === $excerpt) {
            return sprintf('error occurred generating the page: <code>%s</code>', htmlspecialchars($message));
        }

        return sprintf(
            'error occurred generating the page: <code>%s</code><br><textarea style="margin-top:4px; width:100%%; display:none;" data-error-excerpt="true" readonly>%s</textarea>',
            htmlspecialchars($message),
            htmlentities($excerpt),
        );
    }

    /**
     * Format a generic exception (non-Twig) for display.
     */
    public function formatGenericErrorMessage(Throwable $exception): string
    {
        return sprintf(
            'error occurred generating the page: <code>%s</code> (%s)',
            htmlspecialchars($exception->getMessage()),
            $exception::class,
        );
    }
}
