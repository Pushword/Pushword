<?php

namespace Pushword\Quiz\Tests\Twig;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment as Twig;

/**
 * `{% quiz %}…{% endquiz %}` takes its body as raw template text, so the JSON needs
 * no quote escaping — that is the whole point of the tag over the legacy
 * `{{ quiz('…') }}` function.
 */
#[Group('integration')]
final class QuizTagTest extends KernelTestCase
{
    private const string QUESTION = '{"questions":[{"q":"Capital of France?",'
        .'"answers":[{"a":"Paris","correct":true},{"a":"Lyon"}]}]}';

    public function testTagRendersItsBodyAsAQuiz(): void
    {
        $html = $this->render('{% quiz %}'.self::QUESTION.'{% endquiz %}');

        self::assertStringContainsString('Capital of France?', $html);
        self::assertStringContainsString('Paris', $html);
    }

    /** Apostrophes are the reason the tag form exists; they must survive verbatim. */
    public function testApostrophesNeedNoEscaping(): void
    {
        $html = $this->render('{% quiz %}{"questions":[{"q":"Quelle est l\'altitude ?",'
            .'"answers":[{"a":"8 849 m","correct":true},{"a":"4 809 m"}]}]}{% endquiz %}');

        self::assertStringContainsString("l'altitude", $html);
    }

    /** The body is sub-parsed, so an interpolation inside it is still resolved. */
    public function testBodyIsSubParsedAsTwig(): void
    {
        $html = $this->render('{% set city = "Berlin" %}{% quiz %}{"questions":[{"q":"Capital of Germany?",'
            .'"answers":[{"a":"{{ city }}","correct":true},{"a":"Bonn"}]}]}{% endquiz %}');

        self::assertStringContainsString('Berlin', $html);
    }

    public function testMalformedPayloadDegradesToAnErrorInsteadOfBreakingThePage(): void
    {
        $html = $this->render('{% quiz %}not json{% endquiz %}');

        self::assertStringNotContainsString('Capital', $html);
        self::assertNotSame('', trim($html));
    }

    private function render(string $template): string
    {
        self::bootKernel();

        return self::getContainer()->get(Twig::class)->createTemplate($template)->render();
    }
}
