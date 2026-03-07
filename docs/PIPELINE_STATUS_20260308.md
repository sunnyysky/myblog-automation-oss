# Pipeline Status Record (2026-03-08)

This record captures the current status of the AIBase articles pipeline,
the dependency setup on the server, and the latest manual verification run.

## Summary

- The articles pipeline is now a closed loop:
  - scan -> collect into drafts -> scheduled publish
- Server Python dependencies are installed in a local venv.
- Cron now uses the venv Python for all Python tasks.

## Server Environment

- Venv path: `/www/wwwroot/www.skyrobot.top/.venv`
- Installed packages (pip):
  - `paramiko`
  - `requests`
  - `beautifulsoup4`

## Cron (articles + cases)

- Scan: `02:05` -> `auto_scan_updates.py`
- Collect: `02:25` -> `auto_collect_new_articles.py --limit 20`
- Publish: `08:00` and `20:00` -> `auto_publish_daily.py`
- AI cases are still on their original schedule.

## Scan Parser Fix

`auto_scan_updates.py` updated to support `/zh/details/<id>` links and
normalize them from legacy `/zh/<id>` if present.

## Title Cleanup

Titles now remove:
- Zero-width characters
- "view count" + "view details" tail text from the list page

Applied to:
- `auto_scan_updates.py`
- `collect_new_articles.py`
- `collect_all_123.py`

## Manual Verification (2026-03-08 01:30 - 01:42 CST)

- Scan generated: `new_articles_20260308_012630.json` with `454` items.
- Collect run:
  - Log: `/www/wwwroot/www.skyrobot.top/logs/collect_new_articles.log`
  - Result: `132` items collected, `0` failed.
  - Time window: `01:30:10` -> `01:42:22`
  - State file:
    `/www/wwwroot/www.skyrobot.top/logs/collect_new_articles_state.json`

## Notes

- The daily collect limit is `20`.
- The manual run above collected more than 20 because it was triggered
  before the limit change took effect in the running process.
- Future runs will respect `--limit 20` and will skip duplicates
  already present in WordPress.
