<?php

namespace Youvision\ThinkLayuiHelper;

use think\Service as ThinkService;

class Service extends ThinkService
{
    /**
     * 合并包默认配置，应用 config/layui_helper.php 中的配置优先
     *
     * @return void
     */
    public function register(): void
    {
        $default = require __DIR__ . '/../config/layui_helper.php';
        $current = (array) $this->app->config->get('layui_helper', []);

        $this->app->config->set(array_merge($default, $current), 'layui_helper');
    }
}