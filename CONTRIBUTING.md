# Contributing

## Setup

1. Copy `.env.example` to `.env`
2. Fill local test credentials
3. Run changed scripts locally before committing

## Commit Rules

- Keep changes small and focused
- Do not include secrets, backups, or generated artifacts
- Update docs when behavior changes

## Validation

- Python syntax check:
  - `python -m py_compile compare_complete.py`
  - `python -m py_compile wwwroot/collect_all_123.py`
  - `python -m py_compile wwwroot/auto_scan_updates.py`
  - `python -m py_compile wwwroot/auto_publish_daily.py`
  - `python -m py_compile wwwroot/check_featured_images.py`

- Secret scan:
  - `rg -n "REDACTED_PASSWORD_EXAMPLE|REDACTED_SERVER_IP_EXAMPLE|github_pat_|ghp_" .`
