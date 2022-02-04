<?php

namespace RedAnt\DBALSharding\ShardChooser;

use RedAnt\DBALSharding\PoolingShardConnection;

/**
 * Given a distribution value this shard-chooser strategy will pick the shard to
 * connect to for retrieving rows with the distribution value.
 */
interface ShardChooser
{
    /**
     * Picks a shard for the given distribution value.
     *
     * @param string|int $distributionValue
     *
     * @return string|int
     */
    public function pickShard($distributionValue, PoolingShardConnection $conn);
}
