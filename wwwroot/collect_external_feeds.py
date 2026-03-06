#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Collect external RSS/Atom feeds and publish a daily digest post to WordPress.

Usage:
  python wwwroot/collect_external_feeds.py
  python wwwroot/collect_external_feeds.py --dry-run
"""

import argparse
import hashlib
import json
import re
import sys
from collections import defaultdict
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from email.utils import parsedate_to_datetime
from pathlib import Path
from typing import Dict, List, Optional
import xml.etree.ElementTree as ET

import requests


CN_TZ = timezone(timedelta(hours=8))
DEFAULT_FEEDS = [
    {
        "name": "OpenAI News",
        "url": "https://openai.com/news/rss.xml",
        "type": "rss",
    },
    {
        "name": "Hacker News",
        "url": "https://hnrss.org/frontpage",
        "type": "rss",
    },
    {
        "name": "InfoQ",
        "url": "https://www.infoq.com/feed/",
        "type": "rss",
    },
]


def load_env() -> Dict[str, str]:
    env: Dict[str, str] = {}
    root_dir = Path(__file__).resolve().parent.parent
    candidates = [
        root_dir / ".env",
        Path(__file__).resolve().parent / ".env",
        Path(".env"),
    ]
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


def env_int(env: Dict[str, str], key: str, default: int) -> int:
    raw = env.get(key, "").strip()
    if raw == "":
        return default
    try:
        return int(raw)
    except ValueError:
        return default


def env_bool(env: Dict[str, str], key: str, default: bool = False) -> bool:
    raw = env.get(key, "").strip().lower()
    if raw == "":
        return default
    return raw in {"1", "true", "yes", "on"}


def normalize_text(text: str, max_len: int = 170) -> str:
    clean = re.sub(r"<[^>]+>", " ", text or "")
    clean = re.sub(r"\s+", " ", clean).strip()
    if len(clean) <= max_len:
        return clean
    return clean[: max_len - 1].rstrip() + "…"


def parse_external_feeds(raw: str) -> List[Dict[str, str]]:
    if not raw:
        return DEFAULT_FEEDS
    try:
        payload = json.loads(raw)
    except json.JSONDecodeError:
        print("[warn] EXTERNAL_FEEDS is not valid JSON. Fallback to default feed list.")
        return DEFAULT_FEEDS

    feeds: List[Dict[str, str]] = []
    for item in payload if isinstance(payload, list) else []:
        if not isinstance(item, dict):
            continue
        name = str(item.get("name", "")).strip()
        url = str(item.get("url", "")).strip()
        feed_type = str(item.get("type", "rss")).strip().lower()
        if not name or not url:
            continue
        feeds.append(
            {
                "name": name,
                "url": url,
                "type": feed_type if feed_type in {"rss", "atom"} else "rss",
            }
        )
    return feeds or DEFAULT_FEEDS


def parse_csv_names(raw: str, default: List[str]) -> List[str]:
    source = raw.strip()
    if not source:
        return list(default)
    names = [name.strip() for name in source.split(",") if name.strip()]
    return names or list(default)


def parse_date(raw: str) -> Optional[datetime]:
    raw = (raw or "").strip()
    if not raw:
        return None

    try:
        dt = parsedate_to_datetime(raw)
        if dt.tzinfo is None:
            dt = dt.replace(tzinfo=timezone.utc)
        return dt.astimezone(CN_TZ)
    except (TypeError, ValueError):
        pass

    iso_candidates = [
        raw,
        raw.replace("Z", "+00:00"),
    ]
    for candidate in iso_candidates:
        try:
            dt = datetime.fromisoformat(candidate)
            if dt.tzinfo is None:
                dt = dt.replace(tzinfo=timezone.utc)
            return dt.astimezone(CN_TZ)
        except ValueError:
            continue
    return None


def first_child_text(node: ET.Element, names: List[str]) -> str:
    for child in list(node):
        tag_name = child.tag.split("}")[-1].lower()
        if tag_name in names:
            return (child.text or "").strip()
    return ""


def find_atom_link(node: ET.Element) -> str:
    for child in list(node):
        tag_name = child.tag.split("}")[-1].lower()
        if tag_name != "link":
            continue
        href = (child.attrib.get("href") or "").strip()
        if href:
            return href
    return ""


@dataclass
class FeedItem:
    source: str
    title: str
    link: str
    summary: str
    published_at: Optional[datetime]

    @property
    def item_id(self) -> str:
        base = f"{self.link}|{self.title}".strip()
        return hashlib.sha1(base.encode("utf-8")).hexdigest()


class ExternalFeedCollector:
    def __init__(self, env: Dict[str, str], dry_run: bool = False):
        self.env = env
        self.dry_run = dry_run
        self.wp_url = env.get("WP_URL", "").rstrip("/")
        self.wp_user = env.get("WP_ADMIN_USER", "").strip()
        self.wp_auth_password = (
            env.get("WP_APP_PASSWORD")
            or env.get("WP_ADMIN_PASSWORD")
            or env.get("SERVER_PASSWORD")
            or ""
        ).strip()
        self.wp_api_key = (
            env.get("WP_API_KEY")
            or env.get("WP_APP_PASSWORD")
            or env.get("WP_ADMIN_PASSWORD")
            or env.get("SERVER_PASSWORD")
            or ""
        ).strip()
        self.wp_api_url = f"{self.wp_url}/wp-json/wp/v2" if self.wp_url else ""
        self.wp_helper_url = env.get("EXTERNAL_FEED_HELPER_URL", f"{self.wp_url}/wp_publish_helper.php").strip()
        self.use_helper_endpoint = env_bool(env, "EXTERNAL_FEED_USE_HELPER_ENDPOINT", True)
        self.status = env.get("EXTERNAL_FEED_POST_STATUS", "publish").strip().lower()
        if self.status not in {"publish", "draft", "pending", "private"}:
            self.status = "publish"

        self.category_slug = env.get("EXTERNAL_FEED_CATEGORY_SLUG", "external-updates").strip()
        self.category_name = env.get("EXTERNAL_FEED_CATEGORY_NAME", "外部数据更新").strip() or "外部数据更新"
        self.tag_names = parse_csv_names(env.get("EXTERNAL_FEED_TAGS", ""), ["外部数据", "自动更新"])
        self.max_items_per_feed = max(1, env_int(env, "EXTERNAL_FEED_MAX_ITEMS_PER_SOURCE", 4))
        self.max_items_total = max(1, env_int(env, "EXTERNAL_FEED_MAX_ITEMS_TOTAL", 18))
        self.lookback_days = max(1, env_int(env, "EXTERNAL_FEED_LOOKBACK_DAYS", 3))
        self.history_keep_days = max(3, env_int(env, "EXTERNAL_FEED_HISTORY_KEEP_DAYS", 30))
        self.allow_multi_post_per_day = env_bool(env, "EXTERNAL_FEED_ALLOW_MULTI_POST_PER_DAY", False)
        self.request_timeout = max(10, env_int(env, "EXTERNAL_FEED_REQUEST_TIMEOUT", 25))
        self.feeds = parse_external_feeds(env.get("EXTERNAL_FEEDS", ""))

        default_history = Path(__file__).resolve().parent / "runtime" / "external_feed_history.json"
        history_path = env.get("EXTERNAL_FEED_HISTORY_FILE", "").strip()
        if history_path:
            self.history_path = Path(history_path)
        else:
            self.history_path = default_history
        if not self.history_path.is_absolute():
            self.history_path = (Path(__file__).resolve().parent.parent / self.history_path).resolve()

        self.session = requests.Session()
        if self.wp_user and self.wp_auth_password:
            self.session.auth = (self.wp_user, self.wp_auth_password)
        self.session.headers.update(
            {
                # Use a browser-like UA to avoid third-party anti-crawler middleware false positives.
                "User-Agent": (
                    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                    "AppleWebKit/537.36 (KHTML, like Gecko) "
                    "Chrome/122.0.0.0 Safari/537.36"
                ),
                "Accept": "application/json,application/xml,text/xml,*/*;q=0.8",
            }
        )

        self.history = self.load_history()
        self.seen_items: Dict[str, str] = dict(self.history.get("seen_items", {}))
        self.last_post_date = str(self.history.get("last_post_date", "")).strip()

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
        self.history_path.parent.mkdir(parents=True, exist_ok=True)
        payload = {
            "last_post_date": self.last_post_date,
            "seen_items": self.seen_items,
            "updated_at": datetime.now(tz=CN_TZ).isoformat(),
        }
        with self.history_path.open("w", encoding="utf-8") as fh:
            json.dump(payload, fh, ensure_ascii=False, indent=2)

    def compact_history(self) -> None:
        cutoff = datetime.now(tz=CN_TZ) - timedelta(days=self.history_keep_days)
        compacted: Dict[str, str] = {}
        for item_id, seen_at in self.seen_items.items():
            dt = parse_date(str(seen_at))
            if dt is None or dt >= cutoff:
                compacted[item_id] = str(seen_at)
        self.seen_items = compacted

    def fetch_feed_text(self, url: str) -> Optional[str]:
        try:
            response = self.session.get(url, timeout=self.request_timeout)
            response.raise_for_status()
            return response.text
        except requests.RequestException as exc:
            print(f"[warn] failed to fetch feed: {url} ({exc})")
            return None

    def parse_feed(self, source_name: str, feed_text: str) -> List[FeedItem]:
        try:
            root = ET.fromstring(feed_text)
        except ET.ParseError as exc:
            print(f"[warn] invalid XML for feed {source_name}: {exc}")
            return []

        root_name = root.tag.split("}")[-1].lower()
        items: List[FeedItem] = []

        if root_name == "rss":
            channel = None
            for child in list(root):
                if child.tag.split("}")[-1].lower() == "channel":
                    channel = child
                    break
            if channel is None:
                return []

            for item in list(channel):
                if item.tag.split("}")[-1].lower() != "item":
                    continue
                title = first_child_text(item, ["title"])
                link = first_child_text(item, ["link"])
                summary = first_child_text(item, ["description", "summary"])
                date_text = first_child_text(item, ["pubdate", "updated", "published"])
                if not title or not link:
                    continue
                items.append(
                    FeedItem(
                        source=source_name,
                        title=normalize_text(title, max_len=120),
                        link=link,
                        summary=normalize_text(summary, max_len=180),
                        published_at=parse_date(date_text),
                    )
                )
            return items

        if root_name == "feed":
            for entry in list(root):
                if entry.tag.split("}")[-1].lower() != "entry":
                    continue
                title = first_child_text(entry, ["title"])
                link = find_atom_link(entry)
                summary = first_child_text(entry, ["summary", "content"])
                date_text = first_child_text(entry, ["updated", "published"])
                if not title or not link:
                    continue
                items.append(
                    FeedItem(
                        source=source_name,
                        title=normalize_text(title, max_len=120),
                        link=link,
                        summary=normalize_text(summary, max_len=180),
                        published_at=parse_date(date_text),
                    )
                )
            return items

        # Fallback: support unknown root by checking all item/entry tags.
        for node in root.iter():
            node_name = node.tag.split("}")[-1].lower()
            if node_name not in {"item", "entry"}:
                continue
            title = first_child_text(node, ["title"])
            link = first_child_text(node, ["link"])
            if node_name == "entry" and not link:
                link = find_atom_link(node)
            summary = first_child_text(node, ["description", "summary", "content"])
            date_text = first_child_text(node, ["pubdate", "updated", "published"])
            if not title or not link:
                continue
            items.append(
                FeedItem(
                    source=source_name,
                    title=normalize_text(title, max_len=120),
                    link=link,
                    summary=normalize_text(summary, max_len=180),
                    published_at=parse_date(date_text),
                )
            )
        return items

    def collect_items(self) -> List[FeedItem]:
        cutoff = datetime.now(tz=CN_TZ) - timedelta(days=self.lookback_days)
        collected: List[FeedItem] = []

        for feed in self.feeds:
            source_name = feed.get("name", "").strip()
            source_url = feed.get("url", "").strip()
            if not source_name or not source_url:
                continue

            print(f"[info] fetching: {source_name}")
            feed_text = self.fetch_feed_text(source_url)
            if not feed_text:
                continue

            parsed_items = self.parse_feed(source_name, feed_text)
            parsed_items.sort(
                key=lambda item: item.published_at or datetime(1970, 1, 1, tzinfo=timezone.utc),
                reverse=True,
            )

            per_feed_count = 0
            for item in parsed_items:
                if item.item_id in self.seen_items:
                    continue
                if item.published_at and item.published_at < cutoff:
                    continue
                collected.append(item)
                per_feed_count += 1
                if per_feed_count >= self.max_items_per_feed:
                    break
                if len(collected) >= self.max_items_total:
                    break
            print(f"[info] {source_name}: picked {per_feed_count} new items")

            if len(collected) >= self.max_items_total:
                break

        collected.sort(
            key=lambda item: item.published_at or datetime(1970, 1, 1, tzinfo=timezone.utc),
            reverse=True,
        )
        return collected[: self.max_items_total]

    def build_post(self, items: List[FeedItem]) -> Dict[str, str]:
        now = datetime.now(tz=CN_TZ)
        date_str = now.strftime("%Y-%m-%d")
        title_template = self.env.get("EXTERNAL_FEED_TITLE_TEMPLATE", "每日外部数据速递 | {date}")
        title = title_template.replace("{date}", date_str)

        grouped: Dict[str, List[FeedItem]] = defaultdict(list)
        for item in items:
            grouped[item.source].append(item)

        lines: List[str] = []
        lines.append(f"<p>自动抓取时间：{now.strftime('%Y-%m-%d %H:%M')}（UTC+8）。</p>")
        lines.append("<p>以下内容来自公开 RSS/Atom 数据源，仅作信息汇总与索引参考。</p>")
        for source, source_items in grouped.items():
            lines.append(f"<h2>{source}</h2>")
            lines.append("<ul>")
            for item in source_items:
                when = ""
                if item.published_at:
                    when = f" <em>({item.published_at.strftime('%m-%d %H:%M')})</em>"
                summary = f" - {item.summary}" if item.summary else ""
                lines.append(
                    f"<li><a href=\"{item.link}\" target=\"_blank\" rel=\"noopener nofollow external\">"
                    f"{item.title}</a>{when}{summary}</li>"
                )
            lines.append("</ul>")

        content = "\n".join(lines)
        excerpt = f"共汇总 {len(items)} 条外部更新，覆盖 {len(grouped)} 个来源。"
        return {
            "title": title,
            "content": content,
            "excerpt": excerpt,
            "date_key": date_str,
        }

    def ensure_category_id(self) -> Optional[int]:
        if not self.category_slug:
            return None
        if not self.wp_api_url:
            return None

        params = {"slug": self.category_slug, "per_page": 1}
        try:
            resp = self.session.get(f"{self.wp_api_url}/categories", params=params, timeout=self.request_timeout)
            resp.raise_for_status()
            payload = resp.json()
            if isinstance(payload, list) and payload:
                return int(payload[0].get("id", 0)) or None
        except requests.RequestException as exc:
            print(f"[warn] failed to read category by slug: {exc}")

        # Auto create when not found.
        try:
            resp = self.session.post(
                f"{self.wp_api_url}/categories",
                json={"name": self.category_name, "slug": self.category_slug},
                timeout=self.request_timeout,
            )
            if resp.status_code in (200, 201):
                payload = resp.json()
                return int(payload.get("id", 0)) or None
            print(f"[warn] failed to create category: {resp.status_code} {resp.text[:180]}")
        except requests.RequestException as exc:
            print(f"[warn] failed to create category: {exc}")
        return None

    def ensure_tag_ids(self) -> List[int]:
        if not self.tag_names or not self.wp_api_url:
            return []

        tag_ids: List[int] = []
        for name in self.tag_names:
            try:
                resp = self.session.get(
                    f"{self.wp_api_url}/tags",
                    params={"search": name, "per_page": 50},
                    timeout=self.request_timeout,
                )
                resp.raise_for_status()
                payload = resp.json()
                found_id = None
                if isinstance(payload, list):
                    for tag in payload:
                        if str(tag.get("name", "")).strip() == name:
                            found_id = int(tag.get("id", 0))
                            break
                if found_id:
                    tag_ids.append(found_id)
                    continue
            except requests.RequestException as exc:
                print(f"[warn] failed to query tag '{name}': {exc}")
                continue

            try:
                resp = self.session.post(
                    f"{self.wp_api_url}/tags",
                    json={"name": name},
                    timeout=self.request_timeout,
                )
                if resp.status_code in (200, 201):
                    payload = resp.json()
                    new_id = int(payload.get("id", 0))
                    if new_id:
                        tag_ids.append(new_id)
                else:
                    print(f"[warn] failed to create tag '{name}': {resp.status_code} {resp.text[:120]}")
            except requests.RequestException as exc:
                print(f"[warn] failed to create tag '{name}': {exc}")
        return tag_ids

    def create_post_via_helper(self, post_data: Dict[str, str]) -> Optional[Dict[str, object]]:
        payload: Dict[str, object] = {
            "api_key": self.wp_api_key,
            "title": post_data["title"],
            "content": post_data["content"],
            "excerpt": post_data["excerpt"],
            "status": self.status,
            "categories": [self.category_name] if self.category_name else [],
            "tags": self.tag_names,
        }

        if self.dry_run:
            print("[dry-run] helper payload preview:")
            print(f"  helper_url: {self.wp_helper_url}")
            print(f"  title: {payload['title']}")
            print(f"  status: {payload['status']}")
            print(f"  categories: {payload['categories']}")
            print(f"  tags: {payload['tags']}")
            print(f"  content chars: {len(post_data['content'])}")
            return {"id": 0, "link": "", "title": payload["title"]}

        try:
            resp = self.session.post(self.wp_helper_url, json=payload, timeout=self.request_timeout)
            resp.raise_for_status()
            result = resp.json()
            if not isinstance(result, dict) or not result.get("success"):
                print(f"[warn] helper endpoint rejected post: {str(result)[:200]}")
                return None
            return {
                "id": int(result.get("post_id", 0)),
                "link": str(result.get("url", "")),
                "title": post_data["title"],
            }
        except (requests.RequestException, ValueError) as exc:
            print(f"[warn] helper endpoint publish failed: {exc}")
            return None

    def create_post_via_wp_rest(self, post_data: Dict[str, str], category_id: Optional[int], tag_ids: List[int]) -> Optional[Dict[str, object]]:
        payload: Dict[str, object] = {
            "title": post_data["title"],
            "content": post_data["content"],
            "excerpt": post_data["excerpt"],
            "status": self.status,
        }
        if category_id:
            payload["categories"] = [category_id]
        if tag_ids:
            payload["tags"] = tag_ids

        if self.dry_run:
            print("[dry-run] post payload preview:")
            print(f"  title: {payload['title']}")
            print(f"  status: {payload['status']}")
            print(f"  category_id: {category_id}")
            print(f"  tag_ids: {tag_ids}")
            print(f"  content chars: {len(post_data['content'])}")
            return {"id": 0, "link": "", "title": payload["title"]}

        try:
            resp = self.session.post(
                f"{self.wp_api_url}/posts",
                json=payload,
                timeout=self.request_timeout,
            )
            if resp.status_code not in (200, 201):
                print(f"[error] create post failed: {resp.status_code} {resp.text[:200]}")
                return None
            result = resp.json()
            return result if isinstance(result, dict) else None
        except requests.RequestException as exc:
            print(f"[error] create post failed: {exc}")
            return None

    def create_post(self, post_data: Dict[str, str], category_id: Optional[int], tag_ids: List[int]) -> Optional[Dict[str, object]]:
        if self.use_helper_endpoint and self.wp_helper_url:
            created = self.create_post_via_helper(post_data)
            if created:
                return created

        return self.create_post_via_wp_rest(post_data, category_id, tag_ids)

    def validate_runtime(self) -> bool:
        missing = []
        if not self.wp_url:
            missing.append("WP_URL")
        if self.use_helper_endpoint:
            if not self.wp_api_key:
                missing.append("WP_API_KEY (or WP_APP_PASSWORD/WP_ADMIN_PASSWORD for helper endpoint)")
        else:
            if not self.wp_user:
                missing.append("WP_ADMIN_USER")
            if not self.wp_auth_password:
                missing.append("WP_APP_PASSWORD or WP_ADMIN_PASSWORD")

        if missing:
            print("[error] missing required config:")
            for item in missing:
                print(f"  - {item}")
            return False
        return True

    def run(self) -> int:
        if not self.validate_runtime():
            return 1
        if not self.feeds:
            print("[error] no feeds configured")
            return 1

        today = datetime.now(tz=CN_TZ).strftime("%Y-%m-%d")
        if (not self.dry_run) and (not self.allow_multi_post_per_day) and self.last_post_date == today:
            print("[info] today's external feed digest already published, skip.")
            return 0

        self.compact_history()
        items = self.collect_items()
        if not items:
            print("[info] no new feed items, skip publishing.")
            self.save_history()
            return 0

        post_data = self.build_post(items)
        category_id = None
        tag_ids: List[int] = []
        if not self.use_helper_endpoint:
            category_id = self.ensure_category_id()
            tag_ids = self.ensure_tag_ids()
        created = self.create_post(post_data, category_id, tag_ids)
        if not created:
            return 1

        if self.dry_run:
            print("[ok] dry-run completed.")
            return 0

        now_iso = datetime.now(tz=CN_TZ).isoformat()
        for item in items:
            self.seen_items[item.item_id] = now_iso
        self.last_post_date = today
        self.save_history()

        created_id = int(created.get("id", 0)) if isinstance(created, dict) else 0
        created_link = str(created.get("link", "")) if isinstance(created, dict) else ""
        print(f"[ok] external digest posted: id={created_id} link={created_link}")
        return 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Collect external feeds and publish daily digest.")
    parser.add_argument("--dry-run", action="store_true", help="Build payload only, no publish.")
    args = parser.parse_args()

    env = load_env()
    runner = ExternalFeedCollector(env=env, dry_run=args.dry_run)
    return runner.run()


if __name__ == "__main__":
    sys.exit(main())
