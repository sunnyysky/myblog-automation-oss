#!/usr/bin/env python3
# -*- coding: utf-8 -*-
import sys
import io
if sys.platform == 'win32':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

import paramiko
import re
import json
import os

def normalize_title(s):
    s = re.sub(r'[\x00-\x1f\u200b]', '', s or '')
    s = re.sub(r'\s+', '', s).strip().lower()
    return s

def load_env():
    env = {}
    if not os.path.exists('.env'):
        return env
    with open('.env', 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            k, v = line.split('=', 1)
            env[k.strip()] = v.strip().strip('"').strip("'")
    return env

ENV = load_env()

SERVER_HOST = ENV.get('SERVER_HOST', '')
SERVER_USER = ENV.get('SERVER_USER', 'root')
SERVER_PASSWORD = ENV.get('SERVER_PASSWORD', '')
WP_PATH = ENV.get('WP_PATH', '/www/wwwroot/example.com')

print("="*120)
print("完整对比：原始网站文章列表 vs 数据库")
print("="*120)

# 加载所有源文章
try:
    with open('all_source_articles_complete.json', 'r', encoding='utf-8') as f:
        source_articles = json.load(f)
    print(f"\n原始网站文章总数: {len(source_articles)} 篇")
except:
    print("\n未找到 all_source_articles_complete.json")
    exit(1)

# 连接数据库
print("\n正在连接数据库...")
ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())

try:
    if not SERVER_PASSWORD:
        raise RuntimeError("未读取到 SERVER_PASSWORD，请先在 .env 配置")
    if not SERVER_HOST:
        raise RuntimeError("未读取到 SERVER_HOST，请先在 .env 配置")

    ssh.connect(
        hostname=SERVER_HOST,
        username=SERVER_USER,
        password=SERVER_PASSWORD,
        timeout=30
    )

    stdin, stdout, stderr = ssh.exec_command(f'cat {WP_PATH}/wp-config.php')
    config_content = stdout.read().decode('utf-8')

    db_name = db_user = db_pass = table_prefix = ''
    for line in config_content.split('\n'):
        line = line.strip()
        if line.startswith('define'):
            match = re.search(r"define\(['\"]DB_NAME['\"],\s*['\"]([^'\"]+)['\"]\)", line)
            if match:
                db_name = match.group(1)
            match = re.search(r"define\(['\"]DB_USER['\"],\s*['\"]([^'\"]+)['\"]\)", line)
            if match:
                db_user = match.group(1)
            match = re.search(r"define\(['\"]DB_PASSWORD['\"],\s*['\"]([^'\"]+)['\"]\)", line)
            if match:
                db_pass = match.group(1)
        elif 'table_prefix' in line:
            match = re.search(r"\$table_prefix\s*=\s*['\"]([^'\"]+)['\"]", line)
            if match:
                table_prefix = match.group(1)

    posts_table = f'{table_prefix}posts'

    # 获取所有文章
    sql = f"mysql -u{db_user} -p'{db_pass}' {db_name} -e \"SELECT post_title FROM {posts_table} WHERE post_type = 'post';\""
    stdin, stdout, stderr = ssh.exec_command(sql)
    result = stdout.read().decode('utf-8', errors='ignore')

    db_titles = set()
    lines = result.strip().split('\n')
    for line in lines[1:]:
        if line.strip():
            title = line.strip()
            db_titles.add(title)

    print(f"数据库文章总数: {len(db_titles)} 篇")

    ssh.close()

except Exception as e:
    print(f'数据库连接失败: {e}')
    import traceback
    traceback.print_exc()
    exit(1)

# 对比分析
print("\n正在对比分析...")

collected = []
uncollected = []
db_title_norm_set = {normalize_title(t) for t in db_titles}

for source_article in source_articles:
    source_title_norm = normalize_title(source_article.get('title', ''))
    if source_title_norm and source_title_norm in db_title_norm_set:
        collected.append(source_article)
    else:
        uncollected.append(source_article)

# 输出结果
print("\n" + "="*120)
print("对比结果")
print("="*120)

print(f"\n原始网站: {len(source_articles)} 篇")
print(f"已采集:   {len(collected)} 篇")
print(f"未采集:   {len(uncollected)} 篇")
print(f"采集率:   {len(collected)*100//len(source_articles) if source_articles else 0}%")

if uncollected:
    print(f"\n⚠️  发现 {len(uncollected)} 篇未采集的文章！")
    print("="*120)

    # 按分类统计未采集文章
    from collections import defaultdict
    uncollected_by_category = defaultdict(list)
    for article in uncollected:
        cat = article.get('category', '未知')
        uncollected_by_category[cat].append(article)

    print("\n未采集文章分类统计:")
    print("-"*120)
    for cat_name in sorted(uncollected_by_category.keys()):
        articles = uncollected_by_category[cat_name]
        print(f"{cat_name:20s}: {len(articles):3d} 篇")

    # 保存未采集文章列表
    with open('uncollected_articles_complete.json', 'w', encoding='utf-8') as f:
        json.dump(uncollected, f, ensure_ascii=False, indent=2)

    print(f"\n未采集文章列表已保存到: uncollected_articles_complete.json")

    # 显示前20篇未采集的文章
    print(f"\n未采集文章列表（前20篇）:")
    print("-"*120)
    for i, article in enumerate(uncollected[:20], 1):
        cat = article.get('category', '未知')
        print(f"{i:3d}. [{cat:12s}] {article['title']}")

    if len(uncollected) > 20:
        print(f"\n... 还有 {len(uncollected) - 20} 篇")

    print(f"\n需要立即采集这 {len(uncollected)} 篇文章！")

else:
    print("\n✅ 完美！所有文章都已采集完成！")
    print("="*120)

    # 按分类统计已采集文章
    from collections import defaultdict
    collected_by_category = defaultdict(list)
    for article in collected:
        cat = article.get('category', '未知')
        collected_by_category[cat].append(article)

    print("\n已采集文章分类统计:")
    print("-"*120)
    for cat_name in sorted(collected_by_category.keys()):
        articles = collected_by_category[cat_name]
        print(f"{cat_name:20s}: {len(articles):3d} 篇")

print("\n" + "="*120)
