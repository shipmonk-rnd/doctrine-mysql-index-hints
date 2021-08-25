<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\ORM\Query\AST\FromClause;
use Doctrine\ORM\Query\AST\SelectStatement;
use Doctrine\ORM\Query\SqlWalker;
use LogicException;
use Nette\Utils\Strings;
use function count;
use function get_class;
use function gettype;
use function implode;
use function is_array;
use function is_object;

class UseIndexSqlWalker extends SqlWalker
{

    /**
     * @param FromClause $fromClause
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function walkFromClause($fromClause): string
    {
        $selfClass = get_class($this);
        $query = $this->getQuery();
        $platform = $query->getEntityManager()->getConnection()->getDatabasePlatform();

        $sql = parent::walkFromClause($fromClause);

        if (!$platform instanceof MySqlPlatform) {
            throw new LogicException("Only MySQL platform is supported, {$platform->getName()} given");
        }

        if (!$query->hasHint(self::class)) {
            throw new LogicException("{$selfClass} was used, but no index hint was added. Add ->setHint({$selfClass}::class, [IndexHint::use('index_name', 'table_name')])");
        }

        if (!$query->getAST() instanceof SelectStatement) {
            throw new LogicException("Only SELECT queries are currently supported by {$selfClass}");
        }

        $hints = $query->getHint(self::class);

        if (!is_array($hints)) {
            $type = is_object($hints) ? get_class($hints) : gettype($hints);
            throw new LogicException("Unexpected hint, expecting array of IndexHint objects, {$type} given");
        }

        /** @var array<string, array<string, string[]>> $replacements */
        $replacements = [];

        foreach ($hints as $index => $hint) {
            if (!$hint instanceof IndexHint) {
                $type = is_object($hint) ? get_class($hint) : gettype($hint);
                throw new LogicException("Unexpected hint, expecting array of IndexHint objects, element #{$index} is {$type}");
            }

            $tableName = $hint->getTableName();
            $tableAlias = $hint->getDqlAlias() !== null
                ? $this->getSQLTableAlias($hint->getTableName(), $hint->getDqlAlias())
                : '\S+'; // doctrine always adds some alias
            $tableWithAliasRegex = "~{$tableName}\s+{$tableAlias}~i";

            if (Strings::match($sql, $tableWithAliasRegex) === null) {
                $aliasInfo = $hint->getDqlAlias() !== null ? " with DQL alias {$hint->getDqlAlias()}" : '';
                throw new LogicException("Invalid hint for index {$hint->getIndexName()}, table {$tableName}{$aliasInfo} is not present in the query.");
            }

            if ($hint->getDqlAlias() === null && count(Strings::matchAll($sql, $tableWithAliasRegex)) !== 1) {
                throw new LogicException("Invalid hint for index {$hint->getIndexName()}, table {$tableName} is present multiple times in the query, please specify DQL alias to apply index on a proper place.");
            }

            $replacements[$tableWithAliasRegex][$hint->getType()][] = $hint->getIndexName();
        }

        foreach ($replacements as $tableRegex => $indexHints) {
            foreach ($indexHints as $indexType => $indexNames) {
                $indexList = implode(', ', $indexNames);
                $sql = Strings::replace($sql, $tableRegex, "\\0 {$indexType} INDEX ({$indexList})");
            }
        }

        return $sql;
    }

}
