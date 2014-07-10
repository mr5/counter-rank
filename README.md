## 简介
Counter-Rank 是一个使用 PHP 语言编写的、基于 Redis 的排名与计数类库，主要是对 Redis 的 zset 操作进行了封装。并提供了一个 [`JSClientHandler`](lib/mr5/CounterRank/JSClientHandler.php) 类，可以快速地生成 JsonP 接口用于满足一些静态页面的计数需求以及将数字渲染到页面。

本类库的源代码中有详细的注释，你可以使用类 PHPDoc 的工具生成 classes reference manual 。

## 安装
在 `composer.json` 中添加：
```json
{
	require: "mr5/counter-rank"
}
```
然后安装
```shell
composer install
```
## 注意事项
打开 `phpunit.xml` 文件，找到以下片段：
```xml
<php>
	<!-- Redis -->
	<const name="REDIS_SERVER_HOST" value="127.0.0.1" />
	<const name="REDIS_SERVER_PORT" value="6379" />
	<const name="REDIS_NAMESPACE" value="__unitTest___" />
</php>
```
如你要运行本类库的单元测试，请注意其中 `REDIS_NAMESPACE` 定义的命名空间，以防误删你的 Redis 数据。如有必要，可以修改该值。 
## 基础使用
```php
use mr5\CounterRank\CounterRank;

// 命名空间 namespace 用于区分不同的项目。分组名是一类 items 的分组。比如要统计的是文章，则分组名可以是 articles，评论的分组名可以是 comments。
$counterRank = new CounterRank('redis_host', 'redis_port', 'namespace', '分组名');
// 创建一个item，create 方法可以接收一个数字作为默认值，留空则为0。下面的`900310`可以看做是文章 ID、评论 ID 等等。
$counterRank->create('900310', 0);
// 删除一个分组，`articles` 是分组名
$counterRank->deleteGroup('articles');
// 删除一个 item
$counterRank->delete('900310');
// 递增指定键名的值，如为负数则为递减。这里对 `900310` 这篇文章递增了 1
$counterRank->increase('900310', 1);
// 递减
$counterRank->increase('900310', -1);
// 倒序排序，最多 10 个。
$counterRank->rank(10, 'desc');
// 正序排序，最多 10 个
$counterRank->rank(10, 'asc');
// 最高的 10 个
$counterRank->top10();
// 最低的 10 个
$counterRank->down10();
```
## JSClientHandler 的使用
`JSClientHandler` 是一个用于生成 JS 客户端的工具类。
它可以实现以下功能：
* token 验证
* get: 读取指定分组指定键的数值;
* increase: 递增;
* rank: 获取排名;
* 指定 callback ，以实现 JsonP

以下是一个自定义控制器参考，请根据自己使用的框架以及需求进行修改，另外还建议阅读 [`JSClientHandler.php`](lib/mr5/CounterRank/JSClientHandler.php) 的源代码，内含详细的 PHP Doc：
```php
use mr5\CounterRank\JSClientHandler;

/**
 *
 * JS 客户端控制器示例，请根据自己使用的框架以及需求进行修改
 *
 */
class ExampleController  extends Controller
{
	// 配置客户端的 token，键是分组名，值是 token 值。每个分组都必须指定，未指定的则不允许通过 JS Client 访问
    protected $token = array(
        'articles' => '1234567890JQK',
        'comments' => 'abcdefghijk'
    );
    protected $redis_host = 'localhost';
    protected $redis_port = 6379;

	// 命名空间，建议使用项目名称
    protected $namespace = 'project_name';
	// 递增的步长，如设置成负数，则 increase 方法执行的是递减
    protected $increaseStepSize = 1;

    // @var JSClientHandler|null
    protected $handlerInstance = null;

    public function __construct()
    {
        $this->handlerInstance = new JSClientHandler($this->redis_host, $this->redis_port, $this->namespace, $this->token, $this->increaseStepSize);
    }

    // 读取
    public function getAction()
    {
        $this->handlerInstance->handleGet($_REQUEST['token'], $_REQUEST['group'], $_REQUEST['keys'], $_REQUEST['callback']);
    }

    // 递增
    public function increaseAction()
    {
        $this->handlerInstance->handleIncrease($_REQUEST['token'], $_REQUEST['group'], $_REQUEST['keys'], $_REQUEST['callback']);
    }

    // 排名
    public function rankAction()
    {
        $this->handlerInstance->handleRank($_REQUEST['token'], $_REQUEST['group'], $_REQUEST['type'], $_REQUEST['limit'], $_REQUEST['callback']);
    }

    // 最高的十个数据
    public function top10Action()
    {
        $this->handlerInstance->handleTop10($_REQUEST['token'], $_REQUEST['group'], $_REQUEST['callback']);

    }

    // 最低的十个数据
    public function down10Action()
    {
        $this->handlerInstance->handleDown10($_REQUEST['token'], $_REQUEST['group'], $_REQUEST['callback']);

    }
}
```