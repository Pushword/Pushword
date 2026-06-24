<?php

namespace Pushword\Quiz\Service;

/**
 * Pulls the JSON payloads of every quiz block out of a flat file's raw text,
 * for both authoring forms:
 *
 *   {% quiz %}{ …json… }{% endquiz %}   — body is raw JSON.
 *   {{ quiz('{ …json… }') }}            — body is a single-quoted Twig string,
 *                                         so `\'`/`\\` are unescaped back to JSON.
 *
 * Used by {@see \Pushword\Quiz\Command\QuizValidateCommand} so an agent can lint
 * a file without a running server.
 */
final class QuizBlockExtractor
{
    /**
     * @return list<array{json: string, line: int, form: string}> ordered by position
     */
    public function extract(string $content): array
    {
        $blocks = [];

        if (preg_match_all('/\{%\s*quiz\s*%\}(.*?)\{%\s*endquiz\s*%\}/s', $content, $matches, \PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[1] as $index => $match) {
                $blocks[] = [
                    'json' => trim($match[0]),
                    'line' => $this->lineAt($content, $matches[0][$index][1]),
                    'form' => '{% quiz %}',
                ];
            }
        }

        $functionForm = <<<'REGEX'
            /\{\{\s*quiz\(\s*'((?:[^'\\]|\\.)*)'\s*\)\s*\}\}/s
            REGEX;
        if (preg_match_all($functionForm, $content, $matches, \PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[1] as $index => $match) {
                $blocks[] = [
                    'json' => trim($this->unescapeTwigString($match[0])),
                    'line' => $this->lineAt($content, $matches[0][$index][1]),
                    'form' => "{{ quiz('…') }}",
                ];
            }
        }

        usort($blocks, static fn (array $a, array $b): int => $a['line'] <=> $b['line']);

        return $blocks;
    }

    /**
     * Twig single-quoted strings only escape `\'` and `\\`; undo both.
     */
    private function unescapeTwigString(string $value): string
    {
        return preg_replace('/\\\\([\\\\\'])/', '$1', $value) ?? $value;
    }

    private function lineAt(string $content, int $offset): int
    {
        return substr_count(substr($content, 0, $offset), "\n") + 1;
    }
}
