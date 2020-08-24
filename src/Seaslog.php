<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think\log\driver;

use think\App;
use think\facade\Request;
/**
 * 本地化调试输出到文件
 */
class Seaslog
{
    protected $config = [
        'time_format' => ' c ',
        'path'        => '',
        'logger'      => '',
        'json'        => false,
    ];

    protected $app;

    // 实例化并传入参数
    public function __construct(App $app, $config = [])
    {
        $this->app = $app;

        if (is_array($config)) {
            $this->config = array_merge($this->config, $config);
        }

        if (empty($this->config['path'])) {
            $this->config['path'] = $this->app->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR;
        } elseif (substr($this->config['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->config['path'] .= DIRECTORY_SEPARATOR;
        }

        \SeasLog::setBasePath($this->config['path']);
        if ($this->config['logger']) {
            \SeasLog::setLogger($this->config['logger']);
        }
    }

    /**
     * 日志写入接口
     * @access public
     * @param  array    $log    日志信息
     * @param  bool     $append 是否追加请求信息
     * @return bool
     */
    public function save(array $log = [], $append = false)
    {
        $this->config['logger'] = empty(Request::controller())?'':Request::controller().'/'.Request::action();
        if (PHP_SAPI != 'cli') {
            if (!$this->config['json']) {
                \SeasLog::log('info', $this->parseLog());
            }
            // 添加调试日志
            if ($this->app->isDebug() && $append && !$this->config['json']) {
                \SeasLog::log('debug', $this->getDebugLog());
            }
        }

        foreach ($log as $type => $val) {
            if ($this->config['json']) {
                $info[$type] = $val;
            } else {
                \SeasLog::log($type, implode("\n\r", $val));
            }
        }

        if (!empty($info)) {
            if (isset($info['info'])) {
                array_unshift($info['info'], $this->parseLog(true));
            } else {
                $info['info'][] = $this->parseLog(true);
            }

            if ($this->app->isDebug() && $append) {
                if (isset($info['debug'])) {
                    array_unshift($info['debug'], $this->getDebugLog(true));
                } else {
                    $info['debug'][] = $this->getDebugLog(true);
                }
            }

            \SeasLog::log('info', json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        return \SeasLog::flushBuffer();
    }

    /**
     * 追加请求日志
     * @access protected
     * @param  bool     $json 是否JSON格式
     * @return string
     */
    protected function parseLog($json = false)
    {
        $info = [
            'timestamp' => date($this->config['time_format']),
            'ip'        => $this->app['request']->ip(),
            'method'    => $this->app['request']->method(),
            'host'      => $this->app['request']->host(),
            'uri'       => $this->app['request']->url(),
        ];

        if ($json) {
            return json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return "---------------------------------------------------------------\r\n[{$info['timestamp']}] {$info['ip']} {$info['method']} {$info['host']}{$info['uri']}";
    }

    /**
     * 追加调试日志
     * @access protected
     * @param  bool     $json 是否JSON格式
     * @return string
     */
    protected function getDebugLog($json = false)
    {
        if ($json) {
            // 获取基本信息
            $runtime = round(microtime(true) - $this->app->getBeginTime(), 10);
            $reqs    = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';

            $memory_use = number_format((memory_get_usage() - $this->app->getBeginMem()) / 1024, 2);

            $info = [
                'runtime' => number_format($runtime, 6) . 's',
                'reqs'    => $reqs . 'req/s',
                'memory'  => $memory_use . 'kb',
                'file'    => count(get_included_files()),
            ];
            return json_encode($info, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        // 增加额外的调试信息
        $runtime = round(microtime(true) - $this->app->getBeginTime(), 10);
        $reqs    = $runtime > 0 ? number_format(1 / $runtime, 2) : '∞';

        $memory_use = number_format((memory_get_usage() - $this->app->getBeginMem()) / 1024, 2);

        $time_str   = '[运行时间：' . number_format($runtime, 6) . 's] [吞吐率：' . $reqs . 'req/s]';
        $memory_str = ' [内存消耗：' . $memory_use . 'kb]';
        $file_load  = ' [文件加载：' . count(get_included_files()) . ']';

        return $time_str . $memory_str . $file_load;

    }
}
