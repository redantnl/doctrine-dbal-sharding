<?php

namespace Doctrine\Tests\DBAL\Sharding;

use RedAnt\DBALSharding\PoolingShardConnection;
use RedAnt\DBALSharding\PoolingShardManager;
use RedAnt\DBALSharding\ShardChooser\ShardChooser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PoolingShardManagerTest extends TestCase
{
    /**
     * @return PoolingShardConnection&MockObject
     */
    private function createConnectionMock(): PoolingShardConnection
    {
        return $this->getMockBuilder(PoolingShardConnection::class)
            ->onlyMethods(['connect', 'getParams', 'fetchAllAssociative'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createPassthroughShardChooser(): ShardChooser
    {
        $mock = $this->createMock(ShardChooser::class);
        $mock->expects($this->any())
            ->method('pickShard')
            ->will($this->returnCallback(static function ($value) {
                return $value;
            }));

        return $mock;
    }

    private function createStaticShardChooser(): ShardChooser
    {
        $mock = $this->createMock(ShardChooser::class);
        $mock->expects($this->any())
            ->method('pickShard')
            ->willReturn(1);

        return $mock;
    }

    public function testSelectGlobal(): void
    {
        $conn = $this->createConnectionMock();
        $conn->expects($this->once())->method('connect')->with($this->equalTo(0));
        $conn->method('getParams')
            ->willReturn([
                'shardChooser' => $this->createMock(ShardChooser::class),
            ]);

        $shardManager = new PoolingShardManager($conn);
        $shardManager->selectGlobal();

        self::assertNull($shardManager->getCurrentDistributionValue());
    }

    public function testSelectShard(): void
    {
        $shardId = 10;
        $conn    = $this->createConnectionMock();

        $conn->method('getParams')
            ->willReturn(['shardChooser' => $this->createPassthroughShardChooser()]);

        $conn->method('connect')
            ->with($shardId);

        $shardManager = new PoolingShardManager($conn);
        $shardManager->selectShard($shardId);

        self::assertEquals($shardId, $shardManager->getCurrentDistributionValue());
    }

    public function testGetShards(): void
    {
        $conn = $this->createConnectionMock();
        $conn->expects($this->any())->method('getParams')->willReturn(
            ['shards' => [['id' => 1], ['id' => 2]], 'shardChooser' => $this->createPassthroughShardChooser()]
        );

        $shardManager = new PoolingShardManager($conn);
        $shards       = $shardManager->getShards();

        self::assertEquals([['id' => 1], ['id' => 2]], $shards);
    }

    public function testQueryAll(): void
    {
        $sql    = 'SELECT * FROM table';
        $params = [1];
        $types  = [1];

        $conn = $this->createConnectionMock();

        $conn->method('getParams')->willReturn([
            'shards' => [
                ['id' => 1],
                ['id' => 2],
            ],
            'shardChooser' => $this->createPassthroughShardChooser(),
        ]);

        $conn->method('connect')
            ->withConsecutive([1], [2]);

        $conn->method('fetchAllAssociative')
            ->with($sql, $params, $types)
            ->willReturnOnConsecutiveCalls(
                [['id' => 1]],
                [['id' => 2]]
            );

        $shardManager = new PoolingShardManager($conn);
        $result       = $shardManager->queryAll($sql, $params, $types);

        self::assertEquals([['id' => 1], ['id' => 2]], $result);
    }

    public function testQueryAllWithStaticShardChooser(): void
    {
        $sql    = 'SELECT * FROM table';
        $params = [1];
        $types  = [1];

        $conn = $this->createConnectionMock();

        $conn->method('getParams')->willReturn([
            'shards' => [
                ['id' => 1],
                ['id' => 2],
            ],
            'shardChooser' => $this->createStaticShardChooser(),
        ]);

        $conn->method('connect')
            ->withConsecutive([1], [2]);

        $conn->method('fetchAllAssociative')
            ->with($sql, $params, $types)
            ->willReturnOnConsecutiveCalls(
                [['id' => 1]],
                [['id' => 2]]
            );

        $shardManager = new PoolingShardManager($conn);
        $result       = $shardManager->queryAll($sql, $params, $types);

        self::assertEquals([['id' => 1], ['id' => 2]], $result);
    }
}