<?php

namespace RedAnt\DBALSharding\ShardChooser;

use RedAnt\DBALSharding\PoolingShardConnection;

/**
 * The MultiTenant Shard chooser assumes that the distribution value directly
 * maps to the shard id.
 */
class MultiTenantShardChooser implements ShardChooser
{
    /**
     * {@inheritdoc}
     */
    public function pickShard($distributionValue, PoolingShardConnection $conn)
    {
        return $distributionValue;
    }
}
