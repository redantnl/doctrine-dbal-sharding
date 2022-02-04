<?php

namespace Doctrine\Tests\DBAL\Sharding\ShardChooser;

use RedAnt\DBALSharding\PoolingShardConnection;
use RedAnt\DBALSharding\ShardChooser\MultiTenantShardChooser;
use PHPUnit\Framework\TestCase;

class MultiTenantShardChooserTest extends TestCase
{
    public function testPickShard(): void
    {
        $choser = new MultiTenantShardChooser();
        $conn   = $this->createConnectionMock();

        self::assertEquals(1, $choser->pickShard(1, $conn));
        self::assertEquals(2, $choser->pickShard(2, $conn));
    }

    private function createConnectionMock(): PoolingShardConnection
    {
        return $this->getMockBuilder(PoolingShardConnection::class)
            ->onlyMethods(['connect', 'getParams', 'fetchAllAssociative'])
            ->disableOriginalConstructor()
            ->getMock();
    }
}