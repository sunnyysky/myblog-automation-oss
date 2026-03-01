# MyBlog 系统总览（可持续开发基线）

更新时间：2026-03-02
适用目录：`D:\02_work\04_MyBlog`

## 1. 项目目标

本项目用于自动化完成 AIBase 内容到 WordPress 的全流程运营：

- 扫描源站新文章
- 采集正文与图片
- 入库为草稿并写入 SEO 信息
- 自动定时发布
- 封面图修复与质量核对
- 持续增量更新

## 2. 当前系统功能（已落地）

### 2.1 采集与入库

- 从 AIBase 文章页采集标题、正文、分类、图片
- 自动上传图片到 WordPress 媒体库
- 自动设置特色图片（优先封面图）
- 发布方式为写入 WordPress 草稿（`draft`）

核心脚本：

- `wwwroot/collect_all_123.py`
- `wwwroot/collect_new_articles.py`
- `wwwroot/auto_scan_updates.py`

### 2.2 自动发布

- 从草稿中筛选有特色图的文章
- 按创建时间顺序自动发布
- 记录发布历史

核心脚本：

- `wwwroot/auto_publish_daily.py`
- `wwwroot/publish_history.json`

### 2.3 校验与修复

- 对比源站清单与数据库文章，识别缺失
- 检查草稿/已发布文章是否有 `_thumbnail_id`
- 针对缺图文章做封面修复（已做过批量修复）

核心脚本：

- `compare_complete.py`（已改为严格匹配）
- `wwwroot/check_featured_images.py`
- 历史报告：`fix_cover_all_report.json`、`fix_cover_drafts_report.json`

## 3. 当前真实数据状态（截至 2026-03-02）

基线与一致性：

- 源文章基线：`303`（`all_source_articles_complete.json`）
- 严格匹配未采集：`0`（`compare_complete.py` 最新结果）

数据库文章状态统计（`post_type='post'`）：

- `draft`: `265`
- `publish`: `96`
- `private`: `3`

最近一次补采集：

- 按缺失清单补采 `44` 篇
- 成功 `44`，失败 `0`
- 该批次文章封面缺失 `0`

最终状态文件：

- `final_status_report_20260302.json`

## 4. 关键口径说明（避免后续误判）

- 对比口径已统一为“严格标题匹配”：
- 规则：去空白 + 去控制字符 + 小写后做精确匹配
- 不再使用“包含匹配”，避免出现“假 100% 采集率”

## 5. 配置与凭据约定

凭据已改为优先从 `.env` 读取，关键键：

- `SERVER_HOST`
- `SERVER_USER`
- `SERVER_PASSWORD`
- `WP_URL`
- `WP_ADMIN_USER`
- `WP_API_KEY`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_TABLE_PREFIX`

已完成 `.env` 化的脚本：

- `compare_complete.py`
- `wwwroot/collect_all_123.py`
- `wwwroot/auto_publish_daily.py`
- `wwwroot/check_featured_images.py`

## 6. 典型运行顺序（标准操作）

1. 先扫描更新，刷新源文章清单  
`python wwwroot/auto_scan_updates.py`

2. 再做严格对比，确认缺失  
`python compare_complete.py`

3. 若有缺失，执行补采集  
`python wwwroot/collect_all_123.py`

4. 检查特色图完整性  
`python wwwroot/check_featured_images.py`

5. 自动发布（按计划或手动）  
`python wwwroot/auto_publish_daily.py`

## 7. 后续开发建议入口（你下次可直接让我做）

- 增加“URL 维度对比”并生成 title/url 双报告（减少重名误差）
- 为采集流程增加幂等日志与重试队列（失败重跑更稳）
- 将 SSH + MySQL 查询统一抽成工具模块（减少重复代码）
- 增加“重复文章检测”与自动归档策略
- 为核心脚本补充统一 CLI 参数（如 `--dry-run`、`--limit`、`--since`）
- 增加日报输出（采集数、发布数、缺图数、失败数）

## 8. 给后续协作的提示语

你可以在后续对话直接说：

- “按 `docs/CURRENT_SYSTEM_OVERVIEW.md` 继续开发，先做 XXX”
- “先读取 `final_status_report_20260302.json`，然后做增量优化”

这样可以减少重复沟通和上下文丢失。

