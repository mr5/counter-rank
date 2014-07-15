<?php

namespace mr5\CounterRank;

// +----------------------------------------------------------------------
// | [counter-rank]
// +----------------------------------------------------------------------
// | Author: Mr.5 <mr5.simple@gmail.com>
// +----------------------------------------------------------------------
// + Datetime: 14-7-3 下午4:25
// +----------------------------------------------------------------------
// + CounterRank.php  计数与排名，主要是对 redis 一系列的方法进行了封装
// +----------------------------------------------------------------------
/**
 * 计数与排名，主要是对 redis zset 一系列的方法进行了封装
 *
 * @example
 * ``
 * ```php
 *
 * // 命名空间 namespace 用于区分不同的项目。分组名是一类 items 的分组。比如要统计的是文章，则分组名可以是 articles，评论的分组名可以是 comments。
 * $counterRank = new CounterRank('redis_host', 'redis_port', 'namespace', '分组名');
 * // 创建一个item，create 方法可以接收一个数字作为默认值，留空则为0。下面的`900310`可以看做是文章 ID、评论 ID 等等。
 * $counterRank->create('900310', 0);
 * // 删除一个分组，`articles` 是分组名
 * $counterRank->deleteGroup('articles');
 * // 删除一个 item
 * $counterRank->delete('900310');
 * // 递增指定键名的值，如为负数则为递减。这里对 `900310` 这篇文章递增了 1
 * $counterRank->increase('900310', 1);
 * // 递减
 * $counterRank->increase('900310', -1);
 * // 倒序排序，最多 10 个。
 * $counterRank->rank(10, 'desc');
 * // 正序排序，最多 10 个
 * $counterRank->rank(10, 'asc');
 * // 最高的 10 个
 * $counterRank->top10();
 * // 最低的 10 个
 * $counterRank->down10();
 * ```
 */


class CounterRank
{


    /**
     *
     * @var string  redis host
     */
    private $host;

    /**
     *
     * @var int redis port
     */
    private $port;

    /**
     *
     * @var string  命名空间
     */
    private $namespace;


    /**
     * @var \Redis  redis 实例
     */
    private $redis;

    /**
     *
     * @var string 分组名
     */
    private $groupName = NULL;

    /**
     * @var bool 是否使用浮点数
     */
    private $useFloat = false;

    /**
     * 当对一个不存在的 key 进行操作时，执行的方法。如修复成功返回 true，否则返回 false。
     *
     * @var \Closure
     */
    private $fixMissClosure = null;
    /**
     * 使用 persistHelper 持久化后不执行任何操作
     *
     * @see CounterRank::persistHelper()
     */
    const PERSIST_WITH_NOTHING = 0;
    /**
     * 使用 persistHelper 持久化后删除 item
     *
     * @see CounterRank::persistHelper()
     */
    const PERSIST_WITH_DELETING = 1;
    /**
     * 使用 persistHelper 持久化后清零 item
     *
     * @see CounterRank::persistHelper()
     */
    const PERSIST_WITH_CLEARING = 2;

    /**
     * construct
     *
     * @param string $host redis host
     * @param int $port redis port
     * @param string $namespace 顶级命名空间，通常是项目名称
     * @param string $groupName 分组名，通常是实体名称，如 articles、comments
     * @param bool $useFloat 使用浮点数，默认是 false
     *
     */
    public function __construct($host, $port, $namespace, $groupName, $useFloat = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->namespace = $namespace;
        $this->setGroupName($groupName);
        $this->useFloat = $useFloat;

        $this->redis = new \Redis();
        $this->redis->connect($host, $port);
    }


    /**
     * 获取单个 item 的值
     *
     * @param string $key
     *
     * @return int|float
     */
    public function get($key)
    {
        $score = $this->redis->zScore($this->groupName, $key);
        if (is_numeric($score) && !$this->useFloat) {
            $score = intval($score);
        }
        return $score;
    }

    /**
     * 获取多个 item
     *
     * @param array $keys
     *
     * @return array
     */
    public function mGet(array $keys)
    {
        $items = array();
        foreach ($keys as $key) {
            $score = $this->redis->zScore($this->groupName, $key);
            if (is_numeric($score)) {
                if (!$this->useFloat) {
                    $score = intval($score);

                }
                $items[$key] = $score;
            }
        }

        return $items;
    }


    /**
     * 创建一个 item
     *
     * @param string $key item 键名
     * @param int|float $defaultValue 默认值，默认是 0
     *
     * @return Number of values added
     */
    public function create($key, $defaultValue = 0)
    {
        return $this->redis->zAdd($this->groupName, $defaultValue, $key);
    }

    /**
     * 创建多个 item
     *
     * @param array $items
     *
     * @return Number of values added
     */
    public function mCreate($items)
    {
        $_params = array();
        $_params[] = $this->groupName;
        foreach ($items as $_k => $_item) {
            $_params[] = $_item;
            $_params[] = $_k;
        }

//        var_dump($_items);
        return call_user_func_array(array($this->redis, 'zAdd'), $_params);
    }

    /**
     * 删除指定 key 的 item
     *
     * @param string $key
     *
     * @return int   Number of deleted fields
     */
    public function delete($key)
    {
        return $this->redis->zRem($this->groupName, $key);
    }

    /**
     * 通过 keys 数组删除多个 item
     *
     * @param array $keys
     *
     * @return int     Number of deleted values
     */
    public function mDelete(array $keys)
    {
        $params = array();
        $params[] = $this->groupName;
        $params = array_merge($params, $keys);
        return call_user_func_array(array($this->redis, 'zRem'), $params);
    }

    /**
     * 删除分组
     *
     * @param $groupName
     *
     * @return bool
     */
    public function deleteGroup($groupName)
    {
        return $this->redis->delete($this->nameSpacing($groupName)) == 1;
    }


    /**
     * 递增指定键名的 item
     *
     * @param string $key 键名
     * @param int $stepSize 递增步长，如为负数则是递减
     * @param bool $existCheck 是否检查 key 的存在
     *
     * @return int|float|null 新值，如果返回 null 则表示 key 不存在
     */
    public function increase($key, $stepSize, $existCheck = true)
    {
        if ($existCheck && !$this->checkExist($key)) {
            return null;
        }

        return $this->redis->zIncrBy($this->groupName, $stepSize, $key);
    }

    /**
     * 检查 key 是否存在，如不存在，则尝试使用指定的闭包修复。
     *
     * @param $key
     * @return bool
     */
    private function checkExist($key)
    {
        if (!is_numeric($this->get($key))) {
            if ($this->fixMissClosure != null
                && call_user_func_array($this->fixMissClosure, array($key, $this))
            ) {
                return true;
            }
            return false;
        } else {
            return true;
        }
    }

    /**
     * 递增多个 item
     * @param array $keys
     * @param int|float $stepSize
     * @param bool $existCheck 是否检查 key 的存在
     * @return array
     */
    public function mIncrease(array $keys, $stepSize, $existCheck = true)
    {
        $returns = array();

        foreach ($keys as $key) {
            $returns[$key] = $this->increase($key, $stepSize, $existCheck);
        }

        return $returns;
    }


    /**
     * 排序
     *
     * @param $limit
     * @param string $type 默认是倒序 desc 排序(大数到小数目)，可选 asc 正序排序(小数字到大数字)
     * @return array|bool|string
     */
    public function rank($limit, $type = 'desc')
    {
        $type = strtolower($type);
        $type = in_array($type, array('desc', 'asc')) ? $type : 'desc';

        if ($type == 'desc') {
            $items = $this->redis->zRevRange($this->groupName, 0, $limit - 1, true);
        } else {
            $_items = $this->redis->zRevRange($this->groupName, 0 - $limit, -1, true);
            $items = array();
            if ($_items) {
                $items = array_reverse($_items, true);
            }
        }

        return $items;
    }

    /**
     * top10
     *
     * @return array|bool|string
     */
    public function top10()
    {
        return $this->rank(10, 'desc');
    }

    /**
     * down10
     *
     * @return array|bool|string
     */
    public function down10()
    {
        return $this->rank(10, 'asc');
    }

    /**
     * 设置新的分组名
     * @param string $groupName
     */
    public function setGroupName($groupName)
    {
        if ($groupName != null) {
            $this->groupName = $this->nameSpacing($groupName);
        }
    }

    /**
     * 为分组名加上命名空间
     *
     * @param string $groupName
     *
     * @return string
     */
    private function nameSpacing($groupName)
    {

        if ($this->namespace) {
            $groupName = $this->namespace . ':' . $groupName;;
        }
        return $groupName;
    }

    /**
     * 设置当操作一个不存在的 keys 时的处理闭包。该闭包将接收两个参数，第一个参数是 key ，第二个参数是当前 CounterRank 对象。如修复后该 key 可以操作时返回 true，否则返回 false。
     *
     * @param \Closure $fixMissClosure 要 fix 的key
     */
    public function setFixMiss(\Closure $fixMissClosure)
    {
        $this->fixMissClosure = $fixMissClosure;
    }

    /**
     * 移除 fixMiss 闭包
     */
    public function removeFixMiss()
    {
        $this->fixMissClosure = null;
    }

    /**
     * 持久化帮助方法，可以遍历计数器中所有的元素
     *
     * @param callable $callback 回调函数，参数是一个键值对数组
     * @param int $opAfter 后置操作，参考本类中 PERSIST_WITH_ 打头的常量，默认不执行任何操作
     *
     * @throws \InvalidArgumentException
     */
    public function persistHelper(\Closure $callback, $opAfter = self::PERSIST_WITH_NOTHING)
    {
        $total = $this->redis->zCard($this->groupName);

        for ($i = 0; $i < $total; $i += 100) {

            $break = false;
            if (!in_array($opAfter, array(self::PERSIST_WITH_NOTHING, self::PERSIST_WITH_DELETING, self::PERSIST_WITH_CLEARING))) {
                throw new \InvalidArgumentException('$opAfter(第二个) 参数不正确，请参考本类中 PERSIST_WITH_ 打头的常量。');
            }
            $start = 0;
            $end = 100;

            if($opAfter === self::PERSIST_WITH_NOTHING) {
                $start = $i;
                $end = $i + 100;
            }

            $items = $this->redis->zRevRange($this->groupName, $start, $end, true);

            // 删除
            if ($opAfter === self::PERSIST_WITH_DELETING) {
                $params = array_merge(array($this->groupName), array_keys($items));
                call_user_func_array(array($this->redis, 'zRem'), $params);
                unset($params);
            } // 清零
            elseif ($opAfter === self::PERSIST_WITH_CLEARING) {
                foreach ($items as $key => $val) {
                    if ($items[$key] == 0) {
                        unset($items[$key]);
                        $break = true;
                    } else {
                        // 减去取出时的值
                        $this->redis->zIncrBy($this->groupName, 0 - $val, $key);
                    }
                }
            }

            call_user_func($callback, $items);

            if ($break) {
                break;
            }

            unset($items);
        }
    }
}