#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
采集新发现的文章
配合 auto_scan_updates.py 使用
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
import argparse
from datetime import datetime
from urllib.parse import urljoin
from collections import Counter
import paramiko

_ZERO_WIDTH_RE = re.compile(r'[\u200b\u200c\u200d\ufeff]')

def clean_title(value):
    if not value:
        return value
    value = _ZERO_WIDTH_RE.sub('', value)
    return value.strip()

# 导入采集器的核心功能
from collect_all_123 import AllCollector

def main():
    parser = argparse.ArgumentParser(description='采集新发现的文章')
    parser.add_argument('--file', required=True, help='新文章JSON文件路径')
    parser.add_argument('--limit', type=int, default=0, help='采集数量上限(0表示不限制)')
    args = parser.parse_args()

    # 加载新文章列表
    try:
        with open(args.file, 'r', encoding='utf-8') as f:
            new_articles = json.load(f)
        print(f"✓ 加载了 {len(new_articles)} 篇新文章")
    except Exception as e:
        print(f"✗ 加载文件失败: {e}")
        return

    if not new_articles:
        print("没有需要采集的文章")
        return

    # 使用现有的采集器
    collector = AllCollector()

    # 去重：标题归一化 + 过滤已存在文章
    unique_articles = []
    seen_titles = set()
    for article_info in new_articles:
        if 'title' in article_info:
            article_info['title'] = clean_title(article_info.get('title'))
        title = article_info.get('title', '')
        if not title:
            continue
        if title in seen_titles:
            continue
        seen_titles.add(title)
        if title in collector.published_titles:
            continue
        unique_articles.append(article_info)

    if not unique_articles:
        print("没有需要采集的文章（可能已全部入库或重复）")
        return

    if args.limit and args.limit > 0:
        unique_articles = unique_articles[:args.limit]

    print("\n" + "="*120)
    print(f"开始采集 {len(unique_articles)} 篇新文章")
    print("="*120)

    success_count = 0
    failed_count = 0

    for i, article_info in enumerate(unique_articles, 1):
        print(f"\n[{i}/{len(unique_articles)}]", end=' ')

        article = collector.get_article_detail(article_info)

        if article:
            success, post_id = collector.publish_to_wordpress(article)
            if success:
                success_count += 1
                collector.published_titles.add(article['title'])
            else:
                failed_count += 1
        else:
            failed_count += 1

    print("\n" + "="*120)
    print(f"采集完成！成功: {success_count}, 失败: {failed_count}")
    print(f"结束时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("="*120)

if __name__ == "__main__":
    main()
