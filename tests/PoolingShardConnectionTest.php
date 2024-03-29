<?php

namespace Doctrine\Tests\DBAL\Sharding;

use Doctrine\DBAL\DriverManager;
use RedAnt\DBALSharding\PoolingShardConnection;
use RedAnt\DBALSharding\ShardChooser\MultiTenantShardChooser;
use RedAnt\DBALSharding\ShardingException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @requires extension pdo_sqlite
 */
class PoolingShardConnectionTest extends TestCase
{
    public function testConnect(): void
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);

        self::assertFalse($conn->isConnected(0));
        $conn->connect(0);
        self::assertEquals(1, $conn->fetchOne('SELECT 1'));
        self::assertTrue($conn->isConnected(0));

        self::assertFalse($conn->isConnected(1));
        $conn->connect(1);
        self::assertEquals(1, $conn->fetchOne('SELECT 1'));
        self::assertTrue($conn->isConnected(1));

        self::assertFalse($conn->isConnected(2));
        $conn->connect(2);
        self::assertEquals(1, $conn->fetchOne('SELECT 1'));
        self::assertTrue($conn->isConnected(2));

        $conn->close();
        self::assertFalse($conn->isConnected(0));
        self::assertFalse($conn->isConnected(1));
        self::assertFalse($conn->isConnected(2));
    }

    public function testNoGlobalServerException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Connection Parameters require 'global' and 'shards' configurations.");

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);
    }

    public function testNoShardsServersException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Connection Parameters require 'global' and 'shards' configurations.");

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);
    }

    public function testNoShardsChooserException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing Shard Chooser configuration 'shardChooser'");

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
        ]);
    }

    public function testShardChooserWrongInstance(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "The 'shardChooser' configuration is not a valid instance of RedAnt\DBALSharding\ShardChooser\ShardChooser"
        );

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 2, 'memory' => true],
            ],
            'shardChooser' => new stdClass(),
        ]);
    }

    public function testShardNonNumericId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Shard Id has to be a non-negative number.');

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 'foo', 'memory' => true],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);
    }

    public function testShardMissingId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Missing 'id' for one configured shard. Please specify a unique shard-id.");

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['memory' => true],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);
    }

    public function testDuplicateShardId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Shard 1 is duplicated in the configuration.');

        DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
                ['id' => 1, 'memory' => true],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);
    }

    public function testSwitchShardWithOpenTransactionException(): void
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);

        $conn->beginTransaction();

        $this->expectException(ShardingException::class);
        $this->expectExceptionMessage('Cannot switch shard when transaction is active.');
        $conn->connect(1);
    }

    public function testGetActiveShardId(): void
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);

        self::assertNull($conn->getActiveShardId());

        $conn->connect(0);
        self::assertEquals(0, $conn->getActiveShardId());

        $conn->connect(1);
        self::assertEquals(1, $conn->getActiveShardId());

        $conn->close();
        self::assertNull($conn->getActiveShardId());
    }

    public function testGetParamsOverride(): void
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true, 'host' => 'localhost'],
            'shards' => [
                ['id' => 1, 'memory' => true, 'host' => 'foo'],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);

        self::assertEquals([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true, 'host' => 'localhost'],
            'shards' => [
                ['id' => 1, 'memory' => true, 'host' => 'foo'],
            ],
            'shardChooser' => new MultiTenantShardChooser(),
            'memory' => true,
            'host' => 'localhost',
        ], $conn->getParams());

        $conn->connect(1);
        self::assertEquals([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'global' => ['memory' => true, 'host' => 'localhost'],
            'shards' => [
                ['id' => 1, 'memory' => true, 'host' => 'foo'],
            ],
            'shardChooser' => new MultiTenantShardChooser(),
            'id' => 1,
            'memory' => true,
            'host' => 'foo',
        ], $conn->getParams());
    }

    public function testGetHostOverride(): void
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'host' => 'localhost',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true, 'host' => 'foo'],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);

        self::assertEquals('localhost', $conn->getHost());

        $conn->connect(1);
        self::assertEquals('foo', $conn->getHost());
    }

    public function testGetPortOverride(): void
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'port' => 3306,
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true, 'port' => 3307],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);

        self::assertEquals(3306, $conn->getPort());

        $conn->connect(1);
        self::assertEquals(3307, $conn->getPort());
    }

    public function testGetUsernameOverride(): void
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'user' => 'foo',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true, 'user' => 'bar'],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);

        self::assertEquals('foo', $conn->getUsername());

        $conn->connect(1);
        self::assertEquals('bar', $conn->getUsername());
    }

    public function testGetPasswordOverride(): void
    {
        $conn = DriverManager::getConnection([
            'wrapperClass' => PoolingShardConnection::class,
            'driver' => 'pdo_sqlite',
            'password' => 'foo',
            'global' => ['memory' => true],
            'shards' => [
                ['id' => 1, 'memory' => true, 'password' => 'bar'],
            ],
            'shardChooser' => MultiTenantShardChooser::class,
        ]);

        self::assertEquals('foo', $conn->getPassword());

        $conn->connect(1);
        self::assertEquals('bar', $conn->getPassword());
    }
}