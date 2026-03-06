# External Feed Automation

This feature collects external RSS/Atom data and publishes a daily digest post to WordPress.

## 1. Configure `.env`

Minimum required keys:

- `WP_URL`
- `WP_ADMIN_USER`
- `WP_APP_PASSWORD` (or `WP_API_KEY` / `WP_ADMIN_PASSWORD`)

Feed-specific keys:

- `EXTERNAL_FEEDS`: JSON array of feed sources
- `EXTERNAL_FEED_POST_STATUS`: `publish` / `draft` / `pending` / `private`
- `EXTERNAL_FEED_CATEGORY_SLUG`
- `EXTERNAL_FEED_CATEGORY_NAME`
- `EXTERNAL_FEED_TAGS`
- `EXTERNAL_FEED_USE_HELPER_ENDPOINT`: `1` to use `wp_publish_helper.php` first
- `EXTERNAL_FEED_HELPER_URL`: helper endpoint URL

Example:

```env
EXTERNAL_FEEDS=[{"name":"OpenAI News","url":"https://openai.com/news/rss.xml","type":"rss"},{"name":"Hacker News","url":"https://hnrss.org/frontpage","type":"rss"}]
EXTERNAL_FEED_POST_STATUS=publish
EXTERNAL_FEED_USE_HELPER_ENDPOINT=1
EXTERNAL_FEED_HELPER_URL=https://example.com/wp_publish_helper.php
EXTERNAL_FEED_CATEGORY_SLUG=external-updates
EXTERNAL_FEED_CATEGORY_NAME=外部数据更新
EXTERNAL_FEED_TAGS=外部数据,自动更新
```

## 2. Dry-run

```powershell
python wwwroot/collect_external_feeds.py --dry-run
```

## 3. Run Once

```powershell
python wwwroot/collect_external_feeds.py
```

Script behavior:

- Deduplicates by feed item hash (`url + title`)
- Persists history in `wwwroot/runtime/external_feed_history.json`
- Creates category/tag automatically if missing
- By default, publishes at most one digest per day

## 4. Daily Schedule (Windows Task Scheduler)

Create a daily task with command:

```powershell
python D:\02_work\04_MyBlog\wwwroot\collect_external_feeds.py
```

Recommended:

- Trigger: once per day (e.g. 08:30)
- Start in: `D:\02_work\04_MyBlog`
- Retry on failure: enabled

## 5. Daily Schedule (Linux Cron)

```bash
30 8 * * * cd /path/to/MyBlog && /usr/bin/python3 wwwroot/collect_external_feeds.py >> /var/log/myblog_external_feed.log 2>&1
```

## 6. Safety Notes

- Keep secrets only in local `.env`
- Do not commit runtime history/log files
- Run `scripts/precheck.ps1` before push
