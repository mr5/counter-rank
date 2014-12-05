<?php

namespace mr5\CounterRank;

// +----------------------------------------------------------------------
// | [counter-rank]
// +----------------------------------------------------------------------
// | Author: Mr.5 <mr5.simple@gmail.com>
// +----------------------------------------------------------------------
// + Datetime: 14-8-25 11:18
// +----------------------------------------------------------------------

/**
 * 统计数据迭代器
 *
 * Class CounterIterator
 * @package mr5\CounterRank
 */
class CounterIterator implements \Iterator
{
    /**
     * 持久化后不执行任何操作
     */
    const PERSIST_WITH_NOTHING = 0;
    /**
     * 持久化后删除 item
     */
    const PERSIST_WITH_DELETING = 1;
    /**
     * 持久化后清零 item ，但保留 item ，该模式相对于直接删除会更加安全
     */
    const PERSIST_WITH_CLEARING = 2;
    /**
     * @var int 当前位置
     */
    private $position = 0;
    /**
     * @var float|int 结束位置
     */
    private $endPosition = 0;
    /**
     * @var int 每次迭代的个数
     */
    private $perSize = 100;
    /**
     * @var int 每次迭代完毕的后置操作
     */
    private $opAfter = self::PERSIST_WITH_NOTHING;
    /**
     * @var string 分组名
     */
    private $groupName;
    /**
     * @var \Redis
     */
    private $redis;

    public function __construct(\Redis $redis, $groupName, $perSize = 100, $opAfter = self::PERSIST_WITH_NOTHING)
    {
        $this->redis = $redis;
        $this->groupName = $groupName;
        $this->perSize = $perSize;
        $this->opAfter = $opAfter;
        $total = $this->redis->zCard($this->groupName);
        $this->endPosition = ceil($total / $perSize) - 1;


    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     */
    public function current()
    {
        $start = $this->position * $this->perSize;

        $end = $start + $this->perSize - 1;

        $items = $this->redis->zRevRange($this->groupName, $start, $end, true);
        // 删除
        if ($this->opAfter === self::PERSIST_WITH_DELETING) {
            if ($items) {
                $params = array_merge(array($this->groupName), array_keys($items));
                call_user_func_array(array($this->redis, 'zRem'), $params);
                --$this->endPosition;
                --$this->position;
            }

        } elseif ($this->opAfter === self::PERSIST_WITH_CLEARING) {
            if ($items) {
                --$this->endPosition;
                --$this->position;
                foreach ($items as $key => $val) {
                    if ($val == 0) {
                        unset($items[$key]);
                        break;
                    } else {
                        // 减去取出时的值
                        $this->redis->zIncrBy($this->groupName, 0 - $val, $key);
                    }
                }
            }

        }

        return $items;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     */
    public function valid()
    {
        return $this->position <= $this->endPosition;
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     */
    public function rewind()
    {
        $this->position = 0;
    }
}
