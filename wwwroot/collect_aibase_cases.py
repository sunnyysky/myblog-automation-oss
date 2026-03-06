#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Collect AI cases from AIBase and save to WordPress drafts.

Data source:
  https://www.aibase.com/zh/cases
  (backed by app.chinaz.com JSON endpoints)
"""

import argparse
import io
import json
import os
import re
import sys
import time
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import requests
from bs4 import BeautifulSoup


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


def ensure_parent_dir(file_path: Path) -> None:
    file_path.parent.mkdir(parents=True, exist_ok=True)


def parse_json_from_text(text: str) -> Dict[str, object]:
    try:
        payload = json.loads(text)
        if isinstance(payload, dict):
            return payload
        return {"success": True, "data": payload}
    except json.JSONDecodeError:
        match = re.search(r"\{.*\}", text, re.S)
        if not match:
            return {}
        try:
            payload = json.loads(match.group(0))
            return payload if isinstance(payload, dict) else {}
        except json.JSONDecodeError:
            return {}


def normalize_title(title: str) -> str:
    clean = (title or "").strip().lower()
    clean = re.sub(r"[\s\u3000]+", "", clean)
    clean = re.sub(r"[“”\"'‘’`·,，.。:：;；!！?？\-\(\)（）\[\]【】<>《》/\\|]+", "", clean)
    return clean


def sanitize_text(text: str, max_len: int = 170) -> str:
    clean = re.sub(r"\s+", " ", (text or "").strip())
    if len(clean) <= max_len:
        return clean
    return clean[: max_len - 1].rstrip() + "…"


def parse_tags(raw_tags: object) -> List[str]:
    tags: List[str] = []
    if isinstance(raw_tags, list):
        tags = [str(x).strip() for x in raw_tags if str(x).strip()]
        return tags
    if isinstance(raw_tags, str):
        source = raw_tags.strip()
        if not source:
            return []
        try:
            decoded = json.loads(source)
            if isinstance(decoded, list):
                return [str(x).strip() for x in decoded if str(x).strip()]
        except json.JSONDecodeError:
            pass
        split_tags = re.split(r"[,，/|]", source)
        return [t.strip() for t in split_tags if t.strip()]
    return []


class AIBaseCasesCollector:
    def __init__(self, env: Dict[str, str], dry_run: bool = False):
        self.env = env
        self.dry_run = dry_run

        self.wp_url = env.get("WP_URL", "").rstrip("/")
        self.wp_rest_base = env.get("WP_REST_BASE", f"{self.wp_url}/wp-json/myblog/v1").rstrip("/")
        self.wp_use_rest_api = env_bool(env, "WP_USE_REST_API", False)
        self.api_key = (
            env.get("WP_API_KEY")
            or env.get("WP_APP_PASSWORD")
            or env.get("WP_ADMIN_PASSWORD")
            or env.get("SERVER_PASSWORD")
            or ""
        ).strip()

        self.category_name = env.get("AIBASE_CASES_CATEGORY", "AI案例").strip() or "AI案例"
        self.default_tags = [
            x.strip()
            for x in env.get("AIBASE_CASES_TAGS", "AI案例,案例拆解,AI变现").split(",")
            if x.strip()
        ]
        self.draft_status = env.get("AIBASE_CASES_DRAFT_STATUS", "draft").strip() or "draft"
        self.page_size = max(5, min(100, env_int(env, "AIBASE_CASES_PAGE_SIZE", 50)))
        self.batch_size = max(1, min(500, env_int(env, "AIBASE_CASES_BATCH_SIZE", 40)))
        self.min_text_len = max(80, env_int(env, "AIBASE_CASES_MIN_TEXT_LEN", 180))
        self.title_min_len = max(4, env_int(env, "AIBASE_CASES_TITLE_MIN_LEN", 8))
        self.title_max_len = max(20, env_int(env, "AIBASE_CASES_TITLE_MAX_LEN", 80))
        self.require_thumb = env_bool(env, "AIBASE_CASES_REQUIRE_THUMB", True)
        self.hide_source_link = env_bool(env, "AIBASE_CASES_NO_SOURCE_LINK", True)
        self.sleep_seconds = max(0, env_int(env, "AIBASE_CASES_SLEEP_SECONDS", 1))
        self.flag = env.get("AIBASE_CASES_FLAG", "zh").strip() or "zh"
        self.list_type = env_int(env, "AIBASE_CASES_TYPE", 5)
        self.enabled = env_bool(env, "AIBASE_CASES_ENABLED", True)

        self.api_base = env.get(
            "AIBASE_CASES_API_BASE",
            "https://app.chinaz.com/djflkdsoisknfoklsyhownfrlewfknoiaewf",
        ).rstrip("/")
        self.list_endpoint = f"{self.api_base}/ai/GetAiInfoList.aspx"
        self.detail_endpoint = f"{self.api_base}/ai/GetAiCommunityById.aspx"

        history_default = Path(__file__).resolve().parent / "runtime" / "aibase_cases_history.json"
        history_path = env.get("AIBASE_CASES_HISTORY_FILE", "").strip()
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

        self.history = self.load_history()
        self.collected_ids: Dict[str, Dict[str, object]] = dict(self.history.get("collected_ids", {}))
        self.failed_ids: Dict[str, Dict[str, object]] = dict(self.history.get("failed_ids", {}))
        self.existing_titles = set()
        self.non_retry_failed_ids = self.build_non_retry_failed_ids()

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

    def build_non_retry_failed_ids(self) -> set:
        non_retry_reasons = {
            "duplicate_title",
            "title_length_invalid",
            "content_too_short",
            "missing_thumb",
            "thumb_upload_failed",
        }
        ids = set()
        for case_id, payload in self.failed_ids.items():
            if not isinstance(payload, dict):
                continue
            reason = str(payload.get("reason", "")).strip()
            if reason in non_retry_reasons:
                ids.add(str(case_id))
        return ids

    def record_failed(self, case_id: str, payload: Dict[str, object]) -> None:
        if self.dry_run:
            return
        self.failed_ids[case_id] = payload
        reason = str(payload.get("reason", "")).strip()
        if reason in {
            "duplicate_title",
            "title_length_invalid",
            "content_too_short",
            "missing_thumb",
            "thumb_upload_failed",
        }:
            self.non_retry_failed_ids.add(case_id)

    def load_history(self) -> Dict[str, object]:
        if not self.history_path.exists():
            return {}
        try:
            with self.history_path.open("r", encoding="utf-8") as fh:
                payload = json.load(fh)
                return payload if isinstance(payload, dict) else {}
        except (OSError, json.JSONDecodeError):
            return {}

    def save_history(self) -> None:
        ensure_parent_dir(self.history_path)
        payload = {
            "collected_ids": self.collected_ids,
            "failed_ids": self.failed_ids,
            "last_run": datetime.now().isoformat(),
        }
        with self.history_path.open("w", encoding="utf-8") as fh:
            json.dump(payload, fh, ensure_ascii=False, indent=2)

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

    def request_json(self, url: str, params: Optional[Dict[str, object]] = None) -> object:
        response = self.session.get(url, params=params or {}, timeout=30)
        response.raise_for_status()
        return response.json()

    def fetch_existing_titles(self) -> None:
        self.existing_titles.clear()
        endpoints = [
            f"{self.wp_url}/get_published_posts.php",
            f"{self.wp_url}/get_drafts.php",
        ]
        for endpoint in endpoints:
            try:
                response = self.session.get(endpoint, params={"api_key": self.api_key}, timeout=30)
                response.raise_for_status()
                payload = response.json()
                if not payload.get("success"):
                    continue
                for post in payload.get("data", []):
                    title = str(post.get("title", "")).strip()
                    if title:
                        self.existing_titles.add(normalize_title(title))
            except requests.RequestException as exc:
                print(f"[warn] failed to load titles from {endpoint}: {exc}")
        print(f"[info] loaded existing title fingerprints: {len(self.existing_titles)}")

    def fetch_cases(self, max_pages: Optional[int] = None) -> List[Dict[str, object]]:
        records: List[Dict[str, object]] = []
        page = 1
        seen_ids = set()

        while True:
            if max_pages is not None and page > max_pages:
                break
            params = {
                "flag": self.flag,
                "type": self.list_type,
                "page": page,
                "pagesize": self.page_size,
                "catename": "",
            }
            try:
                payload = self.request_json(self.list_endpoint, params=params)
            except requests.RequestException as exc:
                print(f"[warn] fetch list page {page} failed: {exc}")
                break

            if not isinstance(payload, list) or not payload:
                break

            new_count = 0
            for item in payload:
                if not isinstance(item, dict):
                    continue
                case_id = str(item.get("Id", "")).strip()
                if not case_id or case_id in seen_ids:
                    continue
                seen_ids.add(case_id)
                records.append(item)
                new_count += 1

            print(f"[info] list page {page}: {new_count} records")
            page += 1

            if len(payload) < self.page_size:
                break

        print(f"[info] total fetched case records: {len(records)}")
        return records

    def fetch_case_detail(self, case_id: str) -> Optional[Dict[str, object]]:
        params = {"id": case_id, "flag": self.flag}
        try:
            payload = self.request_json(self.detail_endpoint, params=params)
        except requests.RequestException as exc:
            print(f"[warn] fetch detail failed for case {case_id}: {exc}")
            return None

        if not isinstance(payload, dict):
            return None
        data = payload.get("data")
        if not isinstance(data, dict):
            return None
        return data

    def clean_summary_html(self, html: str) -> str:
        soup = BeautifulSoup(html or "", "html.parser")

        for tag in soup.find_all(["script", "style", "iframe", "object", "form", "video", "audio"]):
            tag.decompose()

        for image in soup.find_all("img"):
            src = (image.get("src") or "").strip()
            if src.startswith("//"):
                src = "https:" + src
            image["src"] = src
            image["loading"] = "lazy"
            image["referrerpolicy"] = "no-referrer"
            image.attrs.pop("srcset", None)
            image.attrs.pop("sizes", None)

        if self.hide_source_link:
            for a in soup.find_all("a"):
                href = (a.get("href") or "").strip().lower()
                text = a.get_text(" ", strip=True)
                if "aibase.com" in href or "chinaz.com" in href or "来源" in text:
                    a.unwrap()

            for node in soup.find_all(["p", "div", "span", "strong"]):
                text = node.get_text(" ", strip=True)
                if not text:
                    continue
                if re.search(r"(来源|原文|出处)", text):
                    node.decompose()

        cleaned = str(soup).strip()
        return cleaned

    def html_plain_text(self, html: str) -> str:
        soup = BeautifulSoup(html or "", "html.parser")
        text = soup.get_text(" ", strip=True)
        text = re.sub(r"\s+", " ", text)
        return text.strip()

    def build_post_content(
        self,
        case_id: str,
        title: str,
        subtitle: str,
        description: str,
        summary_html: str,
        tags: List[str],
        addtime_text: str,
    ) -> str:
        tag_blocks = ""
        if tags:
            snippets = []
            for tag in tags[:8]:
                snippets.append(
                    '<span style="display:inline-block;margin:0 8px 8px 0;padding:4px 10px;'
                    'border-radius:999px;background:#edf6ff;color:#2a5d8f;font-size:12px;">'
                    + tag
                    + "</span>"
                )
            tag_blocks = "".join(snippets)

        meta_block = ""
        if addtime_text:
            meta_block = (
                '<p style="margin:0 0 10px;color:#6f86a2;font-size:13px;">'
                f"发布时间：{addtime_text}"
                "</p>"
            )

        subtitle_block = ""
        if subtitle:
            subtitle_block = (
                '<p style="margin:0 0 12px;color:#355877;font-size:16px;font-weight:600;">'
                + subtitle
                + "</p>"
            )

        desc_block = ""
        if description:
            desc_block = (
                '<div style="margin:0 0 14px;padding:12px 14px;border-radius:12px;'
                'border:1px solid #dceaf9;background:linear-gradient(140deg,#f7fbff,#eef6ff);'
                'color:#2f4e6e;font-size:14px;line-height:1.75;">'
                "<strong>案例摘要：</strong>"
                + description
                + "</div>"
            )

        content = (
            '<section class="aibase-case-card" style="margin:0 0 18px;padding:16px 16px 10px;'
            'border-radius:14px;border:1px solid #d9e8fb;background:#fff;'
            'box-shadow:0 10px 18px rgba(18,67,128,0.08);">'
            + subtitle_block
            + meta_block
            + desc_block
            + tag_blocks
            + "</section>"
            + '<section class="aibase-case-main" style="line-height:1.9;color:#223a54;">'
            + summary_html
            + "</section>"
            + f"\n<!-- aibase_case_id:{case_id} -->\n"
        )
        return content

    def upload_featured_image(self, image_url: str) -> Tuple[Optional[int], Optional[str]]:
        if not image_url:
            return None, None
        endpoints = []
        if self.wp_use_rest_api:
            endpoints.append(f"{self.wp_rest_base}/upload-image")
        endpoints.append(f"{self.wp_url}/wp_upload_image.php")

        for upload_endpoint in endpoints:
            try:
                response = self.session.post(
                    upload_endpoint,
                    data={"img_url": image_url, "api_key": self.api_key},
                    timeout=60,
                )
                response.raise_for_status()
                payload = parse_json_from_text(response.text)
                if not payload.get("success"):
                    continue
                attachment_id = payload.get("attachment_id")
                if attachment_id:
                    try:
                        attachment_id = int(attachment_id)
                    except (TypeError, ValueError):
                        attachment_id = None
                image_wp_url = payload.get("url")
                return attachment_id, image_wp_url
            except requests.RequestException:
                continue
        return None, None

    def publish_draft(self, payload: Dict[str, object]) -> Tuple[bool, Dict[str, object]]:
        if self.dry_run:
            return True, {"post_id": 0, "url": "", "message": "dry-run"}
        endpoint = f"{self.wp_url}/wp_publish_helper.php"
        request_payload = dict(payload)
        request_payload["api_key"] = self.api_key
        try:
            response = self.session.post(endpoint, json=request_payload, timeout=60)
            response.raise_for_status()
            data = parse_json_from_text(response.text)
            if data.get("success"):
                return True, data
            return False, data
        except requests.RequestException as exc:
            return False, {"error": str(exc)}

    def candidate_sort_key(self, item: Dict[str, object]) -> str:
        addtime = str(item.get("addtime", "") or item.get("added", "")).strip()
        if not addtime:
            return "9999-99-99T99:99:99"
        return addtime

    def run(self, mode: str, max_pages: Optional[int], batch_size_override: Optional[int]) -> int:
        if not self.enabled:
            print("[info] AIBASE_CASES_ENABLED=0, skip.")
            return 0
        if not self.validate():
            return 1

        self.fetch_existing_titles()
        all_cases = self.fetch_cases(max_pages=max_pages)
        if not all_cases:
            print("[info] no case records.")
            return 0

        selected_batch_size = self.batch_size
        if batch_size_override is not None:
            selected_batch_size = max(1, min(500, batch_size_override))

        filtered: List[Dict[str, object]] = []
        for item in all_cases:
            case_id = str(item.get("Id", "")).strip()
            if not case_id:
                continue
            if case_id in self.collected_ids:
                continue
            if case_id in self.non_retry_failed_ids:
                continue
            filtered.append(item)

        if mode == "backfill":
            filtered.sort(key=self.candidate_sort_key)
        else:
            filtered.sort(key=self.candidate_sort_key, reverse=True)

        candidates = filtered[:selected_batch_size]
        print(f"[info] mode={mode}, candidate_count={len(candidates)}, batch_size={selected_batch_size}")
        if not candidates:
            if not self.dry_run:
                self.save_history()
            return 0

        success_count = 0
        skipped_count = 0
        failed_count = 0

        for idx, item in enumerate(candidates, 1):
            case_id = str(item.get("Id", "")).strip()
            title = str(item.get("title", "")).strip()
            if not case_id or not title:
                skipped_count += 1
                continue

            title_norm = normalize_title(title)
            if title_norm in self.existing_titles:
                skipped_count += 1
                self.record_failed(
                    case_id,
                    {"reason": "duplicate_title", "title": title, "at": datetime.now().isoformat()},
                )
                continue

            detail = self.fetch_case_detail(case_id)
            if not detail:
                failed_count += 1
                self.record_failed(
                    case_id,
                    {"reason": "detail_fetch_failed", "title": title, "at": datetime.now().isoformat()},
                )
                continue

            title = str(detail.get("title", "") or title).strip()
            subtitle = str(detail.get("subtitle", "")).strip()
            description = sanitize_text(str(detail.get("description", "")).strip(), max_len=180)
            summary_html = self.clean_summary_html(str(detail.get("summary", "") or detail.get("content", "")).strip())
            text_len = len(self.html_plain_text(summary_html))
            thumb = str(detail.get("thumb", "") or item.get("thumb", "")).strip()
            addtime_text = str(detail.get("addtime", "") or item.get("addtime", "")).strip()

            if len(title) < self.title_min_len or len(title) > self.title_max_len:
                skipped_count += 1
                self.record_failed(
                    case_id,
                    {
                        "reason": "title_length_invalid",
                        "title": title,
                        "title_len": len(title),
                        "at": datetime.now().isoformat(),
                    },
                )
                continue

            if text_len < self.min_text_len:
                skipped_count += 1
                self.record_failed(
                    case_id,
                    {
                        "reason": "content_too_short",
                        "title": title,
                        "text_len": text_len,
                        "at": datetime.now().isoformat(),
                    },
                )
                continue

            if self.require_thumb and not thumb:
                skipped_count += 1
                self.record_failed(
                    case_id,
                    {"reason": "missing_thumb", "title": title, "at": datetime.now().isoformat()},
                )
                continue

            tags = parse_tags(detail.get("tags", ""))
            merged_tags = []
            seen_tags = set()
            for tag in tags + self.default_tags:
                if not tag:
                    continue
                key = tag.strip().lower()
                if key in seen_tags:
                    continue
                seen_tags.add(key)
                merged_tags.append(tag.strip())
                if len(merged_tags) >= 10:
                    break

            content_html = self.build_post_content(
                case_id=case_id,
                title=title,
                subtitle=subtitle,
                description=description,
                summary_html=summary_html,
                tags=merged_tags,
                addtime_text=addtime_text,
            )
            excerpt = description if description else sanitize_text(self.html_plain_text(summary_html), max_len=150)
            seo_keywords = ", ".join(merged_tags[:5])

            attachment_id = None
            if thumb:
                attachment_id, _ = self.upload_featured_image(thumb)

            if self.require_thumb and not attachment_id and not self.dry_run:
                skipped_count += 1
                self.record_failed(
                    case_id,
                    {"reason": "thumb_upload_failed", "title": title, "at": datetime.now().isoformat()},
                )
                continue

            publish_payload = {
                "title": title,
                "content": content_html,
                "status": self.draft_status,
                "excerpt": excerpt,
                "categories": [self.category_name],
                "tags": merged_tags,
                "seo_title": title,
                "seo_description": excerpt,
                "seo_keywords": seo_keywords,
            }
            if attachment_id:
                publish_payload["featured_image"] = attachment_id

            ok, result = self.publish_draft(publish_payload)
            if ok:
                post_id = int(result.get("post_id", 0) or 0)
                post_url = str(result.get("url", "") or "")
                success_count += 1
                if not self.dry_run:
                    self.existing_titles.add(normalize_title(title))
                    self.collected_ids[case_id] = {
                        "title": title,
                        "post_id": post_id,
                        "url": post_url,
                        "mode": mode,
                        "collected_at": datetime.now().isoformat(),
                    }
                print(f"[ok] {idx}/{len(candidates)} case_id={case_id} post_id={post_id} title={title}")
            else:
                failed_count += 1
                self.record_failed(
                    case_id,
                    {
                        "reason": "publish_failed",
                        "title": title,
                        "detail": str(result)[:240],
                        "at": datetime.now().isoformat(),
                    },
                )
                print(f"[warn] {idx}/{len(candidates)} publish failed case_id={case_id} title={title}")

            if self.sleep_seconds > 0:
                time.sleep(self.sleep_seconds)

        if not self.dry_run:
            self.save_history()
        print(
            "[done] collect_aibase_cases",
            f"success={success_count}",
            f"skipped={skipped_count}",
            f"failed={failed_count}",
        )
        return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Collect AIBase cases into WordPress drafts.")
    parser.add_argument(
        "--mode",
        choices=["backfill", "incremental"],
        default="incremental",
        help="backfill=old to new; incremental=new to old",
    )
    parser.add_argument("--max-pages", type=int, default=None, help="Limit list pages for debug/testing.")
    parser.add_argument("--batch-size", type=int, default=None, help="Override AIBASE_CASES_BATCH_SIZE.")
    parser.add_argument("--dry-run", action="store_true", help="Validate and print actions without publishing.")
    args = parser.parse_args()

    env = load_env()
    collector = AIBaseCasesCollector(env=env, dry_run=args.dry_run)
    return collector.run(mode=args.mode, max_pages=args.max_pages, batch_size_override=args.batch_size)


if __name__ == "__main__":
    sys.exit(main())
