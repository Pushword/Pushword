<?php

declare(strict_types=1);

namespace Pushword\StaticGenerator\Tests\Generator;

use PHPUnit\Framework\TestCase;
use Pushword\StaticGenerator\Generator\HtmlMinification;
use Pushword\StaticGenerator\Generator\HtmlMinifier;
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

    public function testProtocolSupportsEmptyBatches(): void
    {
        $process = new Process([$this->binary()], input: '{"version":1,"id":42,"operation":"minify_html","documents":[]}'."\n");
        $process->mustRun();
        self::assertSame(['version' => 1, 'id' => 42, 'documents' => []], json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR));
    }

    private function binary(): string
    {
        $binary = __DIR__.'/../target/release/pushword-html-minifier';
        self::assertTrue(is_executable($binary), 'Run make -C packages/static-generator/rust build first');

        return $binary;
    }
}
