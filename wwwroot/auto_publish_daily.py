#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
自动发布脚本
每天从草稿箱发布2-3篇文章到博客，确保日更
"""
import sys
import io
if sys.platform == 'win32':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

import requests
import json
import re
import time
import os
from datetime import datetime, timedelta

def load_env():
    env = {}
    for p in ('../.env', '.env'):
        if not os.path.exists(p):
            continue
        with open(p, 'r', encoding='utf-8') as f:
            for line in f:
                line = line.strip()
                if not line or line.startswith('#') or '=' not in line:
                    continue
                k, v = line.split('=', 1)
                env[k.strip()] = v.strip().strip('"').strip("'")
        break
    return env

ENV = load_env()

# WordPress 配置（优先读取 .env）
WP_URL = ENV.get("WP_URL", "https://example.com")
WP_API_URL = f"{WP_URL}/wp-json/wp/v2"
WP_USERNAME = ENV.get("WP_ADMIN_USER", "admin")
WP_PASSWORD = (
    ENV.get("WP_APP_PASSWORD")
    or ENV.get("WP_API_KEY")
    or ENV.get("WP_ADMIN_PASSWORD")
    or ENV.get("SERVER_PASSWORD")
    or ""
)

class AutoPublisher:
    def __init__(self):
        self.auth = (WP_USERNAME, WP_PASSWORD)
        self.session = requests.Session()
        self.session.auth = self.auth
        self.published_titles = set()
        self.load_published_titles()

    def load_published_titles(self):
        """加载已发布文章的标题（用于去重）"""
        try:
            print("正在加载已发布文章列表...")
            # 使用自定义PHP接口获取已发布文章
            url = f"{WP_URL}/get_published_posts.php"
            params = {'api_key': WP_PASSWORD}

            response = self.session.get(url, params=params, timeout=30)
            response.raise_for_status()

            result = response.json()
            if result.get('success'):
                posts = result.get('data', [])
                self.published_titles = {post['title'] for post in posts}
                print(f"✓ 已加载 {len(self.published_titles)} 篇已发布文章")
            else:
                print(f"✗ 获取已发布文章失败: {result.get('error')}")
        except Exception as e:
            print(f"✗ 加载已发布文章失败: {e}")

    def get_draft_posts(self, limit=50):
        """获取草稿箱中的文章"""
        try:
            print("正在获取草稿箱文章...")
            # 使用自定义PHP接口
            url = f"{WP_URL}/get_drafts.php"
            params = {'api_key': WP_PASSWORD}

            response = self.session.get(url, params=params, timeout=30)
            response.raise_for_status()

            result = response.json()
            if result.get('success'):
                drafts = result.get('data', [])
                print(f"✓ 草稿箱共有 {len(drafts)} 篇文章")
                return drafts
            else:
                print(f"✗ 获取草稿失败: {result.get('error', 'Unknown error')}")
                return []

        except Exception as e:
            print(f"✗ 获取草稿失败: {e}")
            return []

    def select_articles_to_publish(self, drafts, count=3):
        """选择要发布的文章（按抓取/创建时间顺序）"""
        if not drafts:
            return []

        # 只选择有特色图片的草稿
        drafts_with_thumbnail = [d for d in drafts if d.get('has_thumbnail', False)]

        if not drafts_with_thumbnail:
            print("  ⚠️  警告: 没有找到有特色图片的草稿！")
            return []

        print(f"  ✓ 找到 {len(drafts_with_thumbnail)} 篇有特色图片的草稿（总共 {len(drafts)} 篇）")

        # 过滤掉已发布的文章
        unpublished_drafts = [d for d in drafts_with_thumbnail if d['title'] not in self.published_titles]

        if not unpublished_drafts:
            print("  ⚠️  警告: 所有有特色图片的草稿都已发布！")
            return []

        print(f"  ✓ 过滤后剩余 {len(unpublished_drafts)} 篇未发布的草稿")

        # 按创建时间升序（最早抓取的先发布）
        def _parse_date(d):
            try:
                return datetime.strptime(d.get('date', ''), "%Y-%m-%d %H:%M:%S")
            except Exception:
                return datetime.min

        unpublished_drafts.sort(key=_parse_date)
        return unpublished_drafts[:count]

    def publish_article(self, post_id, title):
        """发布单篇文章"""
        try:
            # 使用wp_publish_helper.php发布
            url = f"{WP_URL}/publish_draft.php"
            data = {
                'post_id': post_id,
                'api_key': WP_PASSWORD
            }

            response = self.session.post(url, data=data, timeout=30)
            response.raise_for_status()

            # 解析响应
            text = response.text
            json_match = re.search(r'\{.*\}', text, re.DOTALL)
            if json_match:
                result = json.loads(json_match.group())
            else:
                result = response.json()

            if result.get('success'):
                print(f"  ✓ 发布成功: {title}")
                print(f"    链接: {result.get('link', 'N/A')}")
                return True, result
            else:
                print(f"  ✗ 发布失败: {title}")
                print(f"    错误: {result.get('error', '未知错误')}")
                return False, None

        except Exception as e:
            print(f"  ✗ 发布失败: {title}")
            print(f"    错误: {e}")
            return False, None

    def load_publish_history(self):
        """加载发布历史"""
        try:
            with open('publish_history.json', 'r', encoding='utf-8') as f:
                history = json.load(f)
            return history
        except:
            return []

    def save_publish_history(self, history):
        """保存发布历史"""
        with open('publish_history.json', 'w', encoding='utf-8') as f:
            json.dump(history, f, ensure_ascii=False, indent=2)

    def get_today_publish_count(self, history):
        """获取今日已发布数量"""
        today = datetime.now().strftime('%Y-%m-%d')
        today_count = sum(1 for entry in history if entry['date'].startswith(today))
        return today_count

    def run(self, max_daily=3):
        """执行自动发布"""
        print("="*120)
        print("自动发布脚本")
        print("="*120)
        print(f"运行时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

        # 1. 加载发布历史
        history = self.load_publish_history()
        today_count = self.get_today_publish_count(history)

        print(f"\n今日已发布: {today_count} 篇")
        print(f"每日目标: {max_daily} 篇")

        if today_count >= max_daily:
            print(f"\n✓ 今日发布任务已完成！")
            return

        remaining = max_daily - today_count
        print(f"待发布: {remaining} 篇")

        # 2. 获取草稿箱
        drafts = self.get_draft_posts()
        if not drafts:
            print("\n✓ 草稿箱为空，无需发布")
            return

        # 3. 选择要发布的文章
        to_publish = self.select_articles_to_publish(drafts, count=remaining)

        print(f"\n选择了 {len(to_publish)} 篇文章准备发布:")
        print("-"*120)
        for i, draft in enumerate(to_publish, 1):
            title = draft['title']
            date_str = draft['date']
            print(f"{i}. {title}")
            print(f"   创建时间: {date_str}")

        # 4. 发布文章
        print("\n开始发布...")
        print("-"*120)

        success_count = 0
        for draft in to_publish:
            post_id = draft['id']
            title = draft['title']

            success, post = self.publish_article(post_id, title)
            if success:
                success_count += 1
                # 记录到历史
                history.append({
                    'date': datetime.now().isoformat(),
                    'post_id': post_id,
                    'title': title,
                    'link': post.get('link', ''),
                    'status': 'success'
                })

                # 保存历史
                self.save_publish_history(history)

                # 立即发布下一篇，避免脚本中断
                if success_count < len(to_publish):
                    time.sleep(1)

        # 5. 总结
        print("\n" + "="*120)
        print(f"发布完成！成功: {success_count}/{len(to_publish)}")
        print(f"今日累计发布: {today_count + success_count} 篇")
        print("="*120)

        # 统计信息
        total_published = len([h for h in history if h['status'] == 'success'])
        print(f"\n历史发布总数: {total_published} 篇")

        # 最近7天发布统计
        seven_days_ago = (datetime.now() - timedelta(days=7)).strftime('%Y-%m-%d')
        recent_count = sum(1 for h in history if h['date'] >= seven_days_ago)
        print(f"最近7天发布: {recent_count} 篇")

if __name__ == "__main__":
    from bs4 import BeautifulSoup

    if not WP_PASSWORD:
        print("✗ 未读取到发布凭据，请在 .env 配置 WP_APP_PASSWORD/WP_API_KEY/WP_ADMIN_PASSWORD")
        sys.exit(1)

    publisher = AutoPublisher()

    # 固定每日发布 3 篇
    publisher.run(max_daily=3)
