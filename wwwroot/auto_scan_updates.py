#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
自动扫描AIBase网站更新
定期检查是否有新文章，如有则自动采集到草稿箱
"""
import sys
import io
if sys.platform == 'win32':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

import requests
from bs4 import BeautifulSoup
import json
import re
import time
import os
from datetime import datetime
from urllib.parse import urljoin
from collections import defaultdict

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

class UpdateScanner:
    def __init__(self):
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        })
        self.session.cookies.set('preferred_locale', 'zh')
        self.base_url = "https://course.aibase.com"

    def load_existing_articles(self):
        """加载现有文章列表"""
        try:
            with open('all_source_articles_complete.json', 'r', encoding='utf-8') as f:
                articles = json.load(f)
            print(f"✓ 已加载现有文章: {len(articles)} 篇")
            return articles
        except Exception as e:
            print(f"✗ 加载现有文章失败: {e}")
            return []

    def get_all_categories(self):
        """从首页获取所有分类"""
        try:
            print("\n正在获取分类列表...")
            response = self.session.get(f"{self.base_url}/zh", timeout=30)
            response.raise_for_status()
            soup = BeautifulSoup(response.text, 'html.parser')

            categories = []
            for link in soup.find_all('a', href=re.compile(r'/zh/class/\d+')):
                match = re.search(r'/zh/class/(\d+)', link.get('href'))
                if match:
                    cat_id = match.group(1)
                    cat_name = link.get_text().strip()
                    categories.append({
                        'id': cat_id,
                        'name': cat_name,
                        'url': urljoin(self.base_url, link.get('href'))
                    })

            print(f"✓ 发现 {len(categories)} 个分类")
            return categories

        except Exception as e:
            print(f"✗ 获取分类失败: {e}")
            return []

    def scan_category_articles(self, category):
        """扫描单个分类的所有文章"""
        cat_id = category['id']
        cat_name = category['name']
        url = category['url']

        try:
            print(f"\n扫描分类: {cat_name}")
            response = self.session.get(url, timeout=30)
            response.raise_for_status()
            soup = BeautifulSoup(response.text, 'html.parser')

            articles = []
            article_links = soup.find_all('a', href=re.compile(r'/zh/\d+'))

            seen_urls = set()
            for link in article_links:
                href = link.get('href')
                if not href:
                    continue

                article_url = urljoin(self.base_url, href)
                if article_url in seen_urls:
                    continue
                seen_urls.add(article_url)

                # 提取标题
                title_elem = link.find(['h3', 'h4', 'h5'], class_=re.compile(r'text', re.I))
                if not title_elem:
                    title_elem = link.find('span', class_=re.compile(r'title|name', re.I))
                if not title_elem:
                    title_elem = link

                title = title_elem.get_text().strip()
                if not title or len(title) < 5:
                    continue

                articles.append({
                    'title': title,
                    'url': article_url,
                    'category': cat_name,
                    'category_id': cat_id
                })

            print(f"  发现 {len(articles)} 篇文章")
            return articles

        except Exception as e:
            print(f"  ✗ 扫描失败: {e}")
            return []

    def find_new_articles(self, existing_articles, current_articles):
        """对比发现新文章"""
        existing_urls = {article['url'] for article in existing_articles}
        existing_titles = {article['title'] for article in existing_articles}

        new_articles = []
        for article in current_articles:
            url = article['url']
            title = article['title']

            # 通过URL和标题双重判断
            if url not in existing_urls and title not in existing_titles:
                new_articles.append(article)

        return new_articles

    def save_scan_result(self, all_articles, new_articles):
        """保存扫描结果"""
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')

        # 保存完整文章列表（更新）
        with open(f'all_source_articles_complete.json', 'w', encoding='utf-8') as f:
            json.dump(all_articles, f, ensure_ascii=False, indent=2)

        # 保存新发现的文章
        if new_articles:
            with open(f'new_articles_{timestamp}.json', 'w', encoding='utf-8') as f:
                json.dump(new_articles, f, ensure_ascii=False, indent=2)
            print(f"\n✓ 新文章列表已保存: new_articles_{timestamp}.json")

        # 保存扫描日志
        log_entry = {
            'timestamp': datetime.now().isoformat(),
            'total_articles': len(all_articles),
            'new_articles': len(new_articles),
            'new_articles_list': [a['title'] for a in new_articles]
        }

        try:
            with open('scan_log.json', 'r', encoding='utf-8') as f:
                logs = json.load(f)
        except:
            logs = []

        logs.append(log_entry)
        with open('scan_log.json', 'w', encoding='utf-8') as f:
            json.dump(logs, f, ensure_ascii=False, indent=2)

    def run(self):
        """执行扫描"""
        print("="*120)
        print("AIBase网站更新扫描器")
        print("="*120)
        print(f"扫描时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

        # 1. 加载现有文章
        existing_articles = self.load_existing_articles()
        if not existing_articles:
            print("\n警告: 未找到现有文章列表，将作为首次扫描")
            existing_articles = []

        # 2. 扫描所有分类
        categories = self.get_all_categories()
        if not categories:
            print("\n✗ 无法获取分类列表，扫描终止")
            return

        all_current_articles = []
        for category in categories:
            articles = self.scan_category_articles(category)
            all_current_articles.extend(articles)
            time.sleep(2)  # 避免请求过快

        print(f"\n总计发现 {len(all_current_articles)} 篇文章")

        # 3. 对比发现新文章
        new_articles = self.find_new_articles(existing_articles, all_current_articles)

        print("\n" + "="*120)
        print("扫描结果")
        print("="*120)
        print(f"现有文章: {len(existing_articles)} 篇")
        print(f"当前网站: {len(all_current_articles)} 篇")
        print(f"新增文章: {len(new_articles)} 篇")

        if new_articles:
            print(f"\n✓ 发现 {len(new_articles)} 篇新文章！")
            print("\n新文章列表:")
            print("-"*120)
            for i, article in enumerate(new_articles, 1):
                cat = article.get('category', '未知')
                print(f"{i:3d}. [{cat:12s}] {article['title']}")

            # 保存结果
            self.save_scan_result(all_current_articles, new_articles)

            print(f"\n提示: 使用以下命令采集新文章:")
            print(f"  python collect_new_articles.py --file new_articles_{datetime.now().strftime('%Y%m%d_%H%M%S')}.json")
        else:
            print("\n✓ 没有发现新文章")
            # 也要保存最新的完整列表
            with open('all_source_articles_complete.json', 'w', encoding='utf-8') as f:
                json.dump(all_current_articles, f, ensure_ascii=False, indent=2)

        print("\n" + "="*120)

if __name__ == "__main__":
    scanner = UpdateScanner()
    scanner.run()
