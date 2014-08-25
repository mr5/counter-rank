<?php

namespace mr5\CounterRank;

// +----------------------------------------------------------------------
// | [counter-rank]
// +----------------------------------------------------------------------
// | Author: Mr.5 <mr5.simple@gmail.com>
// +----------------------------------------------------------------------
// + Datetime: 14-7-8 14:49
// +----------------------------------------------------------------------
// + JSClientHandlerTest.php  JS 客户端处理器单元测试
// +----------------------------------------------------------------------

class JSClientHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var JSClientHandler
     */
    private $jsHandler = null;
    /**
     * @var CounterRank
     */
    private $counterRank = null;
    /**
     * @var string 密钥
     */
    private $tokens = array(
        'testHandleGroupName' => '123456'
    );
    private $groupName = 'testHandleGroupName';

    private $testData = array();

    protected function setUp()
    {
        $this->counterRank = new CounterRank(REDIS_SERVER_HOST, REDIS_SERVER_PORT, REDIS_NAMESPACE, $this->groupName, false);
        $this->jsHandler = new JSClientHandler(REDIS_SERVER_HOST, REDIS_SERVER_PORT, REDIS_NAMESPACE, $this->tokens, 1, '_', false, false);

        $this->testData['items'] = array();

        $_numbers = array();
        for ($i = 0; $i < 1000; $i++) {
            $_numbers[] = $i;
        }
        shuffle($_numbers);
        foreach ($_numbers as $_num) {
            $this->testData['items']['testSortItem_' . $_num] = $_num;

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
     * 读取处理测试
     */
    public function testHandleGet()
    {

        $this->jsHandler->handleGet(
            $this->tokens[$this->groupName],
            $this->groupName,
            'testHandleGetKey',
            'testCallback'
        );
        $this->assertEquals("testCallback(null);", $this->jsHandler->getLastOutput());

        $this->counterRank->create('testGet', 1);
        $this->jsHandler->handleGet($this->tokens[$this->groupName], $this->groupName, 'testGet', 'testCallback2');
        $this->assertEquals("testCallback2(1);", $this->jsHandler->getLastOutput());
        // 不带 callback
        $this->jsHandler->handleGet($this->tokens[$this->groupName], $this->groupName, 'testGet', '');
        $this->assertEquals("1", $this->jsHandler->getLastOutput());


        $this->counterRank->create('testGet2', 2);
        $this->counterRank->create('testGet3', 3);
        $this->jsHandler->handleGet(
            $this->tokens[$this->groupName],
            $this->groupName,
            'testGet_testGet2_testGet3',
            'testCallback3'
        );
        $this->assertEquals(
            'testCallback3({"testGet":1,"testGet2":2,"testGet3":3});',
            $this->jsHandler->getLastOutput()
        );
    }

    /**
     * 递增处理测试
     */
    public function testHandleIncrease()
    {
        $this->counterRank->create('testHandleIncreaseKey');

        $this->jsHandler->handleIncrease(
            $this->tokens[$this->groupName],
            $this->groupName,
            'testHandleIncreaseKey',
            'increaseCallback'
        );
        $this->assertEquals('increaseCallback(1);', $this->jsHandler->getLastOutput());
        // 不存在的 key
        $this->jsHandler->handleIncrease(
            $this->tokens[$this->groupName],
            $this->groupName,
            'testHandleIncreaseKeyDoesNotExist',
            'increaseCallback'
        );
        $this->assertEquals('increaseCallback(null);', $this->jsHandler->getLastOutput());

        $this->jsHandler->handleIncrease(
            $this->tokens[$this->groupName],
            $this->groupName,
            'testHandleIncreaseKey',
            'increaseCallback'
        );
        $this->assertEquals('increaseCallback(2);', $this->jsHandler->getLastOutput());

        $this->jsHandler->handleIncrease($this->tokens[$this->groupName], $this->groupName, 'testHandleIncreaseKey');
        $this->assertEquals('3', $this->jsHandler->getLastOutput());

    }

    /**
     * 排名处理器测试
     */
    public function testHandleRank()
    {

        $this->assertEquals(count($this->testData['items']), $this->counterRank->mCreate($this->testData['items']));
        $this->jsHandler->handleRank($this->tokens[$this->groupName], $this->groupName, 'asc', 10);
        $this->assertEquals(json_encode($this->testData['down10']), $this->jsHandler->getLastOutput());

        $this->jsHandler->handleRank($this->tokens[$this->groupName], $this->groupName, 'asc', 10, 'rankCallback');
        $this->assertEquals(
            'rankCallback(' . json_encode($this->testData['down10']) . ');',
            $this->jsHandler->getLastOutput()
        );

        $this->jsHandler->handleRank($this->tokens[$this->groupName], $this->groupName, 'desc', 10, 'rankCallback');
        $this->assertEquals(
            'rankCallback(' . json_encode($this->testData['top10']) . ');',
            $this->jsHandler->getLastOutput()
        );

        $this->jsHandler->handleTop10($this->tokens[$this->groupName], $this->groupName, 'rankCallback');
        $this->assertEquals(
            'rankCallback(' . json_encode($this->testData['top10']) . ');',
            $this->jsHandler->getLastOutput()
        );

        $this->jsHandler->handleDown10($this->tokens[$this->groupName], $this->groupName, 'rankCallback');
        $this->assertEquals(
            'rankCallback(' . json_encode($this->testData['down10']) . ');',
            $this->jsHandler->getLastOutput()
        );

    }

    /**
     * 测试自定义 token 验证器
     */
    public function testSpecificalTokenVerifier()
    {


        $this->jsHandler->setTokenVerifier(
            function ($operation, $userToken, $token, $group, $keys) {
                $str = $token . $group;
                if ($keys) {
                    $str .= $keys;
                }
                return md5($str) == $userToken;
            }
        );
        $this->counterRank->create('testTokenVerifier', 1100);
        $this->jsHandler->handleGet(
            md5($this->tokens['testHandleGroupName'] . $this->groupName . 'testTokenVerifier'),
            $this->groupName,
            'testTokenVerifier'
        );

        $this->assertEquals(1100, $this->jsHandler->getLastOutput());
    }

    protected function tearDown()
    {
        $this->counterRank->deleteGroup($this->groupName);
    }
} 