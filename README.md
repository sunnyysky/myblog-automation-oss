# MyBlog Automation (Open-Source Edition)

This repository is a sanitized, open-source subset of a production WordPress automation project.

## Included

- Incremental article scan and collect pipeline
- Draft auto-publish script
- External feed daily digest auto-publish script
- AIBase cases collector + slot-based daily publisher
- Featured-image validation script
- Strict source-vs-database compare script
- Sanitized WordPress endpoint pack (`blog_open_source/`)
- Operational documentation

## Not Included

- Real credentials, API keys, passwords
- Production WordPress runtime files (`wp-config.php`, uploads, backups)
- Private data dumps and local reports

## Quick Start

1. Copy `.env.example` to `.env`
2. Fill in your own credentials and server settings
3. Run scripts from project root

```powershell
python compare_complete.py
python wwwroot/auto_scan_updates.py
python wwwroot/collect_new_articles.py --file your_new_articles.json
python wwwroot/check_featured_images.py
python wwwroot/auto_publish_daily.py
python wwwroot/collect_external_feeds.py --dry-run
python wwwroot/collect_aibase_cases.py --mode incremental --max-pages 1 --batch-size 5 --dry-run
python wwwroot/auto_publish_cases_daily.py --slot manual --count 2 --dry-run
```

## Deployment Principle

- Code is shared across environments
- Credentials stay environment-local in `.env`
- Never commit `.env` or production snapshots

See `docs/OPEN_SOURCE_RELEASE_GUIDE.md` for release and GitHub publishing workflow.

## Iteration

- Branch and commit workflow: `docs/DEV_WORKFLOW.md`
- External feed automation: `docs/EXTERNAL_FEED_AUTOMATION.md`
- AIBase cases pipeline: `docs/AIBASE_CASES_PIPELINE.md`
- Local precheck script: `scripts/precheck.ps1`
- CI precheck: `.github/workflows/precheck.yml`
