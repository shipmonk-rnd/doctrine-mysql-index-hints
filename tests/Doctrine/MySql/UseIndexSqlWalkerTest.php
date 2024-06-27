<?php declare(strict_types = 1);

namespace ShipMonk\Doctrine\MySql;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Query;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ShipMonk\Doctrine\Walker\HintDrivenSqlWalker;
use stdClass;
use function sprintf;

class UseIndexSqlWalkerTest extends TestCase
{

    /**
     * @param callable(Query): void $configureQueryCallback
     */
    #[DataProvider('walksProvider')]
    public function testWalker(string $dql, callable $configureQueryCallback, ?string $expectedSql, ?string $expectedError = null): void
    {
        if ($expectedError !== null) {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessageMatches($expectedError);
        }

        $entityManagerMock = $this->createEntityManagerMock();

        $query = new Query($entityManagerMock);
        $query->setDQL($dql);

        $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, HintDrivenSqlWalker::class);
        $configureQueryCallback($query);
        $producedSql = $query->getSQL();

        self::assertSame($expectedSql, $producedSql);
    }

    /**
     * @return iterable<string, array{0: string, 1: callable(Query): void, 2?: string|null, 3?: string|null}>
     */
    public static function walksProvider(): iterable
    {
        $userSelectDql = sprintf('SELECT u FROM %s u', User::class);
        $userSubselectDql = sprintf('SELECT u FROM %s u WHERE u.id = (SELECT u2.id FROM %s u2 WHERE u2.id = 1)', User::class, User::class);

        yield 'FROM - use single index' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(UseIndexHintHandler::class, [IndexHint::use('IDX_FOO', User::TABLE_NAME)]);
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1 FROM user u0_ USE INDEX (IDX_FOO)',
        ];
        yield 'FROM - force single index' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(UseIndexHintHandler::class, [IndexHint::force('IDX_FOO', User::TABLE_NAME)]);
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1 FROM user u0_ FORCE INDEX (IDX_FOO)',
        ];
        yield 'FROM - ignore single index' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(UseIndexHintHandler::class, [IndexHint::ignore('IDX_FOO', User::TABLE_NAME)]);
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1 FROM user u0_ IGNORE INDEX (IDX_FOO)',
        ];
        yield 'FROM - use multiple indexes' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(UseIndexHintHandler::class, [
                    IndexHint::use('IDX_FOO', User::TABLE_NAME),
                    IndexHint::use('IDX_BAR', User::TABLE_NAME),
                ]);
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1 FROM user u0_ USE INDEX (IDX_FOO, IDX_BAR)',
        ];
        yield 'FROM - force multiple indexes' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(UseIndexHintHandler::class, [
                    IndexHint::force('IDX_FOO', User::TABLE_NAME),
                    IndexHint::force('IDX_BAR', User::TABLE_NAME),
                ]);
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1 FROM user u0_ FORCE INDEX (IDX_FOO, IDX_BAR)',
        ];
        yield 'FROM - ignore multiple indexes' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(UseIndexHintHandler::class, [
                    IndexHint::ignore('IDX_FOO', User::TABLE_NAME),
                    IndexHint::ignore('IDX_BAR', User::TABLE_NAME),
                ]);
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1 FROM user u0_ IGNORE INDEX (IDX_FOO, IDX_BAR)',
        ];

        $userSelectWithJoinsDql = sprintf('SELECT u FROM %s u JOIN u.account a JOIN u.managedAccounts ma', User::class);

        yield 'JOIN - one single use index' => [
            $userSelectWithJoinsDql,
            static function (Query $query): void {
                $query->setHint(
                    UseIndexHintHandler::class,
                    [IndexHint::use('IDX_FOO', Account::TABLE_NAME, 'a')],
                );
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1'
                . ' FROM user u0_'
                . ' INNER JOIN account a1_ USE INDEX (IDX_FOO) ON u0_.account_id = a1_.id'
                . ' INNER JOIN account a2_ ON u0_.id = a2_.manager_id',
        ];
        yield 'JOIN - combine use index with ignore index' => [
            $userSelectWithJoinsDql,
            static function (Query $query): void {
                $query->setHint(
                    UseIndexHintHandler::class,
                    [
                        IndexHint::use('IDX_FOO', Account::TABLE_NAME, 'a'),
                        IndexHint::ignore('IDX_BAR', Account::TABLE_NAME, 'a'),
                    ],
                );
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1'
                . ' FROM user u0_'
                . ' INNER JOIN account a1_ IGNORE INDEX (IDX_BAR) USE INDEX (IDX_FOO) ON u0_.account_id = a1_.id'
                . ' INNER JOIN account a2_ ON u0_.id = a2_.manager_id',
        ];
        yield 'JOIN - one multiple use indexes' => [
            $userSelectWithJoinsDql,
            static function (Query $query): void {
                $query->setHint(
                    UseIndexHintHandler::class,
                    [
                        IndexHint::use('IDX_FOO', Account::TABLE_NAME, 'a'),
                        IndexHint::use('IDX_BAR', Account::TABLE_NAME, 'a'),
                    ],
                );
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1'
                . ' FROM user u0_'
                . ' INNER JOIN account a1_ USE INDEX (IDX_FOO, IDX_BAR) ON u0_.account_id = a1_.id'
                . ' INNER JOIN account a2_ ON u0_.id = a2_.manager_id',
        ];

        yield 'JOIN combinations' => [
            $userSelectWithJoinsDql,
            static function (Query $query): void {
                $query->setHint(
                    UseIndexHintHandler::class,
                    [
                        IndexHint::use('IDX_FOO', Account::TABLE_NAME, 'a'),
                        IndexHint::use('IDX_BAR', Account::TABLE_NAME, 'ma'),
                        IndexHint::use('IDX_BAZ', Account::TABLE_NAME, 'ma'),
                    ],
                );
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1'
                . ' FROM user u0_'
                . ' INNER JOIN account a1_ USE INDEX (IDX_FOO) ON u0_.account_id = a1_.id'
                . ' INNER JOIN account a2_ USE INDEX (IDX_BAR, IDX_BAZ) ON u0_.id = a2_.manager_id',
        ];

        yield 'FROM and JOIN combination' => [
            $userSelectWithJoinsDql,
            static function (Query $query): void {
                $query->setHint(UseIndexHintHandler::class, [
                    IndexHint::use('IDX_FOO', User::TABLE_NAME),
                    IndexHint::use('IDX_BAR', Account::TABLE_NAME, 'a'),
                ]);
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1'
                . ' FROM user u0_ USE INDEX (IDX_FOO)'
                . ' INNER JOIN account a1_ USE INDEX (IDX_BAR) ON u0_.account_id = a1_.id'
                . ' INNER JOIN account a2_ ON u0_.id = a2_.manager_id',
        ];

        yield 'FROM in subselect' => [
            $userSubselectDql,
            static function (Query $query): void {
                $query->setHint(UseIndexHintHandler::class, [IndexHint::use('IDX_FOO', User::TABLE_NAME, 'u2')]);
            },
            'SELECT u0_.id AS id_0, u0_.account_id AS account_id_1 FROM user u0_ WHERE u0_.id = (SELECT u1_.id FROM user u1_ USE INDEX (IDX_FOO) WHERE u1_.id = 1)',
        ];

        yield 'no hint' => [
            $userSelectDql,
            static function (Query $query): void {
            },
            null,
            '~no HintHandler child was added as hint~',
        ];

        yield 'invalid table' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(
                    UseIndexHintHandler::class,
                    [
                        IndexHint::use('IDX_FOO', 'unknown_table'),
                    ],
                );
            },
            null,
            '~table unknown_table is not present in the query~',
        ];

        yield 'invalid alias' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(
                    UseIndexHintHandler::class,
                    [
                        IndexHint::use('IDX_FOO', User::TABLE_NAME, 'unknown_alias'),
                    ],
                );
            },
            null,
            '~table user with DQL alias unknown_alias is not present in the query~',
        ];

        yield 'invalid argument' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(
                    UseIndexHintHandler::class,
                    IndexHint::use('IDX_FOO', User::TABLE_NAME),
                );
            },
            null,
            '~expecting array of IndexHint objects~',
        ];

        yield 'invalid array item' => [
            $userSelectDql,
            static function (Query $query): void {
                $query->setHint(
                    UseIndexHintHandler::class,
                    [
                        new stdClass(),
                    ],
                );
            },
            null,
            '~expecting array of IndexHint objects, element #0 is stdClass~',
        ];

        yield 'missing table alias' => [
            $userSelectWithJoinsDql,
            static function (Query $query): void {
                $query->setHint(
                    UseIndexHintHandler::class,
                    [IndexHint::use('IDX_FOO', Account::TABLE_NAME)],
                );
            },
            null,
            '~table account is present multiple times in the query~',
        ];
    }

    private function createEntityManagerMock(): EntityManager
    {
        $config = new Configuration();
        $config->setProxyNamespace('Tmp\Doctrine\Tests\Proxies');
        $config->setProxyDir('/tmp/doctrine');
        $config->setAutoGenerateProxyClasses(false);
        $config->setSecondLevelCacheEnabled(false);
        $config->setMetadataDriverImpl(new AttributeDriver([__DIR__]));

        $eventManager = $this->createMock(EventManager::class);
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->method('getEventManager')
            ->willReturn($eventManager);

        $connectionMock->method('getDatabasePlatform')
            ->willReturn(new MySQL80Platform());

        return new EntityManager(
            $connectionMock,
            $config,
            $eventManager,
        );
    }

}
