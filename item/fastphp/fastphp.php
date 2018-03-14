<?php

namespace fastphp;

// 框架根目录
defined('CORE_PATH') or define('CORE_PATH', __DIR__);

/**
 * fastphp框架核心
 */
class Fastphp
{
    // 配置内容
    protected $config = [];

    public function __construct($config)
    {
        $this->config = $config;
    }

    // 运行程序
    public function run()
    {
        spl_autoload_register(array($this, 'loadClass'));
        $this->setReporting();
        $this->removeMagicQuotes();
        $this->unregisterGlobals();
        $this->setDbConfig();
        $this->route();
    }

    // 路由处理
    public function route()
    {
        $controllerName = $this->config['defaultController'];
        $actionName = $this->config['defaultAction'];
        $param = array();

        $url = $_SERVER['REQUEST_URI'];

        // 清除?之后的内容
        $position = strpos($url, '?');   // 检索字符串中某个字符的位置
        $url = $position === false ? $url : substr($url, 0, $position);
        // 删除前后的“/”
        $url = trim($url, '/');

        if ($url) {
            // 使用“/”分割字符串，并保存在数组中
            $urlArray = explode('/', $url);

            // 删除空的数组元素
            $urlArray = array_filter($urlArray);  // 用回调函数过滤数组中的元素  第二个参数必须是回调函数
            
            // 获取控制器名
            $controllerName = ucfirst($urlArray[0]);  //把字符串首字母转换为大写
            
            // 获取动作名
            array_shift($urlArray);   //删除数组中的第一个元素（red），并返回被删除元素的值
            $actionName = $urlArray ? $urlArray[0] : $actionName;
            
            // 获取URL参数
            array_shift($urlArray);
            $param = $urlArray ? $urlArray : array();
        }

        // 判断控制器和操作是否存在  class_exists() 判断类是否存在
        $controller = 'app\\controllers\\'. $controllerName . 'Controller';
        if (!class_exists($controller)) {
            exit("<center><h3>".$controller . '控制器不存在</h3></center>');
        }
        if (!method_exists($controller, $actionName)) {
            exit("<center><h3>".$actionName . '方法不存在</h3></center>');
        }

        // 如果控制器和操作名存在，则实例化控制器，因为控制器对象里面
        // 还会用到控制器名和操作名，所以实例化的时候把他们俩的名称也
        // 传进去。结合Controller基类一起看
        $dispatch = new $controller($controllerName, $actionName);

        // $dispatch保存控制器实例化后的对象，我们就可以调用它的方法，
        // 也可以像方法中传入参数，以下等同于：$dispatch->$actionName($param)
        // call_user_func_array() 调用回调函数，并把一个数组参数作为回调函数的参数
        call_user_func_array(array($dispatch, $actionName), $param);   
    }

    // 检测开发环境
    public function setReporting()
    {
        if (APP_DEBUG === true) {
            error_reporting(E_ALL);   //设置提示错误级别  E_ALL 全部错误  0 关闭提示 
            ini_set('display_errors','On');   // 为一个配置设置值
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors','Off');
            ini_set('log_errors', 'On');
        }
    }

    // 删除敏感字符
    public function stripSlashesDeep($value)
    {
        // array_map()  数组每个值的平方  并返回新值得数组
        // stripslashes()  删除反斜线
        $value = is_array($value) ? array_map(array($this, 'stripSlashesDeep'), $value) : stripslashes($value);
        return $value;
    }

    // 检测敏感字符并删除
    public function removeMagicQuotes()
    {
        if (get_magic_quotes_gpc()) {  // get_magic_quotes_gpc()  获取 magic_quotes_gpc 配置选项设置
            $_GET = isset($_GET) ? $this->stripSlashesDeep($_GET ) : '';
            $_POST = isset($_POST) ? $this->stripSlashesDeep($_POST ) : '';
            $_COOKIE = isset($_COOKIE) ? $this->stripSlashesDeep($_COOKIE) : '';
            $_SESSION = isset($_SESSION) ? $this->stripSlashesDeep($_SESSION) : '';
        }
    }

    // 检测自定义全局变量并移除。因为 register_globals 已经弃用，如果
    // 已经弃用的 register_globals 指令被设置为 on，那么局部变量也将
    // 在脚本的全局作用域中可用。 例如， $_POST['foo'] 也将以 $foo 的
    // 形式存在，这样写是不好的实现，会影响代码中的其他变量。 相关信息，
    // 参考: http://php.net/manual/zh/faq.using.php#faq.register-globals
    public function unregisterGlobals()
    {
        if (ini_get('register_globals')) {    //获取一个配置选项的值
            $array = array('_SESSION', '_POST', '_GET', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES');
            foreach ($array as $value) {
                foreach ($GLOBALS[$value] as $key => $var) {
                    if ($var === $GLOBALS[$key]) {
                        unset($GLOBALS[$key]);
                    }
                }
            }
        }
    }

    // 配置数据库信息
    public function setDbConfig()
    {
        if ($this->config['db']) {
            define('DB_HOST', $this->config['db']['host']);
            define('DB_NAME', $this->config['db']['dbname']);
            define('DB_USER', $this->config['db']['username']);
            define('DB_PASS', $this->config['db']['password']);
        }
    }

    // 自动加载类
    public function loadClass($className)
    {
        $classMap = $this->classMap();

        if (isset($classMap[$className])) {
            // 包含内核文件
            $file = $classMap[$className];
        } elseif (strpos($className, '\\') !== false) { 
            // 包含应用（application目录）文件
            $file = APP_PATH . str_replace('\\', '/', $className) . '.php';   //替换字符串中指定字符
            if (!is_file($file)) {   //检索正常文件
                return;
            }
        } else {
            return;
        }

        include $file;

        // 这里可以加入判断，如果名为$className的类、接口或者性状不存在，则在调试模式下抛出错误
    }

    // 内核文件命名空间映射关系
    protected function classMap()
    {
        return [
            'fastphp\base\Controller' => CORE_PATH . '/base/Controller.php',
            'fastphp\base\Model' => CORE_PATH . '/base/Model.php',
            'fastphp\base\View' => CORE_PATH . '/base/View.php',
            'fastphp\db\Db' => CORE_PATH . '/db/Db.php',
            'fastphp\db\Sql' => CORE_PATH . '/db/Sql.php',
        ];
    }
}