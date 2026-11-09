# youvision/think-layui-helper

ThinkPHP 8 树形结构与 Layui 组件扩展，把扁平数据（默认 `id` / `pid` / `title`）转换为 Layui `tree`、`treeTable`、`table`、`menu`、`nav` 所需的数据、配置或 HTML，也可用于无限极分类。

- 面向 ThinkPHP 8，自动注册服务并提供配置文件
- 字段名可在 `config/layui_helper.php` 中统一配置
- 输出 tree 嵌套数据、treeTable 平铺数据、menu / nav 的 HTML 结构
- 封装 table 的服务端响应、分页/排序参数解析与列配置
- 提供子级、父级、后代、祖先 ID 查询

## 安装

```bash
composer require youvision/think-layui-helper
```

ThinkPHP 8 项目在执行 Composer 脚本时会自动运行 `php think service:discover` 和 `php think vendor:publish`，从而注册服务并发布配置文件。

如果没有自动执行，手动运行：

```bash
php think service:discover
php think vendor:publish
```

## 配置

配置文件发布到项目的 `config/layui_helper.php`，配置键为 `layui_helper`：

```php
<?php

return [
    // 主键字段名
    'id'       => 'id',

    // 父级字段名
    'pid'      => 'pid',

    // 名称字段名
    'title'    => 'title',

    // 子级字段名
    'children' => 'children',
];
```

如果数据表字段不同，例如 `cid` / `parent_id` / `name`：

```php
return [
    'id'       => 'cid',
    'pid'      => 'parent_id',
    'title'    => 'name',
    'children' => 'children',
];
```

`id` / `pid` / `title` 用于读取源数据，`children` 用于指定 `toTree()` 输出的子级字段名（默认 `children`）。

`LayuiTree` 实例化时会自动读取该配置。也可以在运行时覆盖：

```php
$tree = new LayuiTree([
    'id'       => 'cid',
    'pid'      => 'parent_id',
    'title'    => 'name',
    'children' => 'children',
]);
```

或使用单例首次实例化时覆盖：

```php
LayuiTree::instance([
    'id'       => 'cid',
    'pid'      => 'parent_id',
    'title'    => 'name',
    'children' => 'children',
]);
```

配置的字段名用于读取源数据；`toTree()` / `toTreeTable()` 会自动补充 Layui 默认的 `id` / `title` 字段（原字段仍保留），因此前端不需要额外配置 `customName`。

## 快速开始

```php
use Youvision\ThinkLayuiHelper\LayuiTree;

$list = [
    ['id' => 1, 'pid' => 0, 'title' => '一级栏目一'],
    ['id' => 2, 'pid' => 0, 'title' => '一级栏目二'],
    ['id' => 3, 'pid' => 1, 'title' => '二级栏目一'],
    ['id' => 4, 'pid' => 1, 'title' => '二级栏目二'],
    ['id' => 5, 'pid' => 2, 'title' => '二级栏目三'],
    ['id' => 6, 'pid' => 3, 'title' => '三级栏目一'],
];

$tree = LayuiTree::instance()->init($list);

$treeData  = $tree->toTree();                  // tree.render 的 data
$tableData = $tree->toTreeTable();             // treeTable.render 的 data
$menuHtml  = $tree->toMenu();                  // <ul class="layui-menu">…
$navHtml   = $tree->toNav(['tree' => true]);   // 垂直导航 HTML
```

## Layui 组件适配

树相关的四个方法以配置的 `id` / `pid` / `title` 为输入；`table` 由 `LayuiTable` 独立处理。

### tree

`toTree()` 输出嵌套的 `children` 结构，可直接作为 `tree.render` 的 `data`：

```php
$data = $tree->toTree();
```

```js
layui.use(function () {
  var tree = layui.tree;
  tree.render({
    elem: '#tree-demo',
    data: <?php echo json_encode($tree->toTree(), JSON_UNESCAPED_UNICODE); ?>
  });
});
```

选项：`root`（根节点 pid，默认把父级不存在的节点视为根）、`children`（子级字段名，默认取配置的 `children`，即 `children`）。

### treeTable

`toTreeTable()` 输出平铺数据，默认把 `pid` 改写为 treeTable 默认的 `parentId`，并按层级深度优先排序，父节点始终在子节点之前：

```php
$data = $tree->toTreeTable();
```

```js
treeTable.render({
  elem: '#tree-table-demo',
  data: <?php echo json_encode($tree->toTreeTable(), JSON_UNESCAPED_UNICODE); ?>,
  tree: {
    data: { rootPid: 0 },
    view: { expandAllDefault: true }
  },
  cols: [[
    { field: 'title', title: '名称' }
  ]]
});
```

如需保留 `pid` 字段，传入 `['pid' => 'pid']`，并在前端同步配置：

```php
$data = $tree->toTreeTable(['pid' => 'pid']);
```

```js
tree: { customName: { pid: 'pid' }, data: { rootPid: 0 } }
```

选项：`root`、`pid`（输出父级字段名，默认 `parentId`）、`isParent`（默认 `isParent`，传 `false` 不输出）。

### menu

`toMenu()` 输出 `ul.layui-menu` 的完整 HTML：父节点使用 `layui-menu-item-group`，子级嵌套 `<ul>`，并写入 `lay-options` 供 `dropdown` 事件使用：

```php
echo $tree->toMenu([
    'filter'   => 'menu-demo', // dropdown.on('click(menu-demo)')
    'selected' => 3,           // 选中节点 id
    'expanded' => [1],         // 默认展开的节点 id
]);
```

```js
layui.use(function () {
  var dropdown = layui.dropdown;
  dropdown.on('click(menu-demo)', function (options) {
    console.log(options); // { id: 3 }
  });
});
```

选项：`id`、`filter`、`class`、`href`、`selected`、`expanded`、`escape`。

### nav

`toNav()` 输出 `ul.layui-nav` 的完整 HTML；`tree` 开启垂直导航，`side` 开启侧边模式：

```php
echo $tree->toNav([
    'tree'     => true,        // layui-nav-tree
    'side'     => true,        // layui-nav-side
    'filter'   => 'nav-demo',  // element.on('nav(nav-demo)')
    'selected' => 6,
    'expanded' => [1],
]);
```

```js
layui.use(function () {
  var element = layui.element;
  element.render('nav', 'nav-demo'); // 动态插入后需要重新渲染
  element.on('nav(nav-demo)', function (elem) {
    console.log(elem);
  });
});
```

nav 只支持两级菜单，第三级及更深的节点会平铺到第二级。

选项：`id`、`filter`、`class`、`href`、`selected`、`expanded`、`escape`、`tree`、`side`、`accordion`。

### table

普通表格由 `LayuiTable` 封装：输入数据库数据或分页对象，输出 layui table 所需的数据与配置。

#### 服务端响应

```php
use Youvision\ThinkLayuiHelper\LayuiTable;

$paginator = \app\model\Article::order('id', 'desc')->paginate(10);

return json(LayuiTable::response($paginator));
```

默认返回：

```json
{"code":0,"msg":"","count":100,"data":[...]}
```

支持数组、`think\Collection`、ThinkPHP 分页对象。自定义字段：

```php
LayuiTable::response($list, $total, [
    'statusName' => 'status',
    'msgName'    => 'message',
    'countName'  => 'total',
    'dataName'   => 'rows',
]);
```

错误响应：

```php
return json(LayuiTable::error('参数错误'));
```

#### 分页、排序与搜索

```php
$page  = LayuiTable::page();                      // ['page'=>1,'limit'=>10,'offset'=>0]
$sort  = LayuiTable::sort(['id', 'created_at']);  // ['field'=>'id','order'=>'desc']
$where = LayuiTable::where();                     // 排除 page/limit/field/order 后的搜索参数
```

- `page()` 默认读取请求的 `page` / `limit`，并限制 `limit` 最大值（默认 1000）。
- `sort()` 默认读取请求的 `field` / `order`，`order` 只接受 `asc` / `desc`，字段不在白名单时返回空。
- `where()` 自动排除分页、排序参数，剩余字段可直接用于查询条件。

#### 列配置与渲染

```php
$cols = LayuiTable::columns([
    ['type' => 'checkbox', 'fixed' => 'left'],
    ['field' => 'id', 'title' => 'ID', 'width' => 80, 'sort' => true],
    ['field' => 'title', 'title' => '标题'],
    ['fixed' => 'right', 'title' => '操作', 'toolbar' => '#toolbar-tpl'],
]);
```

也可以单列构建：

```php
$col = LayuiTable::column('status', '状态', ['sort' => true]);
```

生成 `table.render` 配置：

```php
$config = LayuiTable::render([
    'elem' => '#table-demo',
    'url'  => '/api/articles',
    'cols' => $cols,
]);
```

```js
table.render(<?php echo json_encode($config, JSON_UNESCAPED_UNICODE); ?>);
```

或直接输出调用脚本：

```php
echo LayuiTable::script([
    'elem' => '#table-demo',
    'url'  => '/api/articles',
    'cols' => $cols,
]);
```

> `script()` 只适用于可 JSON 序列化的配置，不能包含 `done` / `toolbar` 等 JS 回调函数。


### href 与选中状态

- menu / nav 默认 `href="javascript:;"`；可传字符串统一链接，或传回调按节点生成：
  ```php
  $tree->toNav(['href' => function (array $node) {
      return '/article/' . $node['id'];
  }]);
  ```
- 节点数据自身包含 `href` 字段时会优先使用该字段。
- `selected` 接受单个 id 或 id 数组。
- `expanded` 接受 `true`（全部展开）、单个 id 或 id 数组。
- HTML 输出默认做转义，传 `'escape' => false` 可关闭。

## 在 ThinkPHP 控制器中使用

```php
namespace app\controller;

use Youvision\ThinkLayuiHelper\LayuiTree;

class Menu
{
    public function index()
    {
        $list = \app\model\Menu::select()->toArray();

        return json(LayuiTree::instance()->init($list)->toTree());
    }
}
```

## 传统树形方法

原有的 `getTreeArray` / `getTreeList` / `multipleChild` 等方法保持不变：

```php
// 带前缀的树形数组
$data = $tree->getTreeArray(0);

// 拍平为二维数组（默认使用配置的 title 字段）
$rows = $tree->getTreeList($data);

// 组装嵌套 children
$nested = $tree->multipleChild();
```

## API

| 方法 | 说明 |
| --- | --- |
| `instance(array $options = [])` | 获取/初始化单例 |
| `init(array $arr, ...)` | 注入扁平数据，链式返回自身 |
| `toTree(array $options = [])` | Layui tree 嵌套数据（`id` / `title` / 配置的 `children` 字段） |
| `toTreeTable(array $options = [])` | Layui treeTable 平铺数据（默认 `parentId` / `isParent`） |
| `toMenu(array $options = [])` | Layui menu 的 HTML 结构 |
| `toNav(array $options = [])` | Layui nav 的 HTML 结构 |
| `LayuiTable::response($data, $count = null, $options = [])` | table 异步数据响应（`code` / `msg` / `count` / `data`） |
| `LayuiTable::error($msg = '操作失败', $code = 1, $options = [])` | table 错误响应 |
| `LayuiTable::page(array $query = [], array $options = [])` | 解析 `page` / `limit` / `offset` |
| `LayuiTable::sort(array $allowed = [], array $options = [])` | 解析 `field` / `order` |
| `LayuiTable::where(array $query = [], array $except = [...])` | 提取搜索参数 |
| `LayuiTable::columns(array $columns, array $defaults = [])` | 生成 `cols` 二维列配置 |
| `LayuiTable::column($field, $title = '', array $options = [])` | 生成单个列配置 |
| `LayuiTable::render(array $options = [])` | 生成 `table.render` 配置数组 |
| `LayuiTable::script(array $options = [])` | 生成 `table.render` 的 JS 调用片段 |

| `getChild($myid)` | 直接子级 |
| `getChildren($myid, $withself = false)` | 全部后代 |
| `getChildrenIds($myid, $withself = false)` | 全部后代 ID |
| `getParent($myid)` | 直接父级 |
| `getParents($myid, $withself = false)` | 全部祖先（由近及远） |
| `getParentsIds($myid, $withself = false)` | 全部祖先 ID |
| `getTreeArray($myid = 0, $itemprefix = '')` | 带 `spacer` / `childlist` 的树形数组 |
| `getTreeList(array $data, $field = null)` | 拍平为二维数组，附 `haschild`，默认使用配置的 title |
| `multipleChild()` | 组装嵌套 `childlist` / 自定义子级字段的多维数组 |

## 说明

- `instance()` 为进程内单例，多次调用 `init()` 会复用同一实例；需要独立实例时直接 `new LayuiTree()`。
- `getParents()` 返回顺序为“顶层 → 直接父级”。
- `multipleChild()` 会为有子级的节点写入子级字段，没有子级的节点不写入该字段（读取时建议用 `?? []`）。
- `toTree()` / `toTreeTable()` 默认把“父级 id 不存在”的节点视为根节点；传 `root` 可指定严格的根 pid（例如 `0`）。
- `toTreeTable()` 只输出可被树覆盖到的节点，存在循环引用的节点不会输出。
- 配置读取发生在 `LayuiTree` 实例化时；修改 `config/layui_helper.php` 后需要重新实例化。
- `LayuiTable` 在 ThinkPHP 应用内自动读取当前请求的 `page` / `limit` / `field` / `order`；无请求上下文时回退到 `$_GET` / `$_POST`。
- `LayuiTable::script()` 只支持可 JSON 序列化的配置，不能包含 JS 回调函数（如 `done`、`toolbar` 函数）。

## License

MIT