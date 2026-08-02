<?php

namespace Pushword\Newsletter\Trigger;

/**
 * Substitutes what a {@see TriggerOccurrence} lends into a step's subject and
 * body:
 *
 *     {{ page.h1 }}  {{ page.excerpt }}  {{ page.url }}  {{ customer.firstName }}
 *
 * The braces are borrowed from Twig; the evaluation is not. This is `strtr` with
 * a regex, exactly like the campaign body's `%name%` — a mail body is authored
 * content, and rendering it as a template would mean evaluating editor input at
 * send time. Which keys exist is the source's business and not this class's: a
 * name it was not given is left where it stands, so a typo shows up in the
 * preview instead of vanishing.
 *
 * Substitution happens once, when the occurrence is handled. What a campaign or
 * an enrollment stores is plain Markdown, which is why every later stage —
 * Markdown, link absolutization, `utm_*` tagging — needs to know nothing about
 * pages, customers, or whatever else a source watches.
 */
final readonly class PlaceholderRenderer
{
    private const string PATTERN = '/\{\{\s*([a-zA-Z]\w*(?:\.\w+)+)\s*\}\}/';

    /**
     * The body is authored HTML-in-Markdown: what the subject lends goes in as
     * it stands.
     *
     * @param array<string, string> $placeholders
     */
    public function render(string $template, array $placeholders): string
    {
        return $this->substitute($template, $placeholders, false);
    }

    /**
     * A subject line is a header, not a document. An h1 routinely carries `<em>`,
     * `<br>` or a `<span class="…">`, and an excerpt taken from rendered content
     * is HTML by construction — both would reach the inbox as literal markup.
     *
     * @param array<string, string> $placeholders
     */
    public function renderSubject(string $template, array $placeholders): string
    {
        return $this->substitute($template, $placeholders, true);
    }

    /** @param array<string, string> $placeholders */
    private function substitute(string $template, array $placeholders, bool $plainText): string
    {
        if ([] === $placeholders || ! str_contains($template, '{{')) {
            return $template;
        }

        return preg_replace_callback(
            self::PATTERN,
            static function (array $match) use ($placeholders, $plainText): string {
                $value = $placeholders[$match[1]] ?? null;

                if (null === $value) {
                    return $match[0];
                }

                return $plainText ? self::plainText($value) : $value;
            },
            $template,
        ) ?? $template;
    }

    /**
     * Tags become a space rather than nothing: a `<br>` between two words must not
     * glue them. Entities are decoded afterwards — never before, or an escaped
     * `&lt;em&gt;` would turn into a tag on its way out.
     */
    private static function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace('<', ' <', $html)), \ENT_QUOTES | \ENT_HTML5);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
