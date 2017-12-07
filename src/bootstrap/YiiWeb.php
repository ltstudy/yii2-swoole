<?php
/**
 * Created by PhpStorm.
 * User: tsingsun
 * Date: 2017/3/7
 * Time: 上午10:41
 */

namespace yii\swoole\bootstrap;

use Yii;
use yii\swoole\coroutine\Task;
use yii\swoole\log\Logger;
use yii\swoole\server\Server;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use yii\swoole\server\Timer;
use yii\swoole\web\Application;
use yii\swoole\web\ErrorHandle;
use yii\swoole\web\Response;
use Swoole\Server as SwooleServer;

/**
 * Yii starter for swoole server
 * @package yii\swoole\bootstrap
 */
class YiiWeb extends BaseBootstrap
{
    public $index = '/index.php';
    /**
     * @var callable
     */
    public $init;


    public function onWorkerStart(SwooleServer $server, $worker_id)
    {
        //使application运行时不会报错
        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $this->index;
        $_SERVER['SCRIPT_FILENAME'] = $this->server->root . $this->index;
        $_SERVER['WORKER_ID'] = $worker_id;

        $initFunc = $this->init;
        if ($initFunc instanceof \Closure) {
            $initFunc($this);
        }
        $this->app->server = $this->server;

        $this->initComponent();
    }

    /**
     * @param SwooleRequest $request
     * @param SwooleResponse $response
     * @throws \Exception
     * @throws \Throwable
     */
    public function handleRequest($request, $response)
    {
        try {
            /** @var Application $app */
            $app = Yii::$app;
            //YII对于协程支持有天然缺陷
//            $coroutine = Yii::$app->run();
//            $app->task = new Task($coroutine, Yii::$app);
//            $app->task->run();
//            return;
            $app->run();

        } catch (\Exception $e) {
            Yii::$app->getErrorHandler()->handleException($e);
        } catch (\Throwable $e) {
            $eh = Yii::$app->getErrorHandler();
            if ($eh instanceof ErrorHandle) {
                $eh->handleFallbackExceptionMessage($e, $e->getPrevious());
            }
        }
    }

    /**
     * 初始化环境变量
     */
    protected function setupEnvironment(SwooleRequest $request,SwooleResponse $response)
    {
        $_GET = isset($request->get) ? $request->get : [];
        $_POST = isset($request->post) ? $request->post : [];
        $_SERVER = array_change_key_case($request->server, CASE_UPPER);
        $_FILES = isset($request->files) ? $request->files : [];
        $_COOKIE = isset($request->cookie) ? $request->cookie : [];

        if (isset($request->header)) {
            foreach ($request->header as $key => $value) {
                $key = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
                $_SERVER[$key] = $value;
            }
        }

        $file = $this->server->root . $this->index;

        $_SERVER['PHP_SELF'] = $_SERVER['SCRIPT_NAME'] = $_SERVER['DOCUMENT_URI'] = $this->index;
        $_SERVER['SCRIPT_FILENAME'] = $file;
        $_SERVER['SERVER_ADDR'] = '127.0.0.1';
        $_SERVER['SERVER_NAME'] = 'localhost';
        $app = clone $this->app;
        Yii::$app = &$app;
        $app->set('request', clone $this->app->getRequest());
        $yiiRes = clone $this->app->getResponse();
        if ($yiiRes instanceof Response) {
            $yiiRes->setSwooleResponse($response);
        }
        $app->set('response', $yiiRes);
        $app->set('view', clone $this->app->getView());
        $app->set('errorHandle', clone $this->app->getErrorHandler());
    }

    public function onFinish(SwooleServer $serv, int $task_id, string $data)
    {
        //echo $data;
    }

    /**
     * @param SwooleServer $server
     * @param $worker_id
     */
    public function onWorkerStop(SwooleServer $server, $worker_id)
    {
        if (!$server->taskworker) {
            Yii::getLogger()->flush(true);
        }
    }

    /**
     * 初始化一些可以复用的组件
     */
    private function initComponent()
    {
        $this->app->getSecurity();
        $this->app->getUrlManager();
        $this->app->getRequest()->setBaseUrl(null);
        $this->app->getRequest()->setScriptUrl($this->index);
        $this->app->getRequest()->setScriptFile($this->index);
        $this->app->getRequest()->setUrl(null);

        if ($this->app->has('session', true)) {
            $this->app->getSession();
        }
        $this->app->getView();
        $this->app->getDb();
        $this->app->getUser();
        if ($this->app->has('mailer', true)) {
            $this->app->getMailer();
        }
    }
}