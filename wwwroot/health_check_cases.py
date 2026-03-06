#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Health check for AIBase cases pipeline.

Usage:
  python wwwroot/health_check_cases.py
  python wwwroot/health_check_cases.py --json
  python wwwroot/health_check_cases.py --strict
"""

import argparse
import io
import json
import re
import subprocess
import sys
import urllib.error
import urllib.parse
import urllib.request
from collections import Counter, deque
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple


if sys.platform == "win32":
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8")
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8")


def load_env() -> Dict[str, str]:
    env: Dict[str, str] = {}
    root_dir = Path(__file__).resolve().parent.parent
    candidates = [root_dir / ".env", Path(__file__).resolve().parent / ".env", Path(".env")]
    for env_path in candidates:
        if not env_path.exists():
            continue
        with env_path.open("r", encoding="utf-8") as fh:
            for raw_line in fh:
                line = raw_line.strip()
                if not line or line.startswith("#") or "=" not in line:
                    continue
                key, value = line.split("=", 1)
                env[key.strip()] = value.strip().strip('"').strip("'")
        break
    return env


def env_bool(env: Dict[str, str], key: str, default: bool = False) -> bool:
    raw = env.get(key, "").strip().lower()
    if raw == "":
        return default
    return raw in {"1", "true", "yes", "on"}


def env_int(env: Dict[str, str], key: str, default: int) -> int:
    raw = env.get(key, "").strip()
    if raw == "":
        return default
    try:
        return int(raw)
    except ValueError:
        return default


def resolve_path(raw_path: str, default_path: Path) -> Path:
    if not raw_path:
        return default_path

    path_obj = Path(raw_path)
    if path_obj.is_absolute():
        return path_obj

    script_dir = Path(__file__).resolve().parent
    normalized = raw_path.replace("\\", "/")
    if normalized.startswith("wwwroot/"):
        # Local repo style: script under <root>/wwwroot, history path starts with "wwwroot/".
        if script_dir.name.lower() == "wwwroot":
            return (script_dir.parent / path_obj).resolve()
        # Server style: script often deployed directly into site root.
        trimmed = normalized[len("wwwroot/") :]
        return (script_dir / trimmed).resolve()
    return (script_dir / path_obj).resolve()


def read_json_file(path: Path) -> Tuple[Optional[object], Optional[str]]:
    if not path.exists():
        return None, "missing"
    try:
        with path.open("r", encoding="utf-8") as fh:
            return json.load(fh), None
    except (OSError, json.JSONDecodeError) as exc:
        return None, str(exc)


def tail_lines(path: Path, max_lines: int = 120) -> Tuple[List[str], Optional[str]]:
    if not path.exists():
        return [], "missing"
    try:
        bucket: deque[str] = deque(maxlen=max_lines)
        with path.open("r", encoding="utf-8", errors="replace") as fh:
            for line in fh:
                bucket.append(line.rstrip("\n"))
        return list(bucket), None
    except OSError as exc:
        return [], str(exc)


def extract_recent_errors(lines: List[str]) -> List[str]:
    errors = []
    patterns = [r"\[error\]", r"traceback", r"exception", r"fatal"]
    for line in lines:
        lower = line.lower()
        if any(re.search(p, lower) for p in patterns):
            errors.append(line.strip())
    return errors


def check_wp_category(wp_url: str, category_slug: str) -> Tuple[bool, str]:
    if not wp_url or not category_slug:
        return False, "skip (missing WP_URL or category slug)"
    query = urllib.parse.urlencode({"slug": category_slug, "per_page": 5})
    endpoint = f"{wp_url.rstrip('/')}/wp-json/wp/v2/categories?{query}"
    request = urllib.request.Request(endpoint, headers={"User-Agent": "MyBlogHealthCheck/1.0"})
    try:
        with urllib.request.urlopen(request, timeout=15) as response:
            text = response.read().decode("utf-8", errors="replace")
        payload = json.loads(text)
        if isinstance(payload, list) and payload:
            cat_id = int(payload[0].get("id", 0) or 0)
            cat_name = str(payload[0].get("name", "")).strip()
            if cat_id > 0:
                return True, f"category found id={cat_id} name={cat_name}"
        return False, f"category slug not found: {category_slug}"
    except (urllib.error.URLError, ValueError, TimeoutError) as exc:
        return False, f"category check failed: {exc}"


class Reporter:
    def __init__(self) -> None:
        self.checks: List[Dict[str, str]] = []
        self.warnings: List[str] = []
        self.errors: List[str] = []

    def add(self, level: str, message: str) -> None:
        self.checks.append({"level": level, "message": message})
        if level == "warn":
            self.warnings.append(message)
        elif level == "error":
            self.errors.append(message)

    def ok(self, message: str) -> None:
        self.add("ok", message)

    def warn(self, message: str) -> None:
        self.add("warn", message)

    def error(self, message: str) -> None:
        self.add("error", message)


def check_cron_entries(reporter: Reporter) -> None:
    if sys.platform == "win32":
        reporter.warn("cron check skipped on Windows")
        return

    try:
        process = subprocess.run(
            ["crontab", "-l"],
            capture_output=True,
            text=True,
            check=False,
            timeout=10,
        )
    except (OSError, subprocess.TimeoutExpired) as exc:
        reporter.warn(f"cron check failed: {exc}")
        return

    if process.returncode != 0:
        stderr = process.stderr.strip() or "unknown error"
        reporter.warn(f"cannot read crontab: {stderr}")
        return

    content = process.stdout
    required_tags = [
        "aibase_cases_collect",
        "aibase_cases_publish_morning",
        "aibase_cases_publish_evening",
    ]
    missing = [tag for tag in required_tags if tag not in content]
    if missing:
        reporter.warn(f"cron tags missing: {', '.join(missing)}")
    else:
        reporter.ok("cron tags present for collect/morning/evening")


def run_health_check(strict: bool = False, json_output: bool = False) -> int:
    reporter = Reporter()
    env = load_env()
    now = datetime.now()
    today = now.strftime("%Y-%m-%d")

    enabled = env_bool(env, "AIBASE_CASES_ENABLED", True)
    if enabled:
        reporter.ok("AIBASE_CASES_ENABLED=1")
    else:
        reporter.warn("AIBASE_CASES_ENABLED=0 (pipeline disabled)")

    daily_max = max(1, env_int(env, "AIBASE_CASES_DAILY_MAX", 3))
    morning_count = max(0, env_int(env, "AIBASE_CASES_MORNING_COUNT", 2))
    evening_count = max(0, env_int(env, "AIBASE_CASES_EVENING_COUNT", 1))

    history_default = Path(__file__).resolve().parent / "runtime" / "aibase_cases_history.json"
    publish_history_default = Path(__file__).resolve().parent / "runtime" / "publish_history_cases.json"
    collect_history_path = resolve_path(env.get("AIBASE_CASES_HISTORY_FILE", "").strip(), history_default)
    publish_history_path = resolve_path(
        env.get("AIBASE_CASES_PUBLISH_HISTORY_FILE", "").strip(),
        publish_history_default,
    )

    collect_payload, collect_error = read_json_file(collect_history_path)
    collected_total = 0
    failed_total = 0
    if collect_error == "missing":
        reporter.warn(f"collect history missing: {collect_history_path}")
    elif collect_error:
        reporter.error(f"collect history parse failed: {collect_error}")
    else:
        if not isinstance(collect_payload, dict):
            reporter.error("collect history invalid structure (expect dict)")
        else:
            collected_ids = collect_payload.get("collected_ids", {})
            failed_ids = collect_payload.get("failed_ids", {})
            if isinstance(collected_ids, dict):
                collected_total = len(collected_ids)
            if isinstance(failed_ids, dict):
                failed_total = len(failed_ids)
            reporter.ok(
                f"collect history ok: collected_total={collected_total}, failed_total={failed_total}"
            )

            last_run = str(collect_payload.get("last_run", "")).strip()
            if last_run:
                try:
                    last_dt = datetime.fromisoformat(last_run)
                    age_hours = (now - last_dt).total_seconds() / 3600.0
                    if age_hours > 30:
                        reporter.warn(f"collector last_run is stale: {last_run}")
                    else:
                        reporter.ok(f"collector last_run={last_run}")
                except ValueError:
                    reporter.warn(f"collector last_run not ISO format: {last_run}")

    publish_payload, publish_error = read_json_file(publish_history_path)
    publish_today = 0
    publish_slots: Counter[str] = Counter()
    if publish_error == "missing":
        reporter.warn(f"publish history missing: {publish_history_path}")
    elif publish_error:
        reporter.error(f"publish history parse failed: {publish_error}")
    else:
        if not isinstance(publish_payload, list):
            reporter.error("publish history invalid structure (expect list)")
        else:
            today_entries = []
            for item in publish_payload:
                if not isinstance(item, dict):
                    continue
                date_text = str(item.get("date", "")).strip()
                if date_text.startswith(today):
                    today_entries.append(item)

            publish_today = len(today_entries)
            for item in today_entries:
                slot = str(item.get("slot", "unknown")).strip() or "unknown"
                publish_slots[slot] += 1

            if publish_today > daily_max:
                reporter.error(
                    f"today published exceeds daily max: {publish_today}>{daily_max}"
                )
            else:
                reporter.ok(
                    f"today published={publish_today}/{daily_max} "
                    f"(morning={publish_slots.get('morning', 0)}, evening={publish_slots.get('evening', 0)})"
                )

            seen_post_ids = set()
            duplicate_ids = set()
            for item in today_entries:
                post_id = int(item.get("post_id", 0) or 0)
                if post_id <= 0:
                    continue
                if post_id in seen_post_ids:
                    duplicate_ids.add(post_id)
                seen_post_ids.add(post_id)
            if duplicate_ids:
                reporter.warn(
                    "duplicate post_id in today's publish history: "
                    + ", ".join(str(x) for x in sorted(duplicate_ids))
                )

            expected_today_target = min(daily_max, morning_count + evening_count)
            reporter.ok(
                f"configured targets morning={morning_count}, evening={evening_count}, daily_max={daily_max}, "
                f"slot_total={expected_today_target}"
            )

    collect_log = Path(env.get("AIBASE_CASES_COLLECT_LOG", "/var/log/aibase_cases_collect.log"))
    publish_log = Path(env.get("AIBASE_CASES_PUBLISH_LOG", "/var/log/aibase_cases_publish.log"))

    collect_lines, collect_log_error = tail_lines(collect_log, max_lines=150)
    if collect_log_error == "missing":
        reporter.warn(f"collect log missing: {collect_log}")
    elif collect_log_error:
        reporter.warn(f"collect log read failed: {collect_log_error}")
    else:
        if any(token in line for line in collect_lines for token in ["candidate_count=", "[done] collect_aibase_cases"]):
            reporter.ok(f"collect log looks healthy: {collect_log}")
        else:
            reporter.warn(f"collect log has no recent completion marker: {collect_log}")
        recent_errors = extract_recent_errors(collect_lines)
        if recent_errors:
            reporter.warn(f"collect log has {len(recent_errors)} error-like lines in tail")

    publish_lines, publish_log_error = tail_lines(publish_log, max_lines=150)
    if publish_log_error == "missing":
        reporter.warn(f"publish log missing: {publish_log}")
    elif publish_log_error:
        reporter.warn(f"publish log read failed: {publish_log_error}")
    else:
        if any(
            token in line
            for line in publish_lines
            for token in ["[done] slot=", "no publish needed", "[ok] published"]
        ):
            reporter.ok(f"publish log looks healthy: {publish_log}")
        else:
            reporter.warn(f"publish log has no recent completion marker: {publish_log}")
        recent_errors = extract_recent_errors(publish_lines)
        if recent_errors:
            reporter.warn(f"publish log has {len(recent_errors)} error-like lines in tail")

    check_cron_entries(reporter)

    wp_url = env.get("WP_URL", "").strip()
    category_slug = env.get("AIBASE_CASES_CATEGORY_SLUG", "ai-cases").strip()
    cat_ok, cat_msg = check_wp_category(wp_url=wp_url, category_slug=category_slug)
    if cat_ok:
        reporter.ok(cat_msg)
    else:
        reporter.warn(cat_msg)

    status = "ok"
    if reporter.errors:
        status = "error"
    elif reporter.warnings:
        status = "warn"

    result = {
        "timestamp": now.isoformat(),
        "status": status,
        "today": today,
        "metrics": {
            "collected_total": collected_total,
            "failed_total": failed_total,
            "published_today": publish_today,
            "daily_max": daily_max,
            "morning_target": morning_count,
            "evening_target": evening_count,
        },
        "warnings": reporter.warnings,
        "errors": reporter.errors,
        "checks": reporter.checks,
    }

    if json_output:
        print(json.dumps(result, ensure_ascii=False, indent=2))
    else:
        print(f"[summary] status={status} today={today}")
        for item in reporter.checks:
            level = item["level"].upper().ljust(5)
            print(f"[{level}] {item['message']}")
        print(
            f"[summary] errors={len(reporter.errors)} warnings={len(reporter.warnings)} "
            f"published_today={publish_today}/{daily_max}"
        )

    if reporter.errors:
        return 2
    if strict and reporter.warnings:
        return 1
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Health check for AIBase cases pipeline.")
    parser.add_argument("--json", action="store_true", help="Output JSON format.")
    parser.add_argument(
        "--strict",
        action="store_true",
        help="Return non-zero when warnings exist.",
    )
    args = parser.parse_args()
    return run_health_check(strict=args.strict, json_output=args.json)


if __name__ == "__main__":
    sys.exit(main())
