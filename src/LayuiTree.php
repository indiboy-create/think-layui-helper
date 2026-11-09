<?php

namespace Youvision\ThinkLayuiHelper;

use think\Container;
use think\facade\Config;

/**
 * 树形结构工具类（ThinkPHP）
 *
 * 从 config/layui_helper.php 读取 id / pid / title / children 字段配置，
 * 将扁平数据转换为 Layui tree / treeTable / menu / nav 所需格式。
 *
 * 用法：
 *   $tree = LayuiTree::instance()->init($list);
 *   $data = $tree->toTree();
 */
class LayuiTree
{
    /**
     * 实例
     * @var ?LayuiTree
     */
    protected static ?LayuiTree $instance = null;

    /**
     * 生成树型结构所需要的2维数组
     * @var array
     */
    public array $arr = [];

    /**
     * 子级数据（树枝）
     * @var array
     */
    protected array $children = [];

    /**
     * 生成树型结构所需修饰符号
     * @var array
     */
    public array $icon = array('│', '├', '└');

    /**
     * 父级字段名称
     * @var string
     */
    public string $pidname = 'pid';

    /**
     * 主键字段名称
     * @var string
     */
    public string $pk = 'id';

    /**
     * 名称字段名称
     * @var string
     */
    public string $title = 'title';

    /**
     * 空格占位符
     * @var string
     */
    public string $nbsp = "&nbsp;";

    /**
     * 子级字段名称
     * @var string
     */
    public string $childname = 'childlist';

    /**
     * toTree 输出的子级字段名称
     * @var string
     */
    public string $childrenname = 'children';

    /**
     * @param array $options 覆盖配置项：id / pid / title / children，兼容旧参数 pk / pidname / childname，另支持 icon / nbsp
     */
    public function __construct(array $options = [])
    {
        $config = [];
        if (class_exists(Config::class) && Container::getInstance()->has('config')) {
            $config = (array) Config::get('layui_helper', []);
        }

        $this->pk           = $options['pk'] ?? $options['id'] ?? $config['id'] ?? $this->pk;
        $this->pidname      = $options['pidname'] ?? $options['pid'] ?? $config['pid'] ?? $this->pidname;
        $this->title        = $options['title'] ?? $config['title'] ?? $this->title;
        $this->childrenname = $options['children'] ?? $config['children'] ?? $this->childrenname;

        isset($options['icon']) && $this->icon = $options['icon'];
        isset($options['childname']) && $this->childname = $options['childname'];
        isset($options['nbsp']) && $this->nbsp = $options['nbsp'];
    }

    /**
     * 获取单例
     *
     * @param array $options 配置项，仅首次实例化时生效
     * @return LayuiTree
     */
    public static function instance(array $options = []): LayuiTree
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }

        return self::$instance;
    }

    /**
     * 初始化数据
     *
     * @param array       $arr       2维数组，例如：
     *     [
     *       ['id' => 1, 'pid' => 0, 'title' => '一级栏目一'],
     *       ['id' => 2, 'pid' => 0, 'title' => '一级栏目二'],
     *       ['id' => 3, 'pid' => 1, 'title' => '二级栏目一'],
     *       ['id' => 4, 'pid' => 1, 'title' => '二级栏目二'],
     *     ]
     * @param string|null $pidname   父字段名称
     * @param string|null $nbsp      空格占位符
     * @param string|null $pk        主键字段名称
     * @param string|null $childname 子级字段名称
     * @param string|null $title     名称字段名称
     * @return LayuiTree
     */
    public function init(array $arr = [], ?string $pidname = null, ?string $nbsp = null, ?string $pk = null, ?string $childname = null, ?string $title = null): LayuiTree
    {
        $this->arr = $arr;

        if (!is_null($pidname)) {
            $this->pidname = $pidname;
        }
        if (!is_null($nbsp)) {
            $this->nbsp = $nbsp;
        }
        if (!is_null($pk)) {
            $this->pk = $pk;
        }
        if (!is_null($childname)) {
            $this->childname = $childname;
        }
        if (!is_null($title)) {
            $this->title = $title;
        }

        return $this;
    }

    /**
     * 得到子级数组
     *
     * @param int|string $myid 父级ID
     * @return array
     */
    public function getChild($myid): array
    {
        $newarr = [];

        foreach ($this->arr as $value) {
            if (!isset($value[$this->pk], $value[$this->pidname])) {
                continue;
            }
            if ($value[$this->pidname] == $myid) {
                $newarr[$value[$this->pk]] = $value;
            }
        }

        return $newarr;
    }

    /**
     * 读取指定节点的所有孩子节点
     *
     * @param int|string $myid     节点ID
     * @param bool       $withself 是否包含自身
     * @return array
     */
    public function getChildren($myid, bool $withself = false): array
    {
        $visited  = [(string) $myid => true];
        $children = $this->collectChildren($myid, $visited);

        if (!$withself) {
            return $children;
        }

        foreach ($this->arr as $value) {
            if (isset($value[$this->pk], $value[$this->pidname]) && (string) $value[$this->pk] === (string) $myid) {
                return array_merge([$value], $children);
            }
        }

        return $children;
    }

    /**
     * 递归收集子节点，visited 用于防止异常数据的循环引用
     *
     * @param int|string $myid
     * @param array      $visited
     * @return array
     */
    protected function collectChildren($myid, array $visited): array
    {
        $newarr = [];

        foreach ($this->arr as $value) {
            if (!isset($value[$this->pk], $value[$this->pidname])) {
                continue;
            }
            $id = (string) $value[$this->pk];
            if ((string) $value[$this->pidname] !== (string) $myid || isset($visited[$id])) {
                continue;
            }
            $visited[$id] = true;
            $newarr[]     = $value;
            $newarr       = array_merge($newarr, $this->collectChildren($value[$this->pk], $visited));
        }

        return $newarr;
    }

    /**
     * 读取指定节点的所有孩子节点ID
     *
     * @param int|string $myid     节点ID
     * @param bool       $withself 是否包含自身
     * @return array
     */
    public function getChildrenIds($myid, bool $withself = false): array
    {
        $childrenids = [];

        foreach ($this->getChildren($myid, $withself) as $value) {
            $childrenids[] = $value[$this->pk];
        }

        return $childrenids;
    }

    /**
     * 得到当前位置父辈数组
     *
     * @param int|string $myid 节点ID
     * @return array
     */
    public function getParent($myid): array
    {
        $pid    = 0;
        $newarr = [];

        foreach ($this->arr as $value) {
            if (!isset($value[$this->pk], $value[$this->pidname])) {
                continue;
            }
            if ($value[$this->pk] == $myid) {
                $pid = $value[$this->pidname];
                break;
            }
        }

        if ($pid) {
            foreach ($this->arr as $value) {
                if (isset($value[$this->pk]) && $value[$this->pk] == $pid) {
                    $newarr[] = $value;
                    break;
                }
            }
        }

        return $newarr;
    }

    /**
     * 得到当前位置所有父辈数组
     *
     * @param int|string $myid     节点ID
     * @param bool       $withself 是否包含自己
     * @return array
     */
    public function getParents($myid, bool $withself = false): array
    {
        return $this->collectParents($myid, $withself, []);
    }

    /**
     * 递归收集父节点，visited 用于防止异常数据的循环引用
     *
     * @param int|string $myid
     * @param bool       $withself
     * @param array      $visited
     * @return array
     */
    protected function collectParents($myid, bool $withself, array $visited): array
    {
        $visited[(string) $myid] = true;
        $self = null;

        foreach ($this->arr as $value) {
            if (!isset($value[$this->pk], $value[$this->pidname])) {
                continue;
            }
            if ((string) $value[$this->pk] === (string) $myid) {
                $self = $value;
                break;
            }
        }

        if (is_null($self)) {
            return [];
        }

        $parents = [];
        if ($withself) {
            $parents[] = $self;
        }

        $pid = $self[$this->pidname];
        if ($pid && !isset($visited[(string) $pid])) {
            $parents = array_merge($this->collectParents($pid, true, $visited), $parents);
        }

        return $parents;
    }

    /**
     * 读取指定节点所有父类节点ID
     *
     * @param int|string $myid     节点ID
     * @param bool       $withself 是否包含自身
     * @return array
     */
    public function getParentsIds($myid, bool $withself = false): array
    {
        $parentsids = [];

        foreach ($this->getParents($myid, $withself) as $value) {
            $parentsids[] = $value[$this->pk];
        }

        return $parentsids;
    }

    /**
     * 获取树状数组（含 spacer 前缀与 childlist 子级）
     *
     * @param int|string $myid       要查询的ID
     * @param string     $itemprefix 前缀
     * @return array
     */
    public function getTreeArray($myid, string $itemprefix = ''): array
    {
        $childs = $this->getChild($myid);
        $data   = [];
        $number = 1;

        if ($childs) {
            $total = count($childs);
            foreach ($childs as $id => $value) {
                $j = $k = '';
                if ($number == $total) {
                    $j .= $this->icon[2];
                    $k = $itemprefix ? $this->nbsp : '';
                } else {
                    $j .= $this->icon[1];
                    $k = $itemprefix ? $this->icon[0] : '';
                }
                $value['spacer'] = $itemprefix ? $itemprefix . $j : '';
                $data[]          = $value;
                $index           = count($data) - 1;
                $data[$index][$this->childname] = $this->getTreeArray($id, $itemprefix . $k . $this->nbsp);
                $number++;
            }
        }

        return $data;
    }

    /**
     * 将 getTreeArray 的结果返回为二维数组
     *
     * @param array       $data  树状数组
     * @param string|null $field 需要加上 spacer 前缀的字段，默认使用配置的 title
     * @return array
     */
    public function getTreeList(array $data = [], ?string $field = null): array
    {
        $field = $field ?? $this->title;
        $arr   = [];

        foreach ($data as $value) {
            $childlist = $value[$this->childname] ?? [];
            unset($value[$this->childname]);
            $value[$field]     = ($value['spacer'] ?? '') . ' ' . ($value[$field] ?? '');
            $value['haschild'] = $childlist ? 1 : 0;
            if (!empty($value[$this->pk])) {
                $arr[] = $value;
            }
            if ($childlist) {
                $arr = array_merge($arr, $this->getTreeList($childlist, $field));
            }
        }

        return $arr;
    }

    /**
     * 递归的根据指定字段组装 children 数组（多维数组）
     *
     * @return array
     */
    public function multipleChild(): array
    {
        $data           = $this->arr;
        $pks            = [];
        $topLevelData   = [];
        $this->children = [];

        foreach ($data as $item) {
            if (!isset($item[$this->pk], $item[$this->pidname])) {
                continue;
            }
            $pks[] = $item[$this->pk];
            $this->children[$item[$this->pidname]][] = $item;
        }

        foreach ($data as $item) {
            if (!isset($item[$this->pk], $item[$this->pidname])) {
                continue;
            }
            if (!in_array($item[$this->pidname], $pks)) {
                $topLevelData[] = $item;
            }
        }

        if (count($this->children) > 0) {
            foreach ($topLevelData as $key => $item) {
                $topLevelData[$key][$this->childname] = $this->getMultipleChildren($this->children[$item[$this->pk]] ?? []);
            }

            return $topLevelData;
        }

        return $data;
    }

    /**
     * 获取 children 数组，辅助 multipleChild 组装子级
     *
     * @param array $data
     * @return array
     */
    protected function getMultipleChildren(array $data): array
    {
        if (!$data) {
            return [];
        }

        foreach ($data as $key => $item) {
            if (isset($item[$this->pk]) && array_key_exists($item[$this->pk], $this->children)) {
                $data[$key][$this->childname] = $this->getMultipleChildren($this->children[$item[$this->pk]]);
            }
        }

        return $data;
    }

    /**
     * 转换为 Layui tree 组件所需的数据（嵌套 children）
     *
     * 输入字段：id / pid / title，输出默认使用 id / title / children。
     *
     * @param array $options 支持 root（根节点 pid，默认按孤儿节点识别）、children（子级字段名，默认取配置）
     * @return array
     */
    public function toTree(array $options = []): array
    {
        $childrenName   = $options['children'] ?? $this->childrenname;
        $root           = $options['root'] ?? null;
        [$groups, $pks] = $this->groupByPid();

        return $this->buildLayuiTree($this->treeRoots($root, $pks), $groups, $childrenName);
    }

    /**
     * 转换为 Layui treeTable 组件所需的平铺数据
     *
     * 默认把 pid 改写为 Layui 默认的 parentId，并按深度优先排序（父节点在前）。
     *
     * @param array $options 支持 root、pid（输出父级字段名，默认 parentId）、isParent（false 时不再输出）
     * @return array
     */
    public function toTreeTable(array $options = []): array
    {
        $pidField    = $options['pid'] ?? 'parentId';
        $root        = $options['root'] ?? null;
        $isParentKey = $options['isParent'] ?? 'isParent';
        [$groups, $pks] = $this->groupByPid();

        $rows = [];
        $this->walkLayuiTreeTable($this->treeRoots($root, $pks), $groups, $pidField, $isParentKey, $rows);

        return $rows;
    }

    /**
     * 转换为 Layui menu 组件的 HTML 结构（ul.layui-menu）
     *
     * @param array $options 支持 id、filter、class、href、selected、expanded、escape
     * @return string
     */
    public function toMenu(array $options = []): string
    {
        $options = array_merge([
            'id'       => null,
            'filter'   => null,
            'class'    => '',
            'href'     => 'javascript:;',
            'selected' => null,
            'expanded' => false,
            'escape'   => true,
        ], $options);

        $attrs = ['class="' . $this->html(trim('layui-menu ' . $options['class']), (bool) $options['escape']) . '"'];
        if (!is_null($options['id']) && $options['id'] !== '') {
            $attrs[] = 'id="' . $this->html((string) $options['id'], (bool) $options['escape']) . '"';
        }
        if (!is_null($options['filter']) && $options['filter'] !== '') {
            $attrs[] = 'lay-filter="' . $this->html((string) $options['filter'], (bool) $options['escape']) . '"';
        }

        return '<ul ' . implode(' ', $attrs) . ">\n"
            . $this->renderLayuiMenu($this->toTree(['children' => '__children']), 1, $options)
            . "</ul>\n";
    }

    /**
     * 转换为 Layui nav 组件的 HTML 结构（ul.layui-nav）
     *
     * nav 仅支持两级菜单，更深层级的节点会平铺到第二级。
     *
     * @param array $options 支持 id、filter、class、href、selected、expanded、escape、tree、side、accordion
     * @return string
     */
    public function toNav(array $options = []): string
    {
        $options = array_merge([
            'id'        => null,
            'filter'    => null,
            'class'     => '',
            'href'      => 'javascript:;',
            'selected'  => null,
            'expanded'  => false,
            'escape'    => true,
            'tree'      => false,
            'side'      => false,
            'accordion' => false,
        ], $options);

        $class = 'layui-nav';
        if ($options['tree']) {
            $class .= ' layui-nav-tree';
        }
        if ($options['side']) {
            $class .= ' layui-nav-side';
        }

        $attrs = ['class="' . $this->html(trim($class . ' ' . $options['class']), (bool) $options['escape']) . '"'];
        if (!is_null($options['id']) && $options['id'] !== '') {
            $attrs[] = 'id="' . $this->html((string) $options['id'], (bool) $options['escape']) . '"';
        }
        if (!is_null($options['filter']) && $options['filter'] !== '') {
            $attrs[] = 'lay-filter="' . $this->html((string) $options['filter'], (bool) $options['escape']) . '"';
        }
        if ($options['accordion']) {
            $attrs[] = 'lay-accordion';
        }

        return '<ul ' . implode(' ', $attrs) . ">\n"
            . $this->renderLayuiNav($this->toTree(['children' => '__children']), 1, $options)
            . "</ul>\n";
    }

    /**
     * 按父级 pid 分组，并记录全部主键
     *
     * @return array
     */
    protected function groupByPid(): array
    {
        $groups = [];
        $pks    = [];

        foreach ($this->arr as $item) {
            if (!isset($item[$this->pk], $item[$this->pidname])) {
                continue;
            }
            $pks[(string) $item[$this->pk]]           = true;
            $groups[(string) $item[$this->pidname]][] = $item;
        }

        return [$groups, $pks];
    }

    /**
     * 获取根节点：未指定 root 时，父级不存在的节点即为根节点
     *
     * @param mixed $root
     * @param array $pks
     * @return array
     */
    protected function treeRoots($root, array $pks): array
    {
        $roots = [];

        foreach ($this->arr as $item) {
            if (!isset($item[$this->pk], $item[$this->pidname])) {
                continue;
            }
            $pid = (string) $item[$this->pidname];
            if (is_null($root)) {
                if (!isset($pks[$pid])) {
                    $roots[] = $item;
                }
            } elseif ($pid === (string) $root) {
                $roots[] = $item;
            }
        }

        return $roots;
    }

    /**
     * 递归组装 tree 所需的嵌套数据
     *
     * @param array  $items
     * @param array  $groups
     * @param string $childrenName
     * @return array
     */
    protected function buildLayuiTree(array $items, array $groups, string $childrenName): array
    {
        $nodes = [];

        foreach ($items as $item) {
            $children = $groups[(string) $item[$this->pk]] ?? [];
            $item     = $this->normalizeOutputNode($item);
            if ($children) {
                $item[$childrenName] = $this->buildLayuiTree($children, $groups, $childrenName);
            }
            $nodes[] = $item;
        }

        return $nodes;
    }

    /**
     * 补充 Layui 默认字段，使自定义字段配置也能被前端直接识别
     *
     * @param array $item
     * @return array
     */
    protected function normalizeOutputNode(array $item): array
    {
        if ($this->pk !== 'id' && array_key_exists($this->pk, $item)) {
            $item['id'] = $item[$this->pk];
        }
        if ($this->title !== 'title' && array_key_exists($this->title, $item)) {
            $item['title'] = $item[$this->title];
        }

        return $item;
    }

    /**
     * 递归组装 treeTable 所需的平铺数据
     *
     * @param array  $items
     * @param array  $groups
     * @param string $pidField
     * @param mixed  $isParentKey
     * @param array  $rows
     * @return void
     */
    protected function walkLayuiTreeTable(array $items, array $groups, string $pidField, $isParentKey, array &$rows): void
    {
        foreach ($items as $item) {
            $children = $groups[(string) $item[$this->pk]] ?? [];
            $row      = $this->normalizeOutputNode($item);
            unset($row[$this->pidname]);
            $row[$pidField] = $item[$this->pidname];
            if ($isParentKey) {
                $row[$isParentKey] = $children ? true : false;
            }
            $rows[] = $row;

            if ($children) {
                $this->walkLayuiTreeTable($children, $groups, $pidField, $isParentKey, $rows);
            }
        }
    }

    /**
     * 渲染 menu 列表项
     *
     * @param array $nodes
     * @param int   $level
     * @param array $options
     * @return string
     */
    protected function renderLayuiMenu(array $nodes, int $level, array $options): string
    {
        $html   = '';
        $indent = str_repeat('  ', $level);

        foreach ($nodes as $node) {
            $id         = $node[$this->pk] ?? '';
            $title      = (string) ($node[$this->title] ?? '');
            $children   = $node['__children'] ?? [];
            $class      = [];
            $layOptions = ['id' => $id];

            if ($children) {
                $class[]            = 'layui-menu-item-group';
                $layOptions['type'] = 'group';
                if ($this->isFlagged($id, $options['expanded'])) {
                    $class[] = 'layui-menu-item-down';
                }
            }
            if ($this->isFlagged($id, $options['selected'])) {
                $class[] = 'layui-menu-item-checked';
            }

            $html .= $indent . '<li';
            if ($class) {
                $html .= ' class="' . implode(' ', $class) . '"';
            }
            $html .= " lay-options='" . $this->jsonAttribute($layOptions, (bool) $options['escape']) . "'>\n";
            $html .= $indent . '  <div class="layui-menu-body-title">';

            if ($children) {
                $html .= $this->html($title, (bool) $options['escape']);
            } else {
                $html .= '<a href="' . $this->html($this->nodeHref($node, $options), (bool) $options['escape']) . '">'
                    . $this->html($title, (bool) $options['escape']) . '</a>';
            }

            $html .= "</div>\n";

            if ($children) {
                $html .= $indent . "  <ul>\n"
                    . $this->renderLayuiMenu($children, $level + 2, $options)
                    . $indent . "  </ul>\n";
            }

            $html .= $indent . "</li>\n";
        }

        return $html;
    }

    /**
     * 渲染 nav 一级菜单项
     *
     * @param array $nodes
     * @param int   $level
     * @param array $options
     * @return string
     */
    protected function renderLayuiNav(array $nodes, int $level, array $options): string
    {
        $html   = '';
        $indent = str_repeat('  ', $level);

        foreach ($nodes as $node) {
            $id       = $node[$this->pk] ?? '';
            $title    = (string) ($node[$this->title] ?? '');
            $children = $node['__children'] ?? [];
            $class    = ['layui-nav-item'];

            if ($this->isFlagged($id, $options['selected'])) {
                $class[] = 'layui-this';
            }
            if ($children && $this->isFlagged($id, $options['expanded'])) {
                $class[] = 'layui-nav-itemed';
            }

            $html .= $indent . '<li class="' . implode(' ', $class) . "\">\n";
            $html .= $indent . '  <a href="' . $this->html($this->nodeHref($node, $options), (bool) $options['escape']) . '">'
                . $this->html($title, (bool) $options['escape']) . "</a>\n";

            if ($children) {
                $html .= $indent . "  <dl class=\"layui-nav-child\">\n"
                    . $this->renderLayuiNavChildren($children, $level + 1, $options)
                    . $indent . "  </dl>\n";
            }

            $html .= $indent . "</li>\n";
        }

        return $html;
    }

    /**
     * 渲染 nav 二级及以下菜单项（超出两级的节点平铺到第二级）
     *
     * @param array $nodes
     * @param int   $level
     * @param array $options
     * @return string
     */
    protected function renderLayuiNavChildren(array $nodes, int $level, array $options): string
    {
        $html   = '';
        $indent = str_repeat('  ', $level);

        foreach ($nodes as $node) {
            $id       = $node[$this->pk] ?? '';
            $title    = (string) ($node[$this->title] ?? '');
            $children = $node['__children'] ?? [];

            $html .= $indent . '<dd';
            if ($this->isFlagged($id, $options['selected'])) {
                $html .= ' class="layui-this"';
            }
            $html .= '><a href="' . $this->html($this->nodeHref($node, $options), (bool) $options['escape']) . '">'
                . $this->html($title, (bool) $options['escape']) . "</a></dd>\n";

            if ($children) {
                $html .= $this->renderLayuiNavChildren($children, $level, $options);
            }
        }

        return $html;
    }

    /**
     * 获取节点链接：节点自带 href 优先，其次回调，最后使用统一默认值
     *
     * @param array $node
     * @param array $options
     * @return string
     */
    protected function nodeHref(array $node, array $options): string
    {
        if (isset($node['href']) && $node['href'] !== '') {
            return (string) $node['href'];
        }
        if (is_callable($options['href'])) {
            return (string) call_user_func($options['href'], $node);
        }

        return (string) $options['href'];
    }

    /**
     * 判断节点是否命中选中/展开标记
     *
     * @param mixed $id
     * @param mixed $flag true / false / 单个 id / id 数组
     * @return bool
     */
    protected function isFlagged($id, $flag): bool
    {
        if (is_bool($flag)) {
            return $flag;
        }
        if (is_array($flag)) {
            foreach ($flag as $item) {
                if ((string) $item === (string) $id) {
                    return true;
                }
            }
            return false;
        }
        if (is_null($flag)) {
            return false;
        }

        return (string) $flag === (string) $id;
    }

    /**
     * 编码 lay-options 属性值
     *
     * @param array $options
     * @return string
     */
    protected function encodeOptions(array $options): string
    {
        $json = json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }

    /**
     * 将 JSON 安全地放入单引号包裹的 HTML 属性中
     *
     * @param array $options
     * @param bool  $escape
     * @return string
     */
    protected function jsonAttribute(array $options, bool $escape): string
    {
        $json = $this->encodeOptions($options);
        if (!$escape) {
            return $json;
        }

        return str_replace(
            ['&', "'", '<', '>'],
            ['&amp;', '&#039;', '&lt;', '&gt;'],
            $json
        );
    }

    /**
     * HTML 转义
     *
     * @param string $value
     * @param bool   $escape
     * @return string
     */
    protected function html(string $value, bool $escape): string
    {
        return $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
    }
}