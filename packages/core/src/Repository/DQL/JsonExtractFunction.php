<?php

declare(strict_types=1);

namespace Pushword\Core\Repository\DQL;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;
use Override;

class JsonExtractFunction extends FunctionNode
{
    private Node|string $column;

    private Node $path;

    #[Override]
    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->column = $parser->ArithmeticPrimary();
        $parser->match(TokenType::T_COMMA);
        $this->path = $parser->StringPrimary();
        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    #[Override]
    public function getSql(SqlWalker $sqlWalker): string
    {
        $column = $this->column instanceof Node ? $this->column->dispatch($sqlWalker) : $this->column;
        $path = $this->path->dispatch($sqlWalker);

        if ($sqlWalker->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            return \sprintf(
                "JSON_EXTRACT_PATH(%s, VARIADIC STRING_TO_ARRAY(SUBSTRING(%s FROM 3), '.'))",
                $column,
                $path,
            );
        }

        return \sprintf('JSON_EXTRACT(%s, %s)', $column, $path);
    }
}
