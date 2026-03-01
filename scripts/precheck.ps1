param(
  [switch]$Fast
)

$ErrorActionPreference = "Stop"

Write-Host "== Precheck: Python syntax =="
python -m py_compile compare_complete.py
python -m py_compile wwwroot/collect_all_123.py
python -m py_compile wwwroot/collect_new_articles.py
python -m py_compile wwwroot/auto_scan_updates.py
python -m py_compile wwwroot/auto_publish_daily.py
python -m py_compile wwwroot/check_featured_images.py

if (-not $Fast) {
  Write-Host "== Precheck: Secret scan (tracked scope) =="
  rg -n "Sky!123456|8\.138\.213\.135|github_pat_|ghp_" `
    compare_complete.py `
    wwwroot/collect_all_123.py `
    wwwroot/collect_new_articles.py `
    wwwroot/auto_scan_updates.py `
    wwwroot/auto_publish_daily.py `
    wwwroot/check_featured_images.py `
    blog_open_source
}

Write-Host "Precheck passed."
