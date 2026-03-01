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

# 导入采集器的核心功能
from collect_all_123 import AllCollector

def main():
    parser = argparse.ArgumentParser(description='采集新发现的文章')
    parser.add_argument('--file', required=True, help='新文章JSON文件路径')
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

    print("\n" + "="*120)
    print(f"开始采集 {len(new_articles)} 篇新文章")
    print("="*120)

    success_count = 0
    failed_count = 0

    for i, article_info in enumerate(new_articles, 1):
        print(f"\n[{i}/{len(new_articles)}]", end=' ')

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
