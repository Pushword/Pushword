<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Pushword\Core\Service\NativeWorker;
use Pushword\StaticGenerator\Generator\HtmlMinification;
use Pushword\StaticGenerator\Generator\HtmlMinifier;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Process\Process;

/** Explicit native suite: requires a built binary and never skips missing tooling. */
final class NativeHtmlMinificationTest extends TestCase
{
    public function testCorpusAndRepeatedRequestsMatchPhpByteForByte(): void
    {
        $json = file_get_contents(__DIR__.'/corpus.json');
        self::assertIsString($json);
        $documents = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($documents);
        $minifier = new HtmlMinification($this->binary());

        try {
            foreach ($documents as $index => $html) {
                self::assertIsString($html);
                $expected = HtmlMinifier::compress($html);
                self::assertSame($expected, $minifier->compress($html), 'Fixture '.$index);
                self::assertSame(HtmlMinifier::compress($expected), $minifier->compress($expected), 'Second pass '.$index);
            }

            // A response larger than a pipe buffer must be drained while sending input.
            $large = str_repeat('<p>Unicode é 🦀</p>', 10000);
            self::assertSame([$large, ''], $minifier->compressMany([$large, '']));
        } finally {
            $minifier->reset();
        }
    }

    public function testProtocolRejectsMalformedFramesWithoutSuccessOutput(): void
    {
        $requests = ["not JSON\n", "\xff\n", '{"version":1}',
            '{"version":2,"id":1,"operation":"minify_html","documents":[]}'."\n",
            '{"version":1,"id":1,"operation":"unknown","documents":[]}'."\n",
            '{"version":1,"id":1,"operation":"minify_html","documents":[null]}'."\n",
            '{"version":1,"id":1,"operation":"minify_html","documents":[],"extra":true}'."\n",
            str_repeat('x', 16 * 1024 * 1024 + 1)."\n",
        ];
        foreach ($requests as $request) {
            $process = new Process([$this->binary()], input: $request);
            $process->run();
            self::assertFalse($process->isSuccessful());
            self::assertSame('', $process->getOutput());
            self::assertNotSame('', $process->getErrorOutput());
        }
    }

    public function testControlCharactersMatchPhpInTextAttributesAndProtectedElements(): void
    {
        $documents = [];
        foreach (range(0, 31) as $code) {
            foreach ([\chr($code), '&#'.$code.';', '&#x'.dechex($code).';', '&#000'.$code.';', '&#X000'.strtoupper(dechex($code)).';', '&#'.$code.'Z', '&#x'.dechex($code).'Z'] as $control) {
                $text = 'a'.$control.'é';
                $documents[] = '<!DOCTYPE html><html><head><style>'.$text.'</style></head><body>'
                    .'<p title="'.$text.'"><a href="/'.$text.'">'.$text.'</a></p>'
                    .'<pre>'.$text.'</pre><code>'.$text.'</code><textarea>'.$text.'</textarea>'
                    .'<script>'.$text.'</script></body></html>';
                $documents[] = '<!DOCTYPE html><html><body><a href="/'.$control.'">URI only</a></body></html>';
            }
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $minifier = new HtmlMinification($this->binary(), logger: $logger);

        try {
            self::assertSame(array_map(HtmlMinifier::compress(...), $documents), $minifier->compressMany($documents));
        } finally {
            $minifier->reset();
        }
    }

    public function testProtectedReplacementsPreserveOrderedPhpSemantics(): void
    {
        $bodies = [
            '<pre>  a\n b </pre><pre>  a\n b </pre><code> c </code><script> d </script><textarea> e </textarea>',
            '<pre><code> nested\n code </code></pre><code> nested\n code </code>',
            '<script>const s = "<code> a </code>";\n  run(s);</script><code> a </code>',
            '<style>.x:after { content: "<code> a </code>"; }</style><code> a </code>',
            '<pre><code-placeholder-0></code-placeholder-0></pre><code> b </code>',
            '<pre-placeholder-0></pre-placeholder-0><pre> c </pre>',
            '<code></code><code></code><textarea>\n x </textarea>',
        ];
        $documents = array_map(static fn (string $body): string => '<!DOCTYPE html><html><body>'.str_replace('\\n', "\n", $body).'</body></html>', $bodies);
        $worker = new NativeWorker($this->binary());

        try {
            self::assertSame(array_map(HtmlMinifier::compress(...), $documents), $worker->request('minify_html', $documents));
        } finally {
            $worker->reset();
        }
    }

    public function testCachedSerializationProfileSurvivesResetAndMixedBatches(): void
    {
        $documents = [
            '<!DOCTYPE html><html><body><p title="&quot;">first</p><svg xml:space="preserve" xml:lang="fr"></svg></body></html>',
            '<!DOCTYPE html><html><body><pre title="&quot;">next</pre><source src="x"></body></html>',
            '<!DOCTYPE html><html><body><a href="/&#13;">control reference</a></body></html>',
        ];
        $expected = array_map(HtmlMinifier::compress(...), $documents);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $minifier = new HtmlMinification($this->binary(), logger: $logger, cache: new ArrayAdapter());

        try {
            self::assertSame($expected[0], $minifier->compress($documents[0]));
            $minifier->reset();
            self::assertSame($expected, $minifier->compressMany($documents));
            $minifier->reset();
            self::assertSame($expected, $minifier->compressMany($documents));
        } finally {
            $minifier->reset();
        }
    }

    public function testProtocolSupportsLegacyAndHtml5Serialization(): void
    {
        $input = [
            '<!DOCTYPE html><html><body><p title="&quot;">x</p><pre title="&quot;"> y </pre></body></html>',
            '<!DOCTYPE html><html><body><svg xml:lang="fr" xml:space="preserve" xml:base="/x"><use xlink:href="#x"></use></svg></body></html>',
        ];
        $legacyParagraph = <<<'HTML'
            <!DOCTYPE html><html><head></head><body><p title='"'>x</p><pre title='"'> y </pre></body></html>
            HTML;
        $legacy = [$legacyParagraph, '<!DOCTYPE html><html><head></head><body><svg xml:lang="fr" xml:space="preserve" xml:base="/x"><use xlink:href="#x"></use></svg></body></html>'];
        $html5 = [
            '<!DOCTYPE html><html><head></head><body><p title="&quot;">x</p><pre title="&quot;"> y </pre></body></html>',
            '<!DOCTYPE html><html><head></head><body><svg lang="fr" space="preserve" xml:base="/x"><use xlink:href="#x"></use></svg></body></html>',
        ];
        $worker = new NativeWorker($this->binary());

        try {
            self::assertSame($legacy, $worker->request('minify_html', $input));
            self::assertSame($html5, $worker->request('minify_html5', $input));
            self::assertSame([$html5[0], $legacy[1]], $worker->request('minify_html5_with_xml_prefixes', $input));
        } finally {
            $worker->reset();
        }
    }

    public function testProtocolSupportsEmptyBatches(): void
    {
        $process = new Process([$this->binary()], input: '{"version":1,"id":42,"operation":"minify_html","documents":[]}'."\n");
        $process->mustRun();
        self::assertSame(['version' => 1, 'id' => 42, 'documents' => []], json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testNoscriptAndAlpineAliasesMatchBothSerializationProfiles(): void
    {
        $documents = [
            '<!DOCTYPE html><html><body><div @click="open = true" @keydown.escape.window="open = false" x-on:click="open = true" :class="active"><noscript><style>p { color: red; }</style><p>fallback é</p></noscript></div></body></html>',
            '<!DOCTYPE html><html><head><noscript><style>p { color: red; }</style></noscript></head><body><p>text</p></body></html>',
        ];
        $expected = array_map(HtmlMinifier::compress(...), $documents);
        $worker = new NativeWorker($this->binary());

        try {
            self::assertSame($expected, $worker->request('minify_html', $documents));
            self::assertSame($expected, $worker->request('minify_html5', $documents));
            self::assertSame($expected, $worker->request('minify_html5_with_xml_prefixes', $documents));
        } finally {
            $worker->reset();
        }
    }

    private function binary(): string
    {
        $binary = __DIR__.'/../target/release/pushword-html-minifier';
        self::assertTrue(is_executable($binary), 'Run make -C packages/static-generator/rust build first');

        return $binary;
    }
}
