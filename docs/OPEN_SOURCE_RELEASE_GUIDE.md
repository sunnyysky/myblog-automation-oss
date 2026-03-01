# Open Source Release Guide

## Goal

Publish a reusable public repository without exposing production credentials or private data.

## Strategy

- Use whitelist `.gitignore` to track only sanitized files.
- Keep production deployment unchanged.
- Store all runtime secrets in server-local `.env`.

## Release Steps

1. Confirm `.env` is present locally but untracked.
2. Run syntax checks for included scripts.
3. Run secret scan on tracked files.
4. Initialize git and commit.
5. Create GitHub repository and push.

## Suggested Commands

```powershell
python -m py_compile compare_complete.py
python -m py_compile wwwroot/collect_all_123.py
python -m py_compile wwwroot/auto_scan_updates.py
python -m py_compile wwwroot/auto_publish_daily.py
python -m py_compile wwwroot/check_featured_images.py

git init
git add .
git status
git commit -m "chore: open-source sanitized baseline"
```

## GitHub Publish (with GitHub CLI)

```powershell
gh auth login
gh repo create <your-repo-name> --public --source . --remote origin --push
```

## Cross-Server Deployment Principle

- Same code repo, different `.env` per server.
- Deploy code only.
- Never overwrite server `.env` during deployment.
