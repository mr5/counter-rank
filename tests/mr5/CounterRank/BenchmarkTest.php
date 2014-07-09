<?php

namespace mr5\CounterRank;

// +----------------------------------------------------------------------
// | [counter-rank]
// +----------------------------------------------------------------------
// | Author: Mr.5 <mr5.simple@gmail.com>
// +----------------------------------------------------------------------
// + Datetime: 14-7-9 13:30
// +----------------------------------------------------------------------
// + BenchmarkTest.php 性能基准测试
// +----------------------------------------------------------------------

class BenchmarkTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \mr5\counterRank\CounterRank
     */
    private $counterRank = null;
    private $groupName = 'CounterAndRankTest';

    protected function setUp()
    {
        $this->counterRank = new CounterRank(REDIS_SERVER_HOST, REDIS_SERVER_PORT, REDIS_NAMESPACE, $this->groupName);
    }
    public function testBenchmark()
    {
        set_time_limit(6000);
        $startTime = microtime(true);

        for($i=1; $i<50000; $i++) {
            $this->counterRank->create('item'.$i, 0);
        }
        $this->assertLessThan(0.9, microtime(true) - $startTime);
    }
    protected function tearDown()
    {
        $this->counterRank->deleteGroup($this->groupName);
    }
}