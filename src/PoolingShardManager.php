<?php

namespace RedAnt\DBALSharding;

use RedAnt\DBALSharding\ShardChooser\ShardChooser;
use RuntimeException;

/**
 * Shard Manager for the Connection Pooling Shard Strategy
 */
class PoolingShardManager implements ShardManager
{
    /** @var PoolingShardConnection */
    private $connection;

    /** @var ShardChooser */
    private $chooser;

    /** @var string|null */
    private $currentDistributionValue;

    public function __construct(PoolingShardConnection $conn)
    {
        $params = $conn->getParams();
        $this->connection = $conn;
        $this->chooser = $params['shardChooser'];
    }

    /**
     * {@inheritDoc}
     */
    public function selectGlobal()
    {
        $this->connection->connect(0);
        $this->currentDistributionValue = null;
    }

    /**
     * {@inheritDoc}
     */
    public function selectShard($distributionValue)
    {
        $shardId = $this->chooser->pickShard($distributionValue, $this->connection);
        $this->connection->connect($shardId);
        $this->currentDistributionValue = $distributionValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getCurrentDistributionValue()
    {
        return $this->currentDistributionValue;
    }

    /**
     * {@inheritDoc}
     */
    public function getShards()
    {
        $params = $this->connection->getParams();
        $shards = [];

        foreach ($params['shards'] as $shard) {
            $shards[] = [ 'id' => $shard['id'] ];
        }

        return $shards;
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException
     */
    public function queryAll($sql, array $params, array $types)
    {
        $shards = $this->getShards();
        if (!$shards) {
            throw new RuntimeException('No shards found.');
        }

        $result = [];
        $oldDistribution = $this->getCurrentDistributionValue();

        foreach ($shards as $shard) {
            $this->connection->connect($shard['id']);
            foreach ($this->connection->fetchAllAssociative($sql, $params, $types) as $row) {
                $result[] = $row;
            }
        }

        if ($oldDistribution === null) {
            $this->selectGlobal();
        } else {
            $this->selectShard($oldDistribution);
        }

        return $result;
    }
}
