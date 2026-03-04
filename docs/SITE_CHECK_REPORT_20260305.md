# Site Check Report (2026-03-05)

目标：对线上站点做一轮可用性与结构健康检查，并给出开源前风险提示。

## 1) 可用性检查

检查页面：

1. `https://www.skyrobot.top/`
2. `https://www.skyrobot.top/zhuanti/`
3. `https://www.skyrobot.top/tags/`
4. `https://www.skyrobot.top/category/about-me/`
5. `https://www.skyrobot.top/tag/deepseek/`

结果：全部返回 HTTP `200`。

## 2) 缓存与响应

抽样响应头中 `x-cache-status`：

- 首页：`HIT`
- 专题页：`HIT`
- 标签页：`HIT`
- 关于我分类页：`HIT`

说明：Nginx FastCGI 缓存命中正常。

## 3) 外链检查（抽样）

首页 / 专题 / 标签 / 关于我页抽样外链域名：

- `wpa.qq.com`
- `t.qq.com`
- `weibo.com`

未发现淘宝/天猫域名回流。

## 4) 关键发现

1. 本地已完成 `/category/about-me/` 新模板开发，但线上页面仍未命中新结构（仍是旧版分类页结构）。
2. 原因是线上尚未同步本地最新主题代码（非缓存问题，已通过带随机参数请求验证）。

## 5) 开源前风险检查（本地仓库）

已检查项：

1. 未发现硬编码 GitHub PAT / `ghp_` / `github_pat_`。
2. `.env` 被忽略，仓库仅保留 `.env.example`。
3. 生产运行目录（uploads/cache/wp-config）未纳入开源跟踪。

注意项：

1. 文档中存在站点域名与历史备份路径记录（属于运营信息而非密钥）。
2. 若希望进一步脱敏，可把文档中的生产路径改为示例路径。

## 6) 建议的下一步

1. 先把最新 About-Me 模板与样式部署到线上并清缓存。
2. 对开源包继续执行“占位符化”策略（联系方式、图片、域名等用示例值）。
3. 发布前跑一次 `scripts/precheck.ps1`，再执行 `git push`。
