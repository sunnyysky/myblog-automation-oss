#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
检查草稿箱文章的特色图片设置
"""
import sys
import io
if sys.platform == 'win32':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

import paramiko
import re
import os

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
SERVER_HOST = ENV.get('SERVER_HOST', '')
SERVER_USER = ENV.get('SERVER_USER', 'root')
SERVER_PASSWORD = ENV.get('SERVER_PASSWORD', '')
DB_NAME = ENV.get('DB_NAME', 'your_db_name')
DB_USER = ENV.get('DB_USER', 'your_db_user')
DB_PASSWORD = ENV.get('DB_PASSWORD', '')
DB_TABLE_PREFIX = ENV.get('DB_TABLE_PREFIX', 'blog_')

print("="*120)
print("检查草稿箱文章的特色图片设置")
print("="*120)

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
if not SERVER_HOST or not SERVER_PASSWORD or not DB_PASSWORD:
    print("缺少凭据：请在 .env 配置 SERVER_HOST、SERVER_PASSWORD 与 DB_PASSWORD")
    sys.exit(1)
ssh.connect(
    hostname=SERVER_HOST,
    username=SERVER_USER,
    password=SERVER_PASSWORD,
    timeout=30
)

# 查询草稿箱文章和特色图片
sql = f"""mysql -u {DB_USER} -p'{DB_PASSWORD}' {DB_NAME} -e "
SELECT p.ID, p.post_title, pm.meta_value as thumbnail_id
FROM {DB_TABLE_PREFIX}posts p
LEFT JOIN {DB_TABLE_PREFIX}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
WHERE p.post_type = 'post' AND p.post_status = 'draft'
ORDER BY p.post_date DESC
LIMIT 20;
" 2>/dev/null"""

stdin, stdout, stderr = ssh.exec_command(sql)
result = stdout.read().decode('utf-8', errors='ignore')

lines = result.strip().split('\n')
print(f"\n草稿箱文章特色图片检查（前20篇）:")
print("-"*120)
print(f"{'ID':<8} {'标题':<50} {'特色图片ID':<15}")
print("-"*120)

no_thumbnail = []
with_thumbnail = []

for line in lines[1:]:  # 跳过标题行
    if not line.strip():
        continue
    parts = line.split('\t')
    if len(parts) >= 3:
        post_id = parts[0].strip()
        title = parts[1].strip()[:48]
        thumbnail_id = parts[2].strip()

        if thumbnail_id == 'None' or not thumbnail_id:
            status = "❌ 无"
            no_thumbnail.append((post_id, title))
        else:
            status = f"✓ {thumbnail_id}"
            with_thumbnail.append((post_id, title, thumbnail_id))

        print(f"{post_id:<8} {title:<50} {status:<15}")

print("-"*120)
print(f"\n统计结果:")
print(f"  总草稿数: {len(no_thumbnail) + len(with_thumbnail)} 篇")
print(f"  有特色图片: {len(with_thumbnail)} 篇")
print(f"  无特色图片: {len(no_thumbnail)} 篇")

# 检查最近发布的文章
print("\n" + "="*120)
print("\n最近发布的文章（检查是否有问题）:")
print("-"*120)

sql_published = f"""mysql -u {DB_USER} -p'{DB_PASSWORD}' {DB_NAME} -e "
SELECT p.ID, p.post_title, pm.meta_value as thumbnail_id, p.post_date
FROM {DB_TABLE_PREFIX}posts p
LEFT JOIN {DB_TABLE_PREFIX}postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_thumbnail_id'
WHERE p.post_type = 'post' AND p.post_status = 'publish'
ORDER BY p.post_date DESC
LIMIT 10;
" 2>/dev/null"""

stdin, stdout, stderr = ssh.exec_command(sql_published)
result = stdout.read().decode('utf-8', errors='ignore')

lines = result.strip().split('\n')
print(f"{'ID':<8} {'标题':<50} {'特色图片ID':<15} {'发布时间'}")
print("-"*120)

for line in lines[1:]:
    if not line.strip():
        continue
    parts = line.split('\t')
    if len(parts) >= 4:
        post_id = parts[0].strip()
        title = parts[1].strip()[:48]
        thumbnail_id = parts[2].strip()
        post_date = parts[3].strip()[:16]

        if thumbnail_id == 'None' or not thumbnail_id:
            status = "❌ 无"
        else:
            status = f"✓ {thumbnail_id}"

        print(f"{post_id:<8} {title:<50} {status:<15} {post_date}")

ssh.close()

print("\n" + "="*120)
