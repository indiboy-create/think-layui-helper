<?php

if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
    echo "SKIP: run composer install first\n";
    exit(0);
}

require __DIR__ . '/../vendor/autoload.php';

use think\facade\Config;
use Youvision\ThinkLayuiHelper\Service;
use Youvision\ThinkLayuiHelper\LayuiTree;

$failed = 0;

function check(string $name, $actual, $expected): void
{
    global $failed;
    if ($actual === $expected) {
        echo "PASS {$name}\n";
        return;
    }
    $failed++;
    echo "FAIL {$name}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
}

$app = new \think\App(dirname(__DIR__));
$config = new \think\Config($app->getConfigPath());
$config->load('layui_helper', 'layui_helper');
$app->instance('config', $config);

check('package config loaded', Config::get('layui_helper'), ['id' => 'id', 'pid' => 'pid', 'title' => 'title']);

$tree = new LayuiTree();
check('tree reads config', [$tree->pk, $tree->pidname, $tree->title], ['id', 'pid', 'title']);

Config::set(['id' => 'cid', 'pid' => 'parent_id', 'title' => 'name'], 'layui_helper');
$custom = new LayuiTree();
check('tree reads customized config', [$custom->pk, $custom->pidname, $custom->title], ['cid', 'parent_id', 'name']);

$fresh = new \think\Config($app->getConfigPath());
$app->instance('config', $fresh);
(new Service($app))->register();
check('service fills defaults', Config::get('layui_helper'), ['id' => 'id', 'pid' => 'pid', 'title' => 'title']);

Config::set(['title' => 'label'], 'layui_helper');
(new Service($app))->register();
$config = Config::get('layui_helper');
check('service keeps user config', $config['title'], 'label');
check('service fills missing keys', [$config['id'], $config['pid']], ['id', 'pid']);

echo $failed === 0 ? "\nALL PASSED\n" : "\n{$failed} FAILED\n";
exit($failed === 0 ? 0 : 1);