<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

class IndexHint
{

    private string $type;

    private string $indexName;

    private string $tableName;

    private ?string $dqlAlias;

    private function __construct(
        string $type,
        string $indexName,
        string $tableName,
        ?string $dqlAlias,
    )
    {
        $this->type = $type;
        $this->indexName = $indexName;
        $this->tableName = $tableName;
        $this->dqlAlias = $dqlAlias;
    }

    public static function use(
        string $indexName,
        string $tableName,
        ?string $dqlAlias = null,
    ): self
    {
        return new self('USE INDEX', $indexName, $tableName, $dqlAlias);
    }

    public static function force(
        string $indexName,
        string $tableName,
        ?string $dqlAlias = null,
    ): self
    {
        return new self('FORCE INDEX', $indexName, $tableName, $dqlAlias);
    }

    public static function ignore(
        string $indexName,
        string $tableName,
        ?string $dqlAlias = null,
    ): self
    {
        return new self('IGNORE INDEX', $indexName, $tableName, $dqlAlias);
    }

    public static function forceForJoin(
        string $indexName,
        string $tableName,
        ?string $dqlAlias = null,
    ): self
    {
        return new self('FORCE INDEX FOR JOIN', $indexName, $tableName, $dqlAlias);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIndexName(): string
    {
        return $this->indexName;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getDqlAlias(): ?string
    {
        return $this->dqlAlias;
    }

}
