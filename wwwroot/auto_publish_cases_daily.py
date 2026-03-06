#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Publish AI case drafts daily in controlled slots.
Only publishes posts under category "AI案例" by default.
"""

import argparse
import io
import json
import os
import re
import subprocess
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import requests


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


def parse_json_from_text(text: str) -> Dict[str, object]:
    try:
        payload = json.loads(text)
        return payload if isinstance(payload, dict) else {}
    except json.JSONDecodeError:
        match = re.search(r"\{.*\}", text, re.S)
        if not match:
            return {}
        try:
            payload = json.loads(match.group(0))
            return payload if isinstance(payload, dict) else {}
        except json.JSONDecodeError:
            return {}


def ensure_parent_dir(file_path: Path) -> None:
    file_path.parent.mkdir(parents=True, exist_ok=True)


class AIBaseCasesPublisher:
    def __init__(self, env: Dict[str, str], dry_run: bool = False):
        self.env = env
        self.dry_run = dry_run

        self.wp_url = env.get("WP_URL", "").rstrip("/")
        self.api_key = (
            env.get("WP_API_KEY")
            or env.get("WP_APP_PASSWORD")
            or env.get("WP_ADMIN_PASSWORD")
            or env.get("SERVER_PASSWORD")
            or ""
        ).strip()

        self.category_name = env.get("AIBASE_CASES_CATEGORY", "AI案例").strip() or "AI案例"
        self.category_slug = env.get("AIBASE_CASES_CATEGORY_SLUG", "ai-cases").strip() or "ai-cases"
        self.require_thumbnail = env_bool(env, "AIBASE_CASES_REQUIRE_THUMB", True)
        self.daily_max = max(1, min(8, env_int(env, "AIBASE_CASES_DAILY_MAX", 3)))
        self.morning_count = max(0, min(6, env_int(env, "AIBASE_CASES_MORNING_COUNT", 2)))
        self.evening_count = max(0, min(6, env_int(env, "AIBASE_CASES_EVENING_COUNT", 1)))
        self.manual_default_count = max(1, min(6, env_int(env, "AIBASE_CASES_MANUAL_COUNT", 2)))

        self.db_name = env.get("DB_NAME", "").strip()
        self.db_user = env.get("DB_USER", "").strip()
        self.db_password = env.get("DB_PASSWORD", "").strip()
        self.db_host = env.get("DB_HOST", "localhost").strip() or "localhost"
        self.db_prefix = env.get("DB_TABLE_PREFIX", "blog_").strip() or "blog_"

        history_default = Path(__file__).resolve().parent / "runtime" / "publish_history_cases.json"
        history_path = env.get("AIBASE_CASES_PUBLISH_HISTORY_FILE", "").strip()
        self.history_path = self.resolve_path(history_path, history_default)

        self.session = requests.Session()
        self.session.headers.update(
            {
                "User-Agent": (
                    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                    "AppleWebKit/537.36 (KHTML, like Gecko) "
                    "Chrome/122.0.0.0 Safari/537.36"
                ),
                "Accept": "application/json,text/plain,*/*",
            }
        )

    def resolve_path(self, raw_path: str, default_path: Path) -> Path:
        if not raw_path:
            return default_path
        path_obj = Path(raw_path)
        if path_obj.is_absolute():
            return path_obj
        normalized = raw_path.replace("\\", "/")
        script_dir = Path(__file__).resolve().parent
        if normalized.startswith("wwwroot/"):
            return (script_dir.parent / path_obj).resolve()
        return (script_dir / path_obj).resolve()

    def validate(self) -> bool:
        missing = []
        if not self.wp_url:
            missing.append("WP_URL")
        if not self.api_key:
            missing.append("WP_API_KEY (or WP_APP_PASSWORD/WP_ADMIN_PASSWORD)")
        if missing:
            print("[error] missing required config:")
            for item in missing:
                print(f"  - {item}")
            return False
        return True

    def load_history(self) -> List[Dict[str, object]]:
        if not self.history_path.exists():
            return []
        try:
            with self.history_path.open("r", encoding="utf-8") as fh:
                payload = json.load(fh)
                return payload if isinstance(payload, list) else []
        except (OSError, json.JSONDecodeError):
            return []

    def save_history(self, entries: List[Dict[str, object]]) -> None:
        ensure_parent_dir(self.history_path)
        with self.history_path.open("w", encoding="utf-8") as fh:
            json.dump(entries, fh, ensure_ascii=False, indent=2)

    def get_category_id(self) -> Optional[int]:
        fallback_id = env_int(self.env, "AIBASE_CASES_CATEGORY_ID", 0)
        query_params_list = []
        if self.category_name:
            query_params_list.append({"search": self.category_name, "per_page": 50})
        if self.category_slug:
            query_params_list.append({"slug": self.category_slug, "per_page": 50})

        for query_params in query_params_list:
            try:
                response = self.session.get(
                    f"{self.wp_url}/wp-json/wp/v2/categories",
                    params=query_params,
                    timeout=20,
                )
                response.raise_for_status()
                categories = response.json()
                if not isinstance(categories, list):
                    continue

                target_name = self.category_name.strip().lower()
                target_slug = self.category_slug.strip().lower()
                for category in categories:
                    if not isinstance(category, dict):
                        continue
                    name = str(category.get("name", "")).strip().lower()
                    slug = str(category.get("slug", "")).strip().lower()
                    if (target_name and name == target_name) or (target_slug and slug == target_slug):
                        cat_id = int(category.get("id", 0) or 0)
                        if cat_id > 0:
                            return cat_id
                if categories:
                    cat_id = int(categories[0].get("id", 0) or 0)
                    if cat_id > 0:
                        return cat_id
            except requests.RequestException as exc:
                print(f"[warn] category query failed ({query_params}): {exc}")

        return fallback_id if fallback_id > 0 else None

    def get_drafts(self) -> List[Dict[str, object]]:
        endpoint = f"{self.wp_url}/get_drafts.php"
        try:
            response = self.session.get(endpoint, params={"api_key": self.api_key}, timeout=30)
            response.raise_for_status()
            payload = response.json()
            if not payload.get("success"):
                print(f"[warn] get_drafts failed: {payload}")
                return []
            data = payload.get("data", [])
            return data if isinstance(data, list) else []
        except requests.RequestException as exc:
            print(f"[warn] get_drafts request failed: {exc}")
            return []

    def publish_one(self, post_id: int) -> Tuple[bool, Dict[str, object]]:
        if self.dry_run:
            return True, {"success": True, "link": "", "post_id": post_id, "dry_run": True}
        endpoint = f"{self.wp_url}/publish_draft.php"
        try:
            response = self.session.post(
                endpoint,
                data={"post_id": post_id, "api_key": self.api_key},
                timeout=30,
            )
            response.raise_for_status()
            payload = parse_json_from_text(response.text)
            if payload.get("success"):
                return True, payload
            return False, payload
        except requests.RequestException as exc:
            return False, {"error": str(exc)}

    def select_candidates(self, drafts: List[Dict[str, object]], category_id: int) -> List[Dict[str, object]]:
        selected = []
        for draft in drafts:
            if not isinstance(draft, dict):
                continue

            categories = draft.get("categories", [])
            if not isinstance(categories, list):
                categories = []
            category_ids = []
            for item in categories:
                try:
                    category_ids.append(int(item))
                except (TypeError, ValueError):
                    continue
            if category_id not in category_ids:
                continue

            if self.require_thumbnail and not bool(draft.get("has_thumbnail", False)):
                continue

            draft_id = int(draft.get("id", 0) or 0)
            if draft_id <= 0:
                continue
            selected.append(draft)

        def _sort_key(item: Dict[str, object]) -> str:
            return str(item.get("date", "")).strip()

        selected.sort(key=_sort_key)
        return selected

    def get_candidates_via_db(self, category_id: int) -> List[Dict[str, object]]:
        if not self.db_name or not self.db_user or not self.db_password:
            return []

        posts_table = f"{self.db_prefix}posts"
        terms_table = f"{self.db_prefix}terms"
        tax_table = f"{self.db_prefix}term_taxonomy"
        rel_table = f"{self.db_prefix}term_relationships"
        meta_table = f"{self.db_prefix}postmeta"

        sql = (
            f"SELECT p.ID, p.post_title, p.post_date, "
            f"COALESCE(pm.meta_value,'') AS thumb_id "
            f"FROM {posts_table} p "
            f"JOIN {rel_table} tr ON p.ID = tr.object_id "
            f"JOIN {tax_table} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id "
            f"JOIN {terms_table} t ON tt.term_id = t.term_id "
            f"LEFT JOIN {meta_table} pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id' "
            f"WHERE p.post_type='post' AND p.post_status='draft' "
            f"AND tt.taxonomy='category' AND t.term_id={int(category_id)} "
            f"ORDER BY p.post_date ASC LIMIT 500;"
        )

        cmd = [
            "mysql",
            f"-h{self.db_host}",
            f"-u{self.db_user}",
            f"-p{self.db_password}",
            "-D",
            self.db_name,
            "-N",
            "-B",
            "-e",
            sql,
        ]
        try:
            process = subprocess.run(cmd, capture_output=True, text=True, check=False, timeout=30)
        except (OSError, subprocess.TimeoutExpired):
            return []

        if process.returncode != 0:
            return []

        drafts: List[Dict[str, object]] = []
        for line in process.stdout.splitlines():
            row = line.rstrip("\n")
            if not row:
                continue
            parts = row.split("\t")
            if len(parts) < 4:
                continue
            try:
                post_id = int(parts[0])
            except ValueError:
                continue
            title = parts[1].strip()
            date_text = parts[2].strip()
            thumb_id = str(parts[3]).strip()
            has_thumbnail = bool(thumb_id and thumb_id != "0")
            drafts.append(
                {
                    "id": post_id,
                    "title": title,
                    "date": date_text,
                    "has_thumbnail": has_thumbnail,
                    "categories": [category_id],
                }
            )
        return drafts

    def today_key(self) -> str:
        return datetime.now().strftime("%Y-%m-%d")

    def run(self, slot: str, count_override: Optional[int]) -> int:
        if not self.validate():
            return 1

        category_id = self.get_category_id()
        if category_id is None:
            print(f"[error] cannot resolve category id for '{self.category_name}'")
            return 1

        history = self.load_history()
        today = self.today_key()
        today_entries = [entry for entry in history if str(entry.get("date", "")).startswith(today)]
        today_count = len(today_entries)
        today_published_ids = {int(entry.get("post_id", 0) or 0) for entry in today_entries}

        if slot == "morning":
            target_total = self.morning_count
        elif slot == "evening":
            target_total = self.morning_count + self.evening_count
        else:
            manual_count = count_override if count_override is not None else self.manual_default_count
            target_total = today_count + max(1, min(6, manual_count))

        target_total = min(target_total, self.daily_max)
        if today_count >= target_total:
            print(f"[info] slot={slot} no publish needed. today_count={today_count}, target_total={target_total}")
            return 0

        need_publish = target_total - today_count
        db_candidates = self.get_candidates_via_db(category_id=category_id)
        if db_candidates:
            candidates = db_candidates
        else:
            drafts = self.get_drafts()
            candidates = self.select_candidates(drafts, category_id=category_id)
        candidates = [item for item in candidates if int(item.get("id", 0) or 0) not in today_published_ids]

        if not candidates:
            print("[info] no candidate drafts in AI案例 category.")
            return 0

        to_publish = candidates[:need_publish]
        print(
            f"[info] slot={slot} category_id={category_id} "
            f"today_count={today_count} target_total={target_total} "
            f"need_publish={need_publish} candidate_count={len(candidates)}"
        )

        success_count = 0
        for item in to_publish:
            post_id = int(item.get("id", 0) or 0)
            title = str(item.get("title", "")).strip()
            ok, payload = self.publish_one(post_id)
            if ok:
                success_count += 1
                if not self.dry_run:
                    history.append(
                        {
                            "date": datetime.now().isoformat(),
                            "slot": slot,
                            "post_id": post_id,
                            "title": title,
                            "link": payload.get("link", ""),
                        }
                    )
                print(f"[ok] published post_id={post_id} title={title}")
            else:
                print(f"[warn] publish failed post_id={post_id} title={title} reason={payload}")
            time.sleep(1)

        if not self.dry_run:
            self.save_history(history)
        print(f"[done] slot={slot} published={success_count}/{len(to_publish)}")
        return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Publish AI case drafts daily by slot.")
    parser.add_argument(
        "--slot",
        choices=["morning", "evening", "manual"],
        default="manual",
        help="morning/evening follow configured targets; manual publishes count directly.",
    )
    parser.add_argument("--count", type=int, default=None, help="Only for --slot manual.")
    parser.add_argument("--dry-run", action="store_true", help="Show planned publish actions only.")
    args = parser.parse_args()

    env = load_env()
    publisher = AIBaseCasesPublisher(env=env, dry_run=args.dry_run)
    return publisher.run(slot=args.slot, count_override=args.count)


if __name__ == "__main__":
    sys.exit(main())
