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

// 命名空间 namespace 用于区分不同的项目。
// 分组名是一类 items 的分组。
// 比如要统计的是文章，则分组名可以是 articles，评论的分组名可以是 comments。
$counterRank = new CounterRank(
	'redis_host', 
	'redis_port', 
	'namespace', 
	'分组名'
);

// 创建一个item，create 方法可以接收一个数字作为默认值，留空则为0。
// 下面的`900310`可以看做是文章 ID、评论 ID 等等。
$counterRank->create('900310', 0);

// 删除一个分组，`articles` 是分组名
$counterRank->deleteGroup('articles');

// 删除一个 item
$counterRank->delete('900310');

// 递增指定键名的值，如为负数则为递减。
// 这里对 `900310` 这篇文章递增了 1
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

// 设置当操作一个不存在的 keys 时的处理闭包。
// 该闭包将接收两个参数，第一个参数是 key ，
// 第二个参数是当前 CounterRank 对象。
// 如修复后该 key 可以操作时返回 true，否则返回 false。
// 可以用于自动创建 item
$counterRank->setFixMiss(function($key, CounterRank $counterRank) {
            return $counterRank->create($key, 0) > 0;
});


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
    protected $tokens = array(
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
        $this->handlerInstance = new JSClientHandler(
						$this->redis_host, 
						$this->redis_port, 
						$this->namespace, 
						$this->tokens, 
						$this->increaseStepSize
		);
    }

    // 读取
    public function getAction()
    {
        $this->handlerInstance->handleGet(
			$_REQUEST['token'], 
			$_REQUEST['group'], 
			$_REQUEST['keys'], 
			$_REQUEST['callback']
		);
    }

    // 递增
    public function increaseAction()
    {
        $this->handlerInstance->handleIncrease(
			$_REQUEST['token'], 
			$_REQUEST['group'], 
			$_REQUEST['keys'], 
			$_REQUEST['callback']
		);
    }

    // 排名
    public function rankAction()
    {
        $this->handlerInstance->handleRank(
			$_REQUEST['token'], 
			$_REQUEST['group'], 
			$_REQUEST['type'], 
			$_REQUEST['limit'], 
			$_REQUEST['callback']
		);
    }

    // 最高的十个数据
    public function top10Action()
    {
        $this->handlerInstance->handleTop10(
			$_REQUEST['token'], 
			$_REQUEST['group'], 
			$_REQUEST['callback']
		);

    }

    // 最低的十个数据
    public function down10Action()
    {
        $this->handlerInstance->handleDown10(
			$_REQUEST['token'], 
			$_REQUEST['group'], 
			$_REQUEST['callback']
		);

    }
}

```
### 自定义 token 验证规则

token 默认验证方式是判断用户递交的 token 是否等于约定的 token ，
你可以通过传递一个闭包给 `setTokenVerifier` 方法来自定义这个验证规则。
下面是自定义 tokenVerifier 的例子：
```php

$jsClientHandler->setTokenVerifier(function (
			$operation,	// 操作名
			$userToken, 	// 客户端提交的 token
			$token, 		// 服务器端约定的 token
			$group, 		// 分组名
			$keys) {
                $str = $token.$group;
                if($keys) {
                    $str .= $keys;
                }
                return md5($str) == $userToken;
            });
```

## 持久化计数数据

 [`CounterRank`](lib/mr5/CounterRank/CounterRank) 类提供了一个名为 `persistHelper` 方法来帮助你持久化计数器中的数据，如同步到 MySQL 数据库。`persistHelper` 方法接收两个参数，第一个参数是一个闭包，该闭包包含了你自定义的同步逻辑，闭包的参数是 items 键值对数组。第二个参数有三个选项：
 
 * `CounterRank::PERSIST_WITH_DELETING`  持久化后的 items 删除，使用场景为使用 CounterRank 统计全量数据。一般需要配合 `$counterRank->setMissFix()` 来初始化不存在的键。
 * `CounterRank::PERSIST_WITH_CLEARING`  持久化后的 items 清零，使用场景为使用 CounterRank 统计增量数据，在比如 MySQL 之类的持久化数据库系统中保留全量数据。
 * `CounterRank::PERSIST_WITH_NOTHING`   持久化后不执行任何操作，使用场景为使用 redis 统计全量数据，并且作为主要持久化途径，其他持久化途径仅作为备份。

下面是一个同步到数据库的例子：

```php
// 注意：本操作可能耗时很长，请在命令行下执行并根据实际情况加入操作系统计划任务，而不是通过 web 请求执行

$counterRank = new CounterRank(
	'redis_host', 
	'redis_port', 
	'namespace', 
	'分组名'
);
$conn = mysql_connect('localhost', 'username', 'password');
mysql_select_db('dbname', $conn);
mysql_set_charset('utf8', $conn);

$counterRank->persistHelper(function($items) {
    $values = '';
    foreach($items AS $post_id=>$heat) {
        if($values != '') {
            $values .= ',';
        }
        $values .= "({$post_id}, {$heat})";
    }
    mysql_query(
        "INSERT INTO `posts`(post_id, heat) VALUES  {$values} ON DUPLICATE KEY UPDATE heat=values(heat);",
        $conn
    );
});
```
