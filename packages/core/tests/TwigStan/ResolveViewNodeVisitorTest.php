<?php

declare(strict_types=1);

namespace Pushword\Core\Tests\TwigStan;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pushword\Core\Site\SiteConfig;
use Pushword\Core\Template\TemplateResolver;
use Pushword\Core\TwigStan\ResolveViewNodeVisitor;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Node\Expression\ConstantExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Source;
use Twig\TwigFunction;

final class ResolveViewNodeVisitorTest extends TestCase
{
    private string $directory;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/pw-twigstan-'.uniqid();
        $this->filesystem = new Filesystem();
        $this->filesystem->mkdir([
            $this->directory.'/templates',
            $this->directory.'/theme',
            $this->directory.'/core',
            $this->directory.'/other',
        ]);
        $this->filesystem->dumpFile($this->directory.'/core/base.html.twig', '{% block content %}{% endblock %}');
        $this->filesystem->dumpFile($this->directory.'/other/base.html.twig', '{% block content %}{% endblock %}');
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    #[DataProvider('literalCalls')]
    public function testMakesTheParentResolvableForAnalysis(string $call, string $expected): void
    {
        $twig = $this->createTwig('site.test');
        $template = $twig->parse($twig->tokenize(new Source("\n{% extends ".$call.' %}', 'child.html.twig')));
        $parent = $template->getNode('parent');

        self::assertInstanceOf(ConstantExpression::class, $parent);
        self::assertSame($expected, $parent->getAttribute('value'));
        self::assertSame(2, $parent->getTemplateLine());
    }

    /** @return Iterator<string, array{string, string}> */
    public static function literalCalls(): Iterator
    {
        yield 'core fallback' => ["view('/base.html.twig')", '@Pushword/base.html.twig'];
        yield 'path normalization' => ["view('base.html.twig')", '@Pushword/base.html.twig'];
        yield 'explicit namespace' => ["view('@Other/base.html.twig')", '@Other/base.html.twig'];
        yield 'fallback namespace' => ["view('/base.html.twig', '@Other')", '@Other/base.html.twig'];
        yield 'null fallback' => ["view('/base.html.twig', null)", '@Pushword/base.html.twig'];
        yield 'named path' => ["view(path: '/base.html.twig')", '@Pushword/base.html.twig'];
        yield 'named arguments in reverse order' => ["view(fallback: '@Other', path: '/base.html.twig')", '@Other/base.html.twig'];
        yield 'named null fallback' => ["view(path: '/base.html.twig', fallback: null)", '@Pushword/base.html.twig'];
    }

    public function testUsesTheSelectedSitesOverridesWithoutLeakingAcrossHosts(): void
    {
        $this->filesystem->dumpFile($this->directory.'/templates/site.test/base.html.twig', '{% block content %}{% endblock %}');
        $source = new Source("{% extends view('/base.html.twig') %}", 'child.html.twig');
        $first = $this->createTwig('site.test');
        $second = $this->createTwig('other.test');

        self::assertSame('/site.test/base.html.twig', $first->parse($first->tokenize($source))->getNode('parent')->getAttribute('value'));
        self::assertSame('@Pushword/base.html.twig', $second->parse($second->tokenize($source))->getNode('parent')->getAttribute('value'));
    }

    public function testUsesTheSitesThemeBeforeTheFallback(): void
    {
        $this->filesystem->dumpFile($this->directory.'/theme/base.html.twig', '{% block content %}{% endblock %}');
        $twig = $this->createTwig('site.test');
        $template = $twig->parse($twig->tokenize(new Source("{% extends view('/base.html.twig', '@Other') %}", 'child.html.twig')));

        self::assertSame('@Theme/base.html.twig', $template->getNode('parent')->getAttribute('value'));
    }

    #[DataProvider('unresolvedCalls')]
    public function testLeavesDynamicAndInvalidCallsAvailableToTheAnalyzer(string $call): void
    {
        $twig = $this->createTwig('site.test');
        $template = $twig->parse($twig->tokenize(new Source('{% extends '.$call.' %}', 'child.html.twig')));

        self::assertInstanceOf(FunctionExpression::class, $template->getNode('parent'));
    }

    /** @return Iterator<string, array{string}> */
    public static function unresolvedCalls(): Iterator
    {
        yield 'unrelated function' => ["include('/base.html.twig')"];
        yield 'dynamic path' => ['view(templateName)'];
        yield 'dynamic fallback' => ["view('/base.html.twig', fallbackNamespace)"];
        yield 'missing path' => ['view()'];
        yield 'invalid path type' => ['view(null)'];
        yield 'invalid fallback type' => ["view('/base.html.twig', 123)"];
        yield 'unknown named argument' => ["view('/base.html.twig', typo: '@Other')"];
        yield 'duplicate path argument' => ["view('/base.html.twig', path: '/other.html.twig')"];
        yield 'extra positional argument' => ["view('/base.html.twig', '@Other', 'extra')"];
    }

    #[DataProvider('includes')]
    public function testResolvesIncludesAndPreservesRenderedBlockContext(string $include): void
    {
        $this->filesystem->dumpFile($this->directory.'/core/part.html.twig', '{{ title }}');
        $this->filesystem->dumpFile($this->directory.'/templates/child.html.twig', "{% extends view('/base.html.twig') %}{% block content %}".$include.'{% endblock %}');

        $twig = $this->createTwig('site.test');

        self::assertSame('From the controller', $twig->render('child.html.twig', ['title' => 'From the controller']));
    }

    /** @return Iterator<string, array{string}> */
    public static function includes(): Iterator
    {
        yield 'include tag' => ["{% include view('/part.html.twig') %}"];
        yield 'include function' => ["{{ include(view('/part.html.twig')) }}"];
    }

    private function createTwig(string $host): Environment
    {
        $loader = new FilesystemLoader($this->directory.'/templates');
        $loader->addPath($this->directory.'/theme', 'Theme');
        $loader->addPath($this->directory.'/core', 'Pushword');
        $loader->addPath($this->directory.'/other', 'Other');

        $twig = new Environment($loader, ['strict_variables' => true]);
        $site = new SiteConfig(new ParameterBag(), [
            'hosts' => [$host],
            'template' => '@Theme',
            'template_dir' => $this->directory.'/templates',
        ], true);
        $site->setTemplateResolver(new TemplateResolver($twig, new ArrayAdapter()));

        $twig->addFunction(new TwigFunction('view', static fn (string $path, ?string $fallback = null): string => $site->getView($path, $fallback ?? '@Pushword')));
        $twig->addNodeVisitor(new ResolveViewNodeVisitor($site));

        return $twig;
    }
}
