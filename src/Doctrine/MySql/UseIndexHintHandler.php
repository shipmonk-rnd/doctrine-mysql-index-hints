<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\ORM\Query\AST\SelectStatement;
use LogicException;
use ShipMonk\Doctrine\Walker\HintHandler;
use ShipMonk\Doctrine\Walker\SqlNode;
use function gettype;
use function implode;
use function is_a;
use function is_array;
use function is_object;
use function preg_last_error;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function preg_replace;
use function sprintf;

class UseIndexHintHandler extends HintHandler
{

    /**
     * @return list<SqlNode::*>
     */
    public function getNodes(): array
    {
        return [SqlNode::SelectStatement, SqlNode::UpdateStatement, SqlNode::DeleteStatement];
    }

    public function processNode(SqlNode $sqlNode, string $sql): string
    {
        $selfClass = static::class;
        $sqlWalker = $this->getDoctrineSqlWalker();
        $query = $sqlWalker->getQuery();
        $platform = $query->getEntityManager()->getConnection()->getDatabasePlatform();

        if (!is_a($platform, 'Doctrine\DBAL\Platforms\AbstractMySQLPlatform')) {
            throw new LogicException(sprintf('Only MySQL platform is supported, %s given', $platform::class));
        }

        if (!$query->getAST() instanceof SelectStatement) {
            throw new LogicException("Only SELECT queries are currently supported by {$selfClass}");
        }

        $hints = $this->getHintValue();

        if (!is_array($hints)) {
            $type = is_object($hints) ? $hints::class : gettype($hints);
            throw new LogicException("Unexpected hint, expecting array of IndexHint objects, {$type} given");
        }

        /** @var array<string, array<string, string[]>> $replacements */
        $replacements = [];

        foreach ($hints as $index => $hint) {
            if (!$hint instanceof IndexHint) {
                $type = is_object($hint) ? $hint::class : gettype($hint);
                throw new LogicException("Unexpected hint, expecting array of IndexHint objects, element #{$index} is {$type}");
            }

            $delimiter = '~';
            $tableName = preg_quote($hint->getTableName(), $delimiter);
            $tableAlias = $hint->getDqlAlias() !== null
                ? preg_quote($sqlWalker->getSQLTableAlias($hint->getTableName(), $hint->getDqlAlias()), $delimiter)
                : '\S+'; // doctrine always adds some alias
            $tableWithAliasRegex = "{$delimiter}{$tableName}\s+{$tableAlias}{$delimiter}i";

            if (preg_match($tableWithAliasRegex, $sql) === 0) {
                $aliasInfo = $hint->getDqlAlias() !== null ? " with DQL alias {$hint->getDqlAlias()}" : '';
                throw new LogicException("Invalid hint for index {$hint->getIndexName()}, table {$tableName}{$aliasInfo} is not present in the query.");
            }

            if ($hint->getDqlAlias() === null && preg_match_all($tableWithAliasRegex, $sql) !== 1) {
                throw new LogicException("Invalid hint for index {$hint->getIndexName()}, table {$tableName} is present multiple times in the query, please specify DQL alias to apply index on a proper place.");
            }

            $replacements[$tableWithAliasRegex][$hint->getType()][] = $hint->getIndexName();
        }

        foreach ($replacements as $tableRegex => $indexHints) {
            foreach ($indexHints as $indexType => $indexNames) {
                $indexList = implode(', ', $indexNames);
                $sqlWithIndexHints = preg_replace($tableRegex, "\\0 {$indexType} INDEX ({$indexList})", $sql);

                if ($sqlWithIndexHints === null) {
                    throw new LogicException('Regex replace failure: ' . preg_last_error());
                }

                $sql = $sqlWithIndexHints;
            }
        }

        return $sql;
    }

}
