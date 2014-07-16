<?php

namespace mr5\CounterRank;

// +----------------------------------------------------------------------
// | [counter-rank]
// +----------------------------------------------------------------------
// | Author: Mr.5 <mr5.simple@gmail.com>
// +----------------------------------------------------------------------
// + Datetime: 14-7-4 下午2:50
// +----------------------------------------------------------------------
// + CounterAndRankTest.php
// +----------------------------------------------------------------------


class CounterRankTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \mr5\counterRank\CounterRank
     */
    private $counterRank = null;
    /**
     * @var string
     */
    private $groupName = 'CounterAndRankTest';
    /**
     * @var array
     */
    private $testData = array();

    protected function setUp()
    {

        $this->counterRank = new CounterRank(REDIS_SERVER_HOST, REDIS_SERVER_PORT, REDIS_NAMESPACE, $this->groupName);

        $this->testData['items'] = array();

        $_numbers = array();
        for ($i = 0; $i < 1000; $i++) {
            $_numbers[] = $i;
        }
        shuffle($_numbers);
        foreach ($_numbers as $_num) {
            $this->testData['items']['testSortItem_' . $_num] = (string)$_num;
        }
        $this->testData['top10'] = array(
            "testSortItem_999" => "999",
            "testSortItem_998" => "998",
            "testSortItem_997" => "997",
            "testSortItem_996" => "996",
            "testSortItem_995" => "995",
            "testSortItem_994" => "994",
            "testSortItem_993" => "993",
            "testSortItem_992" => "992",
            "testSortItem_991" => "991",
            "testSortItem_990" => "990"
        );
        $this->testData['down10'] = array(
            "testSortItem_0" => "0",
            "testSortItem_1" => "1",
            "testSortItem_2" => "2",
            "testSortItem_3" => "3",
            "testSortItem_4" => "4",
            "testSortItem_5" => "5",
            "testSortItem_6" => "6",
            "testSortItem_7" => "7",
            "testSortItem_8" => "8",
            "testSortItem_9" => "9"
        );
    }

    /**
     * 测试删除分组
     */
    public function testDeleteGroup()
    {

        $this->assertEquals(1, $this->counterRank->create('testDeleteGroup', 1));

        $this->assertEquals(2, $this->counterRank->mCreate(array(
            'testDeleteGroup2' => 2,
            'testDeleteGroup3' => 3
        )));

        $this->assertEquals(1, $this->counterRank->get('testDeleteGroup'));
        $this->assertEquals(2, $this->counterRank->get('testDeleteGroup2'));
        $this->assertEquals(3, $this->counterRank->get('testDeleteGroup3'));

        $this->assertTrue($this->counterRank->deleteGroup($this->groupName));

        $this->assertFalse($this->counterRank->get('testDeleteGroup'));
        $this->assertFalse($this->counterRank->get('testDeleteGroup2'));
        $this->assertFalse($this->counterRank->get('testDeleteGroup3'));
    }

    /**
     * 创建、删除和获取测试
     */
    public function testCreateDeleteAndRead()
    {
        $items = array();
        for ($i = 1; $i <= 10; $i++) {
            $items['item' . $i] = $i;
        }
        $keys = array_keys($items);
        $this->assertEquals(1, $this->counterRank->create('testSingleCreate', 1));
        $this->assertEquals(1, $this->counterRank->get('testSingleCreate'));
        $this->assertEquals(1, $this->counterRank->delete('testSingleCreate'));
        $this->assertFalse($this->counterRank->get('testSingleCreate'));

        // 批量插入测试
        $this->assertTrue($this->counterRank->mCreate($items) > 0);
        // 批量获取测试
        $this->assertCount(10, $this->counterRank->mGet($keys));
        $this->assertEquals(3, $this->counterRank->get('item3'));
        // 批量删除
        $this->assertEquals(10, $this->counterRank->mDelete($keys));
        // 批量删除过后应该取不到值
        $this->assertCount(0, $this->counterRank->mGet($keys));


        // 不存在的 key
        $this->assertFalse($this->counterRank->get('keyNotExist'));
    }

    /**
     * 递增测试
     */
    public function testIncrease()
    {
        // 单个元素的递增测试
        $this->assertEquals(1, $this->counterRank->create('testIncrease', 0));
        $this->assertEquals(1, $this->counterRank->increase('testIncrease', 1));
        $this->assertEquals(1, $this->counterRank->get('testIncrease'));
        $this->assertEquals(0, $this->counterRank->increase('testIncrease', -1));
        $this->assertEquals(0, $this->counterRank->get('testIncrease'));
    }

    /**
     * 排序测试
     */
    public function testRank()
    {

        $this->assertEquals(count($this->testData['items']), $this->counterRank->mCreate($this->testData['items']));

        $this->assertEquals($this->testData['top10'], $this->counterRank->rank(10, 'desc'));
        $this->assertNotEquals($this->testData['top10'], $this->counterRank->rank(10, 'asc'));
        $this->assertEquals($this->testData['down10'], $this->counterRank->rank(10, 'asc'));
        $this->assertEquals($this->testData['down10'], $this->counterRank->down10());
        $this->assertEquals($this->testData['top10'], $this->counterRank->top10());
    }

    /**
     * 持久化帮助方法测试，持久化后删除
     */
    public function testPersistHelperWithDeleting()
    {
        $self = $this;

        $itemsSource = $this->testData['items'];
        $this->assertEquals(count($this->testData['items']), $this->counterRank->mCreate($itemsSource));
        $testItems = array();

        $this->counterRank->persistHelper(function (array $_items) use (& $testItems, $self) {
            $testItems = array_merge($testItems, $_items);


            $self->assertEmpty($self->counterRank->mGet(array_keys($_items)));

            unset($_items);
        }, CounterRank::PERSIST_WITH_DELETING);

        arsort($itemsSource);
        $this->assertEquals(json_encode($itemsSource) , json_encode($testItems));
        unset($fileContentExcepted);
        unset($itemsSource);
    }
    /**
     * 持久化帮助方法测试，持久化后不执行任何操作
     */
    public function testPersistHelperWithNothing()
    {
        $itemsSource = $this->testData['items'];
        $this->assertEquals(count($this->testData['items']), $this->counterRank->mCreate($itemsSource));
        $testItems = array();

        $this->counterRank->persistHelper(function (array $_items) use (& $testItems) {
            $testItems = array_merge($testItems, $_items);

            $this->assertEquals($_items, $this->counterRank->mGet(array_keys($_items)));

            unset($_items);
        }, CounterRank::PERSIST_WITH_NOTHING);

        arsort($itemsSource);


        $this->assertEquals(json_encode($itemsSource) , json_encode($testItems));
        unset($itemsSource);
        unset($testItems);
    }
    /**
     * 持久化帮助方法测试，持久化后清零
     */
    public function testPersistHelperWithClearing()
    {
        $itemsSource = $this->testData['items'];
        $this->assertEquals(count($this->testData['items']), $this->counterRank->mCreate($itemsSource));
        $testItems = array();

        $this->counterRank->persistHelper(function (array $_items) use (& $testItems) {
            $testItems = array_merge($testItems, $_items);

            $itemsExcepted = array();
            foreach($_items AS $key=>$_v) {
                $itemsExcepted[$key] = 0;
            }

            $this->assertEquals($itemsExcepted, $this->counterRank->mGet(array_keys($_items)));

            unset($_items);
            unset($itemsExcepted);
        }, CounterRank::PERSIST_WITH_CLEARING);

        arsort($itemsSource);
        unset($itemsSource['testSortItem_0']);
        $this->assertEquals(json_encode($itemsSource) , json_encode($testItems));
        unset($fileContentExcepted);
        unset($testItems);
    }


    /**
     * 测试 fixMiss 闭包
     */
    public function testFixMiss()
    {
        $this->assertNull($this->counterRank->increase('inexistense', 10));

        $this->counterRank->setFixMiss(function ($key, CounterRank $counterRank) {
            return $counterRank->create($key, 1000) > 0;
        });

        $this->assertEquals(1010, $this->counterRank->increase('inexistense', 10));

    }

    protected function tearDown()
    {
        $this->counterRank->deleteGroup($this->groupName);
    }

}