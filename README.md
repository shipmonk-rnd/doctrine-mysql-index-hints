## MySQL index hints for Doctrine

This library provides a simple way to incorporate [MySQL's index hints](https://dev.mysql.com/doc/refman/8.0/en/index-hints.html)
into SELECT queries written in [Doctrine Query Language](https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/reference/dql-doctrine-query-language.html)
via [custom SqlWalker](https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/cookbook/dql-custom-walkers.html#modify-the-output-walker-to-generate-vendor-specific-sql).
No need for native queries anymore.

### Installation:

```sh
composer require shipmonk/doctrine-mysql-index-hints
```

### Simple usage:

```php
$result = $em->createQueryBuilder()
    ->select('u.id')
    ->from(User::class, 'u')
    ->andWhere('u.id = 1')
    ->getQuery()
    ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
    ->setHint(UseIndexHintHandler::class, [IndexHint::force(User::IDX_FOO, User::TABLE_NAME)])
    ->getResult();
```

Which produces following SQL:

```mysql
SELECT u0_.id AS id_0
FROM user u0_ FORCE INDEX (IDX_FOO)
WHERE u0_.id = 1
```

See the used entity (it makes sense to put table names and index names into public constants to bind it together and reference it easily):

```php
#[ORM\Table(name: self::TABLE_NAME)]
#[ORM\Index(name: self::IDX_FOO, columns: ['id'])]
#[ORM\Entity]
class User
{
    public const TABLE_NAME = 'user';
    public const IDX_FOO = 'IDX_FOO';

    // ...
}
```

### Combining multiple hints:

You might need to give MySQL a list of possible indexes or hint it not to use some indices.
As you can see, hinting joined tables is equally simple.

```php
->from(User::class, 'u')
->join('u.account', 'a')
->getQuery()
->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
->setHint(UseIndexHintHandler::class, [
    IndexHint::use(Account::IDX_1, Account::TABLE_NAME),
    IndexHint::use(Account::IDX_2, Account::TABLE_NAME),
    IndexHint::ignore(Account::IDX_3, Account::TABLE_NAME),
    IndexHint::ignore(Account::IDX_4, Account::TABLE_NAME),
])
```

Produces this SQL:

```mysql
FROM user u0_
JOIN account a1_ IGNORE INDEX (IDX_3, IDX_4) USE INDEX (IDX_1, IDX_2) ON (...)
```

### Hinting table joined multiple times:

You might need to hint only specific join of certain table. Just add which DQL alias specifies it as third argument.

```php
->from(User::class, 'u')
->join('u.account', 'a1')
->join('u.anotherAccount', 'a2')
->getQuery()
->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
->setHint(UseIndexHintHandler::class, [
    IndexHint::use(Account::IDX_1, Account::TABLE_NAME, 'a1'), // alias needed
])
```

Produces this SQL:

```mysql
FROM user u0_
JOIN account a1_ USE INDEX (IDX_1) ON (...)
JOIN account a2_ ON (...)
```

### Advanced usage notes
- It works even for tables that are not present in the DQL, but are present in SQL!
    - For example parent table from [class table inheritance](https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/reference/inheritance-mapping.html#class-table-inheritance) when selecting children
- Any invalid usage is checked in runtime
    - Table name existence is checked, so you just cannot swap `tableName` and `indexName` parameters by accident or use non-existing DQL alias
    - Forgotten hint or invalid arguments are also checked
    - Since those checks cannot be caught by any static analysis tool, it is recommended to have a test for every query

### Combining with optimizer hints:

Since 3.0.0, you can combine this library with [shipmonk/doctrine-mysql-optimizer-hints](https://github.com/shipmonk-rnd/doctrine-mysql-optimizer-hints):

```php
$result = $em->createQueryBuilder()
    ->select('u.id')
    ->from(User::class, 'u')
    ->andWhere('u.id = 1')
    ->getQuery()
    ->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class)
    ->setHint(OptimizerHintsHintHandler::class, ['MAX_EXECUTION_TIME(1000)'])
    ->setHint(UseIndexHintHandler::class, [IndexHint::force(User::IDX_FOO, User::TABLE_NAME)])
    ->getResult();
```

### Versions
- 1.x requires PHP >= 7.1
- 2.x requires PHP >= 7.4
- 3.x requires PHP >= 7.4
