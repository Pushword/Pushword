<?php

namespace Pushword\Core\Component\Filter;

use Knp\Bundle\MarkdownBundle\MarkdownParserInterface;
use Knp\Bundle\MarkdownBundle\Parser\MarkdownParser as KnpMarkdownParser;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;

class MarkdownParser implements MarkdownParserInterface
{
    protected Environment $env;

    protected $features = [
        'header' => true,
        'list' => true,
        'horizontal_rule' => true,
        'table' => true,
        'foot_note' => true,
        'fenced_code_block' => true,
        'abbreviation' => true,
        'definition_list' => true,
        'inline_link' => true, // [link text](url "optional title")
        'reference_link' => true, // [link text] [id]
        'shortcut_link' => true, // [link text]
        'images' => true,
        'block_quote' => true,
        'code_block' => false, // break everything witht twig includes
        'html_block' => true,
        'auto_link' => true,
        'auto_mailto' => true,
        'entities' => true,
        'no_html' => false,
    ];

    protected $converter;
    protected array $config;

    public function __construct()
    {
        $this->env = Environment::createCommonMarkEnvironment();
        $this->env->addExtension(new AttributesExtension());
        $this->env->addExtension(new TableExtension());
        $this->env->addExtension(new SmartPunctExtension());
        $this->env->addExtension(new StrikethroughExtension());


        $this->config = [];
    }

    public function getConverter()
    {
        if (!$this->converter)
            $this->converter = new CommonMarkConverter($this->config, $this->env);

            return $this->converter;

    }

    public function transformMarkdown($text)
    {
        return $this->getConverter()->convertToHtml($text);
    }
}
