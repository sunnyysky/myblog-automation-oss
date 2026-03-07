# 普通文章 Cron 恢复记录（2026-03-07）

## 背景

- 线上 active `crontab` 仅保留了 `AI case` 相关任务和图片巡检任务。
- 普通文章任务未挂载到 active `crontab`：
  - `auto_scan_updates.py`
  - `auto_publish_daily.py`
- 因此 `2026-03-07` 当天只发布了 `AI案例`，普通文章没有自动更新。

## 本次恢复动作

- 备份了恢复前的线上 `crontab`：
  - `/root/crontab_backup_20260307_170423.txt`
- 恢复了普通文章相关任务：
  - `5 2 * * * cd /www/wwwroot/www.skyrobot.top && /usr/bin/python3 auto_scan_updates.py >> logs/scan.log 2>&1`
  - `0 8 * * * cd /www/wwwroot/www.skyrobot.top && /usr/bin/python3 auto_publish_daily.py >> logs/publish_morning.log 2>&1`
  - `0 20 * * * cd /www/wwwroot/www.skyrobot.top && /usr/bin/python3 auto_publish_daily.py >> logs/publish_evening.log 2>&1`
- 保留了原有任务：
  - `wp-cron.php`
  - `AI case` 采集/发布/健康检查
  - `guard_published_images.php`

## 手动受控验证

- 执行时间：`2026-03-07 17:05:26`（服务器本地时间）
- 执行命令：
  - `/usr/bin/python3 auto_publish_daily.py`
- 备份文件：
  - `/www/wwwroot/www.skyrobot.top/publish_history.json.bak.20260307_170526`
- 运行日志：
  - `/www/wwwroot/www.skyrobot.top/logs/publish_recover_20260307_170526.log`

本次手动发布成功 3 篇普通文章：

1. `1301` `如何用豆包设计浴室防霉日常清洁小技巧`
2. `1306` `如何用豆包生成直播脚本`
3. `1310` `如何用 DeepSeek 生成博客文章`

## 验证结果

- `publish_history.json` 中 `2026-03-07` 的普通文章发布计数已变为 `3`
- 站点最新文章列表已出现上述 3 篇普通文章
- 今日文章序列恢复为：
  - `09:00` 发布 `AI案例`
  - `17:05` 补发 3 篇普通文章

## 仍然存在的断口

- 本次恢复的是“普通文章自动发布”能力，不是完整“扫描 -> 入库 -> 发布”闭环。
- `auto_scan_updates.py` 只会扫描并生成 `new_articles_*.json`，不会自动把新文章采进 WordPress 草稿箱。
- 当前 active `crontab` 里仍然没有 `collect_new_articles.py` 的自动任务。
- 服务器上最近一份未消费的新文章清单是：
  - `/www/wwwroot/www.skyrobot.top/new_articles_20260225_021706.json`
  - 文件内共有 `606` 篇待入库文章

## 结论

- 这次已经修复了“今天普通文章不更新”的直接原因。
- 如果后续要恢复完整增量更新，还需要单独补“扫描结果自动入库草稿箱”的调度方案。
