<?php

namespace Pushword\Quiz\Tests\Command;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

#[Group('integration')]
final class QuizCommandTest extends KernelTestCase
{
    private function tester(string $name): CommandTester
    {
        $application = new Application(self::createKernel());

        return new CommandTester($application->find($name));
    }

    private function fileWith(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'pw-quiz-');
        file_put_contents($path, $content);

        return $path;
    }

    public function testValidateReportsValidFile(): void
    {
        $path = $this->fileWith('{% quiz %}{"questions":[{"q":"Q?",'
            .'"answers":[{"a":"A","correct":true},{"a":"B"}]}]}{% endquiz %}');

        $tester = $this->tester('pw:quiz:validate');
        $tester->execute(['path' => $path]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('valid', $tester->getDisplay());
        unlink($path);
    }

    public function testValidateFailsWithPreciseViolations(): void
    {
        $path = $this->fileWith('{% quiz %}{"pass":150,"questions":[{"q":"","answers":[{"a":"only"}]}]}{% endquiz %}');

        $tester = $this->tester('pw:quiz:validate');
        $tester->execute(['path' => $path]);

        self::assertSame(1, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString('path', $display);
        self::assertStringContainsString('message', $display);
        self::assertStringContainsString('pass', $display);
        unlink($path);
    }

    public function testValidateWarnsOnUnknownCtaWithoutFailing(): void
    {
        $path = $this->fileWith('{% quiz %}{"cta":"bogusForm","questions":[{"q":"Q?",'
            .'"answers":[{"a":"A","correct":true},{"a":"B"}]}]}{% endquiz %}');

        $tester = $this->tester('pw:quiz:validate');
        $tester->execute(['path' => $path]);

        self::assertSame(0, $tester->getStatusCode(), 'an unknown cta is a warning, not a failure');
        self::assertStringContainsString('unknown cta "bogusForm"', $tester->getDisplay());
        unlink($path);
    }

    public function testSchemaCommandPrintsValidJsonSchema(): void
    {
        $tester = $this->tester('pw:quiz:schema');
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $schema = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($schema);
        self::assertSame('Pushword Quiz', $schema['title']);
        self::assertArrayHasKey('question', $schema['$defs']);
    }
}
