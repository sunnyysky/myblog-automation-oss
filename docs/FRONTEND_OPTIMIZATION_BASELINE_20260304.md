# Frontend Optimization Baseline (2026-03-04)

适用站点：`https://your-domain.example/`（开源版示例）  
主题：`wwwroot/wp-content/themes/justnews`

## 1. 本轮完成内容

### 1.1 首页结构

- 首页主内容区采用“最新 + 12 个分类模块”结构：
- 固定优先分类不足时，自动补齐高内容量分类（`count` 降序）。
- 分类模块标题增加图标、文章数徽标。
- 新增“内容目录”面板：提供 12 个分类锚点跳转，首页内快速定位到对应模块。
- 新增“快速开始”卡片流：首屏下方展示 4 条优先内容入口，增强首页可浏览性。

### 1.1.1 全站头部兼容优化

- 头部 `viewport` 调整为 `width=device-width, initial-scale=1`（移除禁止缩放参数）。
- favicon 链接改为 `home_url()` 动态拼接，避免域名硬编码导致跨域部署不一致。

### 1.2 首页侧栏

新增并统一为卡片化模块：

- 站内搜索
- 站点概览（文章/分类/标签/专题统计 + 7 天更新数）
- 热门文章（按 `views`，90 天窗口，缓存）
- 随机探索（缓存随机文章入口）
- 精选阅读（按评论热度/近期内容，缓存）
- 分类导航
- 热门专题（`special` taxonomy，缓存）
- 热门标签（彩色标签胶囊，缓存）
- 订阅更新

### 1.3 首页轮播与特征图

- 轮播与特征图支持优先使用附件 ID 渲染（`wp_get_attachment_image`），获得更好的 `srcset/size` 能力。
- 首屏图保留 `fetchpriority=\"high\"`，其余懒加载。
- 自动过滤淘宝/天猫外链，统一回落到站内分类链接。

### 1.4 标签页

- 标签页保留 Hero + 热门标签彩色 chips。
- 当标签无文章时，自动展示 6 篇最新文章作为兜底，避免空白页。
- 标签页 Hero 新增快捷锚点：返回首页 / 全部标签 / 热门标签 / 文章列表。

### 1.5 分类页（新增）

- 对 `cat-tpl-default.php`、`cat-tpl-list.php`、`cat-tpl-image.php`、`cat-tpl-image-fullwidth.php` 统一增强：
- 新增“分类总览”模块（总文章、7 天更新、子分类数量、最近更新）。
- 新增子分类 chips 导航（有子分类时显示）。
- 新增“本分类热门”与“相关标签”侧栏（全宽模板为底部延伸阅读区）。
- 分类页样式统一并纳入 `skyrobot-home.css` 管理（通过 `is_category()` 自动加载）。

### 1.6 专题页 / 标签云页 / 文章右栏（新增）

- `/zhuanti/` 从 `page.php` 回退页升级为“专题中枢页”：
- 新增专题 Hero、专题卡片、热门/最新专题、专题关联标签模块。
- 修复页面正文垃圾文案暴露问题（不再依赖后台 page content 作为主内容）。
- `/tags/` 从 `page.php` 回退页升级为“标签发现页”：
- 新增标签 Hero、热门标签 chips、标签发现卡片、全部标签云、关联专题侧栏。
- 修复页面正文垃圾文案暴露问题。
- 单篇文章右栏升级为现代卡片风格（对齐首页）：
- 新增文章速览、目录导航（TOC）、同类热门、延伸阅读、标签 chips。
- 目录锚点由前端渲染时自动注入 `h2/h3 id`，不改数据库内容。

### 1.7 专题/标签/文章页交互增强（Round6）

- 专题页（`/zhuanti/`）：
- 增加专题卡片排序控件（最热 / 近期活跃 / 最近更新 / 名称）。
- 新增“专题路线图”模块，突出高频专题及更新节奏。
- 标签页（`/tags/`）：
- 增加标签发现卡片排序控件（最热 / 近期活跃 / 最近更新 / A-Z）。
- 新增“标签趋势观察”模块，强化页面信息密度。
- 单篇右栏：
- 新增“阅读进度”卡片（滚动实时进度条）。
- 目录支持滚动高亮和顺滑跳转，定位更直观。
- 新增轻量动效：专题卡片/标签卡片/右栏卡片加载渐显（无阻塞依赖）。

### 1.8 关于我名片页（Round7）

- `/category/about-me/` 升级为专属“个人名片页”（`category-about-me.php`）：
- 首屏输出个人定位、核心宣言、价值导向 CTA（加微信 / 查看专题）。
- 页面新增模块：
- 我能帮你解决什么（问题导向）
- 专业方向（工业机器人 / 预测性维护 / 插件化改造）
- 你将获得的价值（结果导向）
- 方法论（自动化/模板化/流程化/工具化）
- 代表内容（about-me 分类优先，数量不足自动 fallback 最新文章）
- 右侧新增联系方式模块：微信号 + 二维码（若图片缺失显示明确提示）。
- 新增网站概览与快速入口模块，强化“个人 + 站点定位”一体化表达。
- 模板内增加 `Person` JSON-LD，增强关于页语义表达。

## 2. 新增/关键函数

文件：`wwwroot/wp-content/themes/justnews/functions.php`

- `skyrobot_is_internal_link`
- `skyrobot_get_link_target`
- `skyrobot_get_link_rel`
- `skyrobot_format_compact_number`
- `skyrobot_get_home_hot_post_ids`
- `skyrobot_get_home_spotlight_post_ids`
- `skyrobot_get_home_hot_special_term_ids`
- `skyrobot_get_home_hot_tag_term_ids`
- `skyrobot_get_site_stat_snapshot`
- `skyrobot_get_home_random_post_ids`
- `skyrobot_flush_home_data_transients`
- `skyrobot_flush_home_data_on_post_change`
- `skyrobot_flush_home_data_on_term_change`
- `skyrobot_get_category_page_context`
- `skyrobot_flush_transients_by_prefix`
- `skyrobot_get_special_term_snapshot`
- `skyrobot_get_special_hub_context`
- `skyrobot_get_tag_preview_posts`
- `skyrobot_get_tag_hub_context`
- `skyrobot_build_heading_payload`
- `skyrobot_get_single_toc_payload`
- `skyrobot_prepare_single_toc_content`
- `skyrobot_get_single_sidebar_context`
- `skyrobot_get_about_profile_config`
- `skyrobot_get_about_page_posts`
- `skyrobot_get_about_page_context`

## 3. 主要改动文件

- `wwwroot/wp-content/themes/justnews/index.php`
- `wwwroot/wp-content/themes/justnews/tag.php`
- `wwwroot/wp-content/themes/justnews/functions.php`
- `wwwroot/wp-content/themes/justnews/css/skyrobot-home.css`
- `wwwroot/wp-content/themes/justnews/single.php`
- `wwwroot/wp-content/themes/justnews/page-zhuanti.php`
- `wwwroot/wp-content/themes/justnews/page-tags.php`
- `wwwroot/wp-content/themes/justnews/pages/zhuanti.php`
- `wwwroot/wp-content/themes/justnews/pages/tags.php`
- `wwwroot/wp-content/themes/justnews/js/skyrobot-home.js`
- `wwwroot/wp-content/themes/justnews/category-about-me.php`
- `wwwroot/wp-content/themes/justnews/images/about/wechat-sky.jpg`（资源位，需放置实际二维码）

## 4. 线上发布与回滚

- 备份目录示例：`/var/www/your-site/_ops_backups/yyyymmdd_hhmmss_home_opt`
- 二次/三次/四次/五次优化备份同命名规则递增。
- 若需回滚，可从该目录按文件覆盖回主题目录。

## 5. 缓存注意事项

该站启用了 Nginx FastCGI 缓存（响应头 `x-cache-status`）。  
发布后需要清缓存，否则首页可能继续命中旧页面：

```bash
rm -rf /var/cache/nginx/*
nginx -s reload
```

验证建议：

- 首页：`https://your-domain.example/`
- 任意标签页：`https://your-domain.example/tag/sample-tag/`
- 检查 `x-cache-status` 从 `MISS` 到 `HIT` 且结构不回退。
