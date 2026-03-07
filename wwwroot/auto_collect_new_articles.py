#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Auto-collect newly scanned articles into WordPress drafts.
Finds the latest new_articles_*.json and runs collect_new_articles.py.
"""
import argparse
import json
import os
import sys
import glob
import subprocess
from datetime import datetime


def _now_iso():
    return datetime.now().isoformat()


def _load_state(path):
    if not os.path.exists(path):
        return {}
    try:
        with open(path, "r", encoding="utf-8") as f:
            return json.load(f) or {}
    except Exception:
        return {}


def _save_state(path, data):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)


def _find_latest(pattern):
    files = glob.glob(pattern)
    if not files:
        return None
    files.sort(key=lambda p: os.path.getmtime(p), reverse=True)
    return files[0]


def main():
    parser = argparse.ArgumentParser(description="Auto collect new articles into WordPress drafts")
    parser.add_argument("--limit", type=int, default=20, help="Max items to collect per run")
    args = parser.parse_args()

    base_dir = os.path.dirname(os.path.abspath(__file__))
    os.chdir(base_dir)

    pattern = "new_articles_*.json"
    state_path = os.path.join(base_dir, "logs", "collect_new_articles_state.json")

    latest = _find_latest(pattern)
    if not latest:
        print(f"[{_now_iso()}] no new_articles file found")
        return 0

    latest_mtime = os.path.getmtime(latest)

    try:
        with open(latest, "r", encoding="utf-8") as f:
            payload = json.load(f)
    except Exception as exc:
        print(f"[{_now_iso()}] failed to read {latest}: {exc}")
        return 1

    if not payload:
        print(f"[{_now_iso()}] empty list in {os.path.basename(latest)}, mark as processed")
        _save_state(state_path, {
            "last_file": os.path.basename(latest),
            "last_mtime": latest_mtime,
            "last_count": 0,
            "last_run": _now_iso(),
        })
        return 0

    cmd = [sys.executable, "collect_new_articles.py", "--file", os.path.basename(latest)]
    if args.limit and args.limit > 0:
        cmd.extend(["--limit", str(args.limit)])
    print(f"[{_now_iso()}] run: {' '.join(cmd)}")
    result = subprocess.run(cmd)
    if result.returncode != 0:
        print(f"[{_now_iso()}] collect_new_articles.py failed with code {result.returncode}")
        return result.returncode

    state = _load_state(state_path)
    _save_state(state_path, {
        "last_file": os.path.basename(latest),
        "last_mtime": latest_mtime,
        "last_count": len(payload),
        "last_limit": args.limit,
        "last_run": _now_iso(),
    })
    print(f"[{_now_iso()}] done: {os.path.basename(latest)} ({len(payload)} items)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
