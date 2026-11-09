<?php

/**
 * LayuiTable 自测脚本：php tests/layui_table_test.php
 */

require __DIR__ . '/../src/LayuiTable.php';

use Youvision\ThinkLayuiHelper\LayuiTable;

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

$rows = [
    ['id' => 1, 'title' => 'A'],
    ['id' => 2, 'title' => 'B'],
];

check('response default', LayuiTable::response($rows), [
    'code' => 0,
    'msg' => '',
    'count' => 2,
    'data' => $rows,
]);

check('response custom count', LayuiTable::response($rows, 100)['count'], 100);

$custom = LayuiTable::response($rows, 100, [
    'statusName' => 'status',
    'statusCode' => 200,
    'msgName' => 'message',
    'countName' => 'total',
    'dataName' => 'rows',
    'msg' => 'ok',
]);
check('response custom keys', array_keys($custom), ['status', 'message', 'total', 'rows']);
check('response custom values', [$custom['status'], $custom['message'], $custom['total']], [200, 'ok', 100]);

check('response error', LayuiTable::error('失败'), ['code' => 1, 'msg' => '失败']);

$page = LayuiTable::page(['page' => '3', 'limit' => '20'], ['maxLimit' => 100]);
check('page parse', $page, ['page' => 3, 'limit' => 20, 'offset' => 40]);

$pageMax = LayuiTable::page(['page' => '0', 'limit' => '9999'], ['maxLimit' => 100]);
check('page boundary', $pageMax, ['page' => 1, 'limit' => 100, 'offset' => 0]);

$pageDefault = LayuiTable::page(['page' => '2'], ['limit' => 15]);
check('page default limit', $pageDefault, ['page' => 2, 'limit' => 15, 'offset' => 15]);

$sort = LayuiTable::sort(['id', 'title'], ['query' => ['field' => 'title', 'order' => 'DESC']]);
check('sort parse', $sort, ['field' => 'title', 'order' => 'desc']);

$sortDenied = LayuiTable::sort(['id'], ['query' => ['field' => 'password', 'order' => 'asc']]);
check('sort whitelist', $sortDenied, ['field' => '', 'order' => '']);

$sortDefault = LayuiTable::sort(['id'], ['query' => [], 'defaultField' => 'id', 'defaultOrder' => 'asc']);
check('sort default', $sortDefault, ['field' => 'id', 'order' => 'asc']);

$where = LayuiTable::where([
    'page' => 2,
    'limit' => 10,
    'field' => 'id',
    'order' => 'desc',
    'keyword' => 'abc',
]);
check('where parse', $where, ['keyword' => 'abc']);

$cols = LayuiTable::columns([
    'id',
    ['field' => 'title', 'width' => 200],
    ['type' => 'checkbox', 'fixed' => 'left'],
]);
check('columns wrapper', count($cols), 1);
check('columns string field', $cols[0][0], ['field' => 'id', 'title' => 'id']);
check('columns title default', $cols[0][1]['title'], 'title');
check('columns type', $cols[0][2]['type'], 'checkbox');

$column = LayuiTable::column('status', '状态', ['sort' => true]);
check('column', $column, ['field' => 'status', 'title' => '状态', 'sort' => true]);
check('column title default', LayuiTable::column('name')['title'], 'name');

$group = LayuiTable::columns([
    ['title' => '地址', 'children' => [
        ['field' => 'province'],
        ['field' => 'city', 'width' => 100],
    ]],
]);
check('columns children count', count($group[0][0]['children']), 2);
check('columns children title', $group[0][0]['children'][0]['title'], 'province');

$config = LayuiTable::render([
    'elem' => '#table-demo',
    'cols' => ['id', 'title'],
]);
check('render cols wrapped', $config['cols'], [[
    ['field' => 'id', 'title' => 'id'],
    ['field' => 'title', 'title' => 'title'],
]]);
check('render defaults', [$config['page'], $config['limit'], $config['cellMinWidth']], [true, 10, 80]);
check('render limits', count($config['limits']), 5);

$config2 = LayuiTable::render([
    'cols' => [[
        ['field' => 'id', 'title' => 'ID'],
    ]],
]);
check('render keeps two dimensional cols', $config2['cols'][0][0]['title'], 'ID');

$script = LayuiTable::script(['elem' => '#table-demo', 'cols' => ['id']]);
check('script module', strpos($script, "layui.use('table'") !== false, true);
check('script render', strpos($script, 'table.render(') !== false, true);
check('script json', strpos($script, '"#table-demo"') !== false, true);

$unsafe = LayuiTable::script([
    'elem' => '#x',
    'title' => '</script><script>alert(1)</script>',
]);
check('script escapes closing tag', strpos($unsafe, '</script>') === false, true);

$single = LayuiTable::columns(['field' => 'id', 'title' => 'ID']);
check('columns single column', $single, [[['field' => 'id', 'title' => 'ID']]]);

$singleConfig = LayuiTable::render(['cols' => ['field' => 'id', 'title' => 'ID']]);
check('render single column', $singleConfig['cols'], [[['field' => 'id', 'title' => 'ID']]]);

echo $failed === 0 ? "\nALL PASSED\n" : "\n{$failed} FAILED\n";
exit($failed === 0 ? 0 : 1);