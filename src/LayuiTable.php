<?php

namespace Youvision\ThinkLayuiHelper;

use think\Container;
use think\facade\Request;

class LayuiTable
{
    /**
     * Layui table 默认异步数据字段
     */
    public const DEFAULT_RESPONSE = [
        'statusName' => 'code',
        'statusCode' => 0,
        'msgName'    => 'msg',
        'countName'  => 'count',
        'dataName'   => 'data',
    ];

    /**
     * 生成 table 异步数据响应
     *
     * @param mixed      $data    数组、Collection、ThinkPHP 分页对象
     * @param int|null   $count   总条数，不传时自动计算
     * @param array      $options 自定义响应字段：statusName / statusCode / msgName / countName / dataName / msg
     * @return array
     */
    public static function response($data = [], ?int $count = null, array $options = []): array
    {
        $options = array_merge(self::DEFAULT_RESPONSE, $options);

        if (is_object($data)) {
            if (method_exists($data, 'total') && method_exists($data, 'items')) {
                $count = $count ?? (int) $data->total();
                $data  = $data->items();
            } elseif (method_exists($data, 'toArray')) {
                $data = $data->toArray();
            }
        }

        if ($data instanceof \Traversable) {
            $data = iterator_to_array($data);
        }

        if (!is_array($data)) {
            $data = (array) $data;
        }

        if (is_null($count)) {
            $count = count($data);
        }

        $items = [];
        foreach (array_values($data) as $item) {
            $items[] = is_object($item) && method_exists($item, 'toArray') ? $item->toArray() : $item;
        }

        return [
            $options['statusName'] => $options['statusCode'],
            $options['msgName']    => $options['msg'] ?? '',
            $options['countName']  => (int) $count,
            $options['dataName']   => $items,
        ];
    }

    /**
     * 生成 table 错误响应
     *
     * @param string $msg
     * @param int    $code
     * @param array  $options
     * @return array
     */
    public static function error(string $msg = '操作失败', int $code = 1, array $options = []): array
    {
        $options = array_merge(self::DEFAULT_RESPONSE, $options);

        return [
            $options['statusName'] => $code,
            $options['msgName']    => $msg,
        ];
    }

    /**
     * 解析 table 分页参数
     *
     * @param array $query   请求参数，传空数组时自动读取当前请求
     * @param array $options pageName / limitName / page / limit / maxLimit
     * @return array page / limit / offset
     */
    public static function page(array $query = [], array $options = []): array
    {
        $query     = $query ?: static::query();
        $pageName  = $options['pageName'] ?? 'page';
        $limitName = $options['limitName'] ?? 'limit';
        $page      = max(1, (int) ($query[$pageName] ?? $options['page'] ?? 1));
        $limit     = (int) ($query[$limitName] ?? $options['limit'] ?? 10);
        $maxLimit  = max(1, (int) ($options['maxLimit'] ?? 1000));
        $limit     = max(1, min($limit, $maxLimit));

        return [
            'page'   => $page,
            'limit'  => $limit,
            'offset' => ($page - 1) * $limit,
        ];
    }

    /**
     * 解析 table 排序参数
     *
     * @param array $allowed 允许排序的字段白名单，为空时不限制
     * @param array $options query / fieldName / orderName / defaultField / defaultOrder
     * @return array field / order
     */
    public static function sort(array $allowed = [], array $options = []): array
    {
        $query      = array_key_exists('query', $options) ? (array) $options['query'] : static::query();
        $fieldName  = $options['fieldName'] ?? 'field';
        $orderName  = $options['orderName'] ?? 'order';
        $field      = (string) ($query[$fieldName] ?? $options['defaultField'] ?? '');
        $order      = strtolower((string) ($query[$orderName] ?? $options['defaultOrder'] ?? ''));

        if ($field !== '' && $allowed && !in_array($field, $allowed, true)) {
            $field = '';
        }

        if (!in_array($order, ['asc', 'desc'], true) || $field === '') {
            $order = '';
        }

        return [
            'field' => $field,
            'order' => $order,
        ];
    }

    /**
     * 提取 table 搜索参数（排除分页、排序参数）
     *
     * @param array $query  请求参数，传空数组时自动读取当前请求
     * @param array $except 需要排除的字段
     * @return array
     */
    public static function where(array $query = [], array $except = ['page', 'limit', 'field', 'order']): array
    {
        $query = $query ?: static::query();

        return array_diff_key($query, array_flip($except));
    }

    /**
     * 生成 cols 二维列配置
     *
     * @param array $columns 支持字符串简写、列数组、多级表头 children
     * @param array $defaults 列默认属性
     * @return array
     */
    public static function columns(array $columns, array $defaults = []): array
    {
        if ($columns && !static::isList($columns)) {
            $columns = [$columns];
        }

        return [static::normalizeRow($columns, $defaults)];
    }

    /**
     * 判断数组是否为索引列表
     *
     * @param array $array
     * @return bool
     */
    protected static function isList(array $array): bool
    {
        return $array === [] || array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * 生成单个列配置
     *
     * @param string $field
     * @param string $title
     * @param array  $options
     * @return array
     */
    public static function column(string $field, string $title = '', array $options = []): array
    {
        $column = ['field' => $field];
        if ($title !== '') {
            $column['title'] = $title;
        }

        $column = array_merge($column, $options);

        if (!isset($column['title'])) {
            $column['title'] = $field;
        }

        return $column;
    }

    /**
     * 生成 table.render 配置数组
     *
     * @param array $options
     * @return array
     */
    public static function render(array $options = []): array
    {
        $config = array_merge([
            'page'         => true,
            'limit'        => 10,
            'limits'       => [10, 20, 30, 40, 50],
            'cellMinWidth' => 80,
        ], $options);

        if (isset($config['cols']) && is_array($config['cols']) && $config['cols']) {
            if (!static::isList($config['cols'])) {
                $config['cols'] = static::columns([$config['cols']]);
            } else {
                $first = reset($config['cols']);
                if (is_array($first) && isset($first[0]) && is_array($first[0])) {
                    $rows = [];
                    foreach ($config['cols'] as $row) {
                        $rows[] = static::normalizeRow((array) $row);
                    }
                    $config['cols'] = $rows;
                } else {
                    $config['cols'] = static::columns($config['cols']);
                }
            }
        }

        return $config;
    }

    /**
     * 生成 table.render 的 JS 调用片段
     *
     * @param array $options 仅支持可 JSON 序列化的配置，不支持 JS 回调函数
     * @return string
     */
    public static function script(array $options = []): string
    {
        $json = json_encode(static::render($options), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false) {
            $json = '{}';
        }

        return "layui.use('table', function () {\n"
            . "  var table = layui.table;\n"
            . "  table.render({$json});\n"
            . '});';
    }

    /**
     * 规范化一行列配置
     *
     * @param array $columns
     * @param array $defaults
     * @return array
     */
    protected static function normalizeRow(array $columns, array $defaults = []): array
    {
        $row = [];

        foreach ($columns as $column) {
            if (is_string($column)) {
                $column = ['field' => $column];
            }
            $row[] = static::normalizeColumn((array) $column, $defaults);
        }

        return $row;
    }

    /**
     * 规范化单个列配置
     *
     * @param array $column
     * @param array $defaults
     * @return array
     */
    protected static function normalizeColumn(array $column, array $defaults = []): array
    {
        $column = array_merge($defaults, $column);

        if (!empty($column['children'])) {
            $children = [];
            foreach ($column['children'] as $child) {
                $children[] = static::normalizeColumn((array) $child, $defaults);
            }
            $column['children'] = $children;

            return $column;
        }

        $type = $column['type'] ?? '';
        if (in_array($type, ['checkbox', 'radio', 'numbers', 'space'], true)) {
            return $column;
        }

        if (!isset($column['title']) && isset($column['field'])) {
            $column['title'] = $column['field'];
        }

        return $column;
    }

    /**
     * 获取当前请求参数
     *
     * @return array
     */
    protected static function query(): array
    {
        if (class_exists(Request::class) && Container::getInstance()->has('request')) {
            return (array) Request::param();
        }

        return array_merge($_GET ?? [], $_POST ?? []);
    }
}