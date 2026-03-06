# AIBase Cases Pipeline

This pipeline collects all AI cases from `https://www.aibase.com/zh/cases`, stores them as WordPress drafts, and publishes `2-3` posts daily.

## Scripts

- Collector: `wwwroot/collect_aibase_cases.py`
- Daily publisher: `wwwroot/auto_publish_cases_daily.py`
- Health check: `wwwroot/health_check_cases.py`

## Collector Behavior

- Uses AIBase JSON endpoints behind the cases page:
  - list: `/ai/GetAiInfoList.aspx` (`type=5`, `flag=zh`)
  - detail: `/ai/GetAiCommunityById.aspx`
- Preserves case structure in content and applies light normalization.
- Enforces basic quality gate before draft creation:
  - title length
  - minimum text length
  - featured image presence (configurable)
- Deduplicates by:
  - AIBase case ID history
  - existing WordPress draft/published title fingerprint

## Publisher Behavior

- Only publishes drafts under category `AI案例`.
- Slot-based publish targets:
  - morning: 2 posts
  - evening: 1 post
- Daily max default: 3 posts.
- Uses `publish_draft.php` and keeps local publish history.
- Includes DB fallback query for draft selection, avoiding the `get_drafts.php` 100-item limit.

## Required `.env` Keys

```env
WP_URL=https://example.com
WP_API_KEY=your-api-key
WP_REST_BASE=https://example.com/wp-json/myblog/v1
WP_USE_REST_API=1

AIBASE_CASES_ENABLED=1
AIBASE_CASES_FLAG=zh
AIBASE_CASES_TYPE=5
AIBASE_CASES_PAGE_SIZE=50
AIBASE_CASES_BATCH_SIZE=40
AIBASE_CASES_MIN_TEXT_LEN=180
AIBASE_CASES_TITLE_MIN_LEN=8
AIBASE_CASES_TITLE_MAX_LEN=80
AIBASE_CASES_REQUIRE_THUMB=1
AIBASE_CASES_DRAFT_STATUS=draft
AIBASE_CASES_CATEGORY=AI案例
AIBASE_CASES_CATEGORY_SLUG=ai-cases
AIBASE_CASES_TAGS=AI案例,案例拆解,AI变现
AIBASE_CASES_NO_SOURCE_LINK=1
AIBASE_CASES_SLEEP_SECONDS=1
AIBASE_CASES_HISTORY_FILE=wwwroot/runtime/aibase_cases_history.json
AIBASE_CASES_PUBLISH_HISTORY_FILE=wwwroot/runtime/publish_history_cases.json
AIBASE_CASES_DAILY_MAX=3
AIBASE_CASES_MORNING_COUNT=2
AIBASE_CASES_EVENING_COUNT=1

# DB keys are required for publisher DB fallback
DB_NAME=your_db_name
DB_USER=your_db_user
DB_PASSWORD=your_db_password
DB_HOST=localhost
DB_TABLE_PREFIX=blog_
```

## Local Validation

```powershell
python wwwroot/collect_aibase_cases.py --mode incremental --max-pages 1 --batch-size 5 --dry-run
python wwwroot/auto_publish_cases_daily.py --slot manual --count 2 --dry-run
python wwwroot/health_check_cases.py --json
```

## Backfill (All cases -> drafts)

Run in batches:

```powershell
python wwwroot/collect_aibase_cases.py --mode backfill --batch-size 40
```

Repeat until output shows `candidate_count=0`.

## Daily Increment + Publish

- Incremental collect once a day:

```powershell
python wwwroot/collect_aibase_cases.py --mode incremental --batch-size 20
```

- Publish by slots:

```powershell
python wwwroot/auto_publish_cases_daily.py --slot morning
python wwwroot/auto_publish_cases_daily.py --slot evening
```

## Linux Cron Example

```bash
# Incremental collect
30 7 * * * cd /www/wwwroot/www.skyrobot.top && /usr/bin/python3 collect_aibase_cases.py --mode incremental --batch-size 20 >> /var/log/aibase_cases_collect.log 2>&1

# Daily publish slots
0 9 * * * cd /www/wwwroot/www.skyrobot.top && /usr/bin/python3 auto_publish_cases_daily.py --slot morning >> /var/log/aibase_cases_publish.log 2>&1
0 18 * * * cd /www/wwwroot/www.skyrobot.top && /usr/bin/python3 auto_publish_cases_daily.py --slot evening >> /var/log/aibase_cases_publish.log 2>&1

# Optional health check report (warnings/errors only)
10 19 * * * cd /www/wwwroot/www.skyrobot.top && /usr/bin/python3 health_check_cases.py --strict >> /var/log/aibase_cases_health.log 2>&1
```
