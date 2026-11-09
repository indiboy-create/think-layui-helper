<?php

/**
 * 无第三方依赖的自测脚本：php tests/layui_tree_test.php
 */

require __DIR__ . '/../src/LayuiTree.php';

use Youvision\ThinkLayuiHelper\LayuiTree;

$list = [
    ['id' => 1, 'pid' => 0, 'name' => '一级栏目一', 'title' => '一级栏目一'],
    ['id' => 2, 'pid' => 0, 'name' => '一级栏目二', 'title' => '一级栏目二'],
    ['id' => 3, 'pid' => 1, 'name' => '二级栏目一', 'title' => '二级栏目一'],
    ['id' => 4, 'pid' => 1, 'name' => '二级栏目二', 'title' => '二级栏目二'],
    ['id' => 5, 'pid' => 2, 'name' => '二级栏目三', 'title' => '二级栏目三'],
    ['id' => 6, 'pid' => 3, 'name' => '三级栏目一', 'title' => '三级栏目一'],
];

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

$tree = new LayuiTree();
$tree->init($list);

$child = $tree->getChild(1);
check('getChild count', count($child), 2);
check('getChild keys', array_keys($child), [3, 4]);

check('getChildrenIds(1)', $tree->getChildrenIds(1), [3, 6, 4]);
check('getChildrenIds(1, true)', $tree->getChildrenIds(1, true), [1, 3, 6, 4]);
check('getParentsIds(6)', $tree->getParentsIds(6), [1, 3]);
check('getParentsIds(6, true)', $tree->getParentsIds(6, true), [1, 3, 6]);
check('getParent(6)', $tree->getParent(6)[0]['id'], 3);
check('getParent(1) empty', $tree->getParent(1), []);

$data = $tree->getTreeArray(0);
check('tree root count', count($data), 2);
check('tree first child count', count($data[0]['childlist']), 2);
check('tree last icon spacer', $data[0]['childlist'][1]['spacer'], $tree->nbsp . '└');
check('tree first icon spacer', $data[0]['childlist'][0]['spacer'], $tree->nbsp . '├');

$rows = $tree->getTreeList($data, 'name');
check('treeList row count', count($rows), 6);
check('treeList haschild', $rows[0]['haschild'], 1);
check('treeList leaf haschild', $rows[2]['haschild'], 0);
check('treeList name prefixed', strpos($rows[1]['name'], '│') !== false || strpos($rows[1]['name'], '├') !== false, true);

$nested = (new LayuiTree())->init($list)->multipleChild();
check('multipleChild root count', count($nested), 2);
check('multipleChild children key', isset($nested[0]['childlist']), true);
check('multipleChild nested', isset($nested[0]['childlist'][0]['childlist']), true);
check('multipleChild leaf empty', array_key_exists('childlist', $nested[0]['childlist'][1]), false);

$custom = (new LayuiTree(['pk' => 'cid', 'pidname' => 'parent_id', 'childname' => 'children']))
    ->init([['cid' => 1, 'parent_id' => 0, 'title' => 'A']])->multipleChild();
check('custom fields', $custom[0]['cid'], 1);
check('custom child key', array_key_exists('children', $custom[0]), true);

$layui = new LayuiTree();
$layui->init($list);

$treeData = $layui->toTree();
check('toTree root count', count($treeData), 2);
check('toTree first title', $treeData[0]['title'], '一级栏目一');
check('toTree children key', isset($treeData[0]['children']), true);
check('toTree nested count', count($treeData[0]['children']), 2);
check('toTree leaf no children', array_key_exists('children', $treeData[0]['children'][1]), false);

$flat = $layui->toTreeTable();
check('toTreeTable count', count($flat), 6);
check('toTreeTable order', array_column($flat, 'id'), [1, 3, 6, 4, 2, 5]);
check('toTreeTable parentId', $flat[1]['parentId'], 1);
check('toTreeTable removes pid', array_key_exists('pid', $flat[1]), false);
check('toTreeTable isParent', $flat[0]['isParent'], true);
check('toTreeTable leaf isParent', $flat[2]['isParent'], false);

$flatPid = $layui->toTreeTable(['pid' => 'pid', 'isParent' => false]);
check('toTreeTable pid option', $flatPid[1]['pid'], 1);
check('toTreeTable isParent off', array_key_exists('isParent', $flatPid[0]), false);

$menu = $layui->toMenu(['filter' => 'menu-demo', 'selected' => 3, 'expanded' => [1]]);
check('menu container', strpos($menu, 'class="layui-menu"') !== false, true);
check('menu filter', strpos($menu, 'lay-filter="menu-demo"') !== false, true);
check('menu group', strpos($menu, 'layui-menu-item-group') !== false, true);
check('menu options', strpos($menu, "lay-options='{\"id\":1,\"type\":\"group\"}'") !== false, true);
check('menu selected', strpos($menu, 'layui-menu-item-checked') !== false, true);
check('menu nested ul', substr_count($menu, '<ul>'), 3);

$nav = $layui->toNav([
    'tree' => true,
    'side' => true,
    'filter' => 'nav-demo',
    'selected' => 6,
    'expanded' => [1],
]);
check('nav tree class', strpos($nav, 'layui-nav layui-nav-tree layui-nav-side') !== false, true);
check('nav filter', strpos($nav, 'lay-filter="nav-demo"') !== false, true);
check('nav child', strpos($nav, 'layui-nav-child') !== false, true);
check('nav selected dd', strpos($nav, '<dd class="layui-this">') !== false, true);
check('nav expanded item', strpos($nav, 'layui-nav-itemed') !== false, true);

$escaped = (new LayuiTree())->init([
    ['id' => 1, 'pid' => 0, 'title' => '<script>alert(1)</script>'],
    ['id' => 2, 'pid' => 1, 'title' => 'A"B\'C'],
])->toMenu();
check('menu escapes tag', strpos($escaped, '<script>') === false, true);
check('menu escapes quote', strpos($escaped, 'A"B\'C') === false, true);

$href = $layui->toNav(['href' => function (array $node) {
    return '/node/' . $node['id'];
}]);
check('nav href callback', strpos($href, 'href="/node/1"') !== false, true);

$rootCheck = (new LayuiTree())->init([
    ['id' => 1, 'pid' => 0, 'title' => '根一'],
    ['id' => 2, 'pid' => 99, 'title' => '孤儿根'],
    ['id' => 3, 'pid' => 1, 'title' => '子'],
])->toTree();
check('toTree orphan as root', array_column($rootCheck, 'id'), [1, 2]);

$strictRoot = (new LayuiTree())->init([
    ['id' => 1, 'pid' => 0, 'title' => '根一'],
    ['id' => 2, 'pid' => 99, 'title' => '孤儿根'],
    ['id' => 3, 'pid' => 1, 'title' => '子'],
])->toTree(['root' => 0]);
check('toTree explicit root', array_column($strictRoot, 'id'), [1]);

$config = require __DIR__ . '/../config/layui_helper.php';
check('config file keys', array_keys($config), ['id', 'pid', 'title', 'children']);
check('config defaults', $config, ['id' => 'id', 'pid' => 'pid', 'title' => 'title', 'children' => 'children']);
check('default children name', (new LayuiTree())->childrenname, 'children');

$customChildren = (new LayuiTree(['children' => 'nodes']))->init([
    ['id' => 1, 'pid' => 0, 'title' => '根'],
    ['id' => 2, 'pid' => 1, 'title' => '子'],
]);
check('custom children name', array_key_exists('nodes', $customChildren->toTree()[0]), true);
check('children option overrides config', array_key_exists('items', $customChildren->toTree(['children' => 'items'])[0]), true);

$mapped = new LayuiTree(['id' => 'cid', 'pid' => 'parent_id', 'title' => 'label']);
$mapped->init([
    ['cid' => 1, 'parent_id' => 0, 'label' => '节点A'],
    ['cid' => 2, 'parent_id' => 1, 'label' => '节点B'],
]);
check('mapped pk', $mapped->pk, 'cid');
check('mapped pidname', $mapped->pidname, 'parent_id');
check('mapped title', $mapped->title, 'label');
check('mapped toTree title', $mapped->toTree()[0]['label'], '节点A');
check('mapped toTree normalized id', $mapped->toTree()[0]['id'], 1);
check('mapped toTree normalized title', $mapped->toTree()[0]['title'], '节点A');
check('mapped toTreeTable parentId', $mapped->toTreeTable()[1]['parentId'], 1);
check('mapped toTreeTable normalized id', $mapped->toTreeTable()[0]['id'], 1);
check('mapped toTreeTable normalized title', $mapped->toTreeTable()[0]['title'], '节点A');
check('mapped menu title', strpos($mapped->toMenu(), '节点A') !== false, true);
check('mapped nav title', strpos($mapped->toNav(), '节点A') !== false, true);

$initMapped = new LayuiTree();
$initMapped->init([['cid' => 1, 'parent_id' => 0, 'label' => 'A']], 'parent_id', null, 'cid', null, 'label');
check('init pk option', $initMapped->pk, 'cid');
check('init title option', $initMapped->title, 'label');
check('getTreeList uses configured title', array_key_exists('label', $initMapped->getTreeList($initMapped->getTreeArray(0))[0]), true);

$cycle = (new LayuiTree())->init([
    ['id' => 1, 'pid' => 2, 'title' => 'A'],
    ['id' => 2, 'pid' => 1, 'title' => 'B'],
]);
check('cycle getChildren stops', array_column($cycle->getChildren(1), 'id'), [2]);
check('cycle getChildren withself', array_column($cycle->getChildren(1, true), 'id'), [1, 2]);
check('cycle getParents stops', array_column($cycle->getParents(1), 'id'), [2]);
check('cycle getParents withself', array_column($cycle->getParents(1, true), 'id'), [2, 1]);

echo $failed === 0 ? "\nALL PASSED\n" : "\n{$failed} FAILED\n";
exit($failed === 0 ? 0 : 1);