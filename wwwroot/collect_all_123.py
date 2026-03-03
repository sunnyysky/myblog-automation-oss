#!/usr/bin/env python3
# -*- coding: utf-8 -*-
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
from collections import Counter
import paramiko

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
WP_REST_BASE = ENV.get("WP_REST_BASE", f"{WP_URL}/wp-json/myblog/v1")
WP_USE_REST_API = ENV.get("WP_USE_REST_API", "0").lower() in ("1", "true", "yes", "on")
WP_PASSWORD = (
    ENV.get("WP_API_KEY")
    or ENV.get("WP_APP_PASSWORD")
    or ENV.get("WP_ADMIN_PASSWORD")
    or ENV.get("SERVER_PASSWORD")
    or ""
)

# SEO关键词库（扩展版）
SEO_KEYWORDS = {
    'chatbot': ['AI助手', '聊天机器人', 'AI对话', '智能客服', 'ChatGPT', 'Claude', '文心一言', '通义千问'],
    '豆包': ['豆包', '字节跳动', 'AI陪伴', 'AI聊天'],
    'Kimi': ['Kimi', '月之暗面', 'AI助手', '长文本'],
    'DeepSeek': ['DeepSeek', 'AI编程', '代码助手', '技术问答'],
    '文心一言': ['文心一言', '百度', 'ERNIE', 'AI写作'],
    '可灵': ['可灵', '视频生成'],
    '即梦': ['即梦', '视频生成', '动画'],
    '得理法搜': ['得理法搜', '法律', '法律AI'],
    'excel': ['Excel', 'AI表格', '数据处理', '数据分析', '办公自动化'],
    'ppt': ['PPT', '演示文稿', 'AI制作幻灯片', 'PowerPoint'],
    'word': ['Word', '文档处理', 'AI写作', '文档编辑'],
    'pdf': ['PDF', '文档转换', 'PDF处理'],
    '法律': ['法律', '法律文书', '合同', '申请书', '法律咨询', '维权', '起诉状', '法条', '条款', '欺诈', '消费者'],
    '写作': ['写作', '文章生成', '内容创作', '文案', '博客'],
    '学习': ['学习', '教育', '教学', '课程', '培训', '备考'],
    '工作': ['工作', '办公', '效率', '职场', '自动化'],
    '设计': ['设计', 'UI设计', '平面设计', '创意', '素材', '海报', '配色', '剪纸', '手工', '相册', '排版'],
    '营销': ['营销', '推广', '广告', '品牌', '客户', 'SEO', '话术', '产品策划', '节假日', '用户画像', '销售', '朋友圈', '商务谈判', 'SWOT', '竞品', '合作邮件'],
    '生活': ['生活', '购物', '避坑', '晨读', '手账', '防蚊', '晒被子', '浴室', '清洁'],
    '视频': ['视频', '动画', '宣传视频', '数字人', '短片'],
    '音频': ['音频', '播客', '有声书', '文本转语音', '音乐', 'Suno'],
    '编程': ['编程', 'Python', '函数', '模块', '数据分析', '项目'],
    '自媒体': ['自媒体', '小红书', '爆款', '标题优化'],
}

SERVER_HOST = ENV.get('SERVER_HOST', '')
SERVER_USER = ENV.get('SERVER_USER', 'root')
SERVER_PASSWORD = ENV.get('SERVER_PASSWORD', '')
WP_PATH = ENV.get('WP_PATH', '/www/wwwroot/example.com')

class AllCollector:
    def __init__(self):
        self.session = requests.Session()
        self.session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        })
        self.session.cookies.set('preferred_locale', 'zh')
        self.base_url = "https://course.aibase.com"
        self.published_titles = set()
        self.load_published()

    def normalize_url(self, url):
        if not url:
            return ""
        return url.split("?")[0].replace("http://", "https://")

    def extract_cover_image(self, html):
        """Try to extract the original cover image from page HTML."""
        # Cover image often appears in payload right before date string
        m = re.search(
            r'(https://img-1255512983\.cos\.ap-guangzhou\.myqcloud\.com/aibase/info/[^"\\]+?\.(?:jpg|jpeg|png|webp))","20\\d{2}-\\d{2}-\\d{2}',
            html
        )
        if m:
            return m.group(1)

        try:
            soup = BeautifulSoup(html, 'html.parser')
            meta = soup.find('meta', property='og:image') or soup.find('meta', attrs={'name': 'twitter:image'})
            if meta and meta.get('content'):
                return meta['content'].strip()
        except Exception:
            pass

        # Try JSON data embedded in page
        patterns = [
            r'"thumb"\s*:\s*"([^"]+)"',
            r'"cover"\s*:\s*"([^"]+)"'
        ]
        for pattern in patterns:
            match = re.search(pattern, html)
            if match:
                url = match.group(1)
                url = url.replace('\\u002F', '/').replace('\\/', '/')
                if url.startswith('http'):
                    return url

        # Fallback: first image from aibase info CDN
        match = re.search(r'(https?://[^"\']*aibase/info/[^"\']+\.(?:jpg|jpeg|png|webp))', html, re.I)
        if match:
            return match.group(1)

        return ""

    def load_published(self):
        try:
            ssh = paramiko.SSHClient()
            ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
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

            sql = f"mysql -u{db_user} -p'{db_pass}' {db_name} -e \"SELECT post_title FROM {posts_table} WHERE post_type = 'post';\""
            stdin, stdout, stderr = ssh.exec_command(sql)
            result = stdout.read().decode('utf-8', errors='ignore')

            lines = result.strip().split('\n')
            for line in lines[1:]:
                if line.strip():
                    title = line.strip()
                    self.published_titles.add(title)

            print(f"已加载 {len(self.published_titles)} 篇已发布文章")

            ssh.close()

        except Exception as e:
            print(f"加载已发布文章失败: {e}")

    def load_uncollected(self):
        """加载未采集的文章列表"""
        try:
            with open('uncollected_articles_complete.json', 'r', encoding='utf-8') as f:
                articles = json.load(f)
            return articles
        except:
            print("未找到未采集文章列表")
            return []

    def upload_image_to_wordpress(self, img_url):
        try:
            upload_url = f"{WP_REST_BASE}/upload-image" if WP_USE_REST_API else f"{WP_URL}/wp_upload_image.php"
            response = requests.post(
                upload_url,
                data={'img_url': img_url, 'api_key': WP_PASSWORD},
                timeout=60
            )

            if not response.text:
                return None, None

            text = response.text
            json_match = re.search(r'\{.*\}', text, re.DOTALL)
            if json_match:
                result = json.loads(json_match.group())
            else:
                result = response.json()

            if result.get('success'):
                return result.get('url'), result.get('attachment_id')
            return None, None

        except Exception as e:
            return None, None

    def get_article_detail(self, article_info):
        article_url = article_info['url']
        title = article_info['title']
        category = article_info.get('category', 'AI工具')
        print(f"  [{category:12s}] {title}")

        try:
            time.sleep(2)
            response = self.session.get(article_url, timeout=30)
            response.raise_for_status()
            soup = BeautifulSoup(response.text, 'html.parser')

            article = {
                'url': article_url,
                'title': title,
                'content': '',
                'excerpt': '',
                'images': [],
                'category': category,
                'cover_image': ''
            }

            # Extract cover image from detail page
            article['cover_image'] = self.extract_cover_image(response.text)

            main_content = soup.find('div', class_='commContainer1090')
            if not main_content:
                main_content = soup.find('div', class_=re.compile(r'md:pt-\[56px\]'))

            if main_content:
                for elem in main_content.find_all(['script', 'style', 'iframe', 'noscript', 'nav', 'header', 'footer']):
                    elem.decompose()

                for elem in main_content.find_all(class_=re.compile(r'sidebar|catalog|menu', re.I)):
                    elem.decompose()

                content_parts = []

                step_cards = main_content.find_all('div', class_=re.compile(r'mt-\[24px\].*mdBorderMain'))
                for card in step_cards:
                    content_parts.append(str(card))

                if not content_parts:
                    intro = main_content.find('div', id='intro')
                    if intro:
                        content_parts.append(str(intro))

                    tools = main_content.find('div', id='tools')
                    if tools:
                        content_parts.append(str(tools))

                    steps = main_content.find_all('div', id=re.compile(r'step-'))
                    for step in steps:
                        content_parts.append(str(step))

                    faq = main_content.find('div', id='faq')
                    if faq:
                        content_parts.append(str(faq))

                if content_parts:
                    article['content'] = '\n\n'.join(content_parts)

                    soup_content = BeautifulSoup(article['content'], 'html.parser')
                    img_tags = soup_content.find_all('img')

                    first_image_attachment_id = None
                    cover_image_id = None
                    cover_image_url = article.get('cover_image', '')

                    # Upload cover image first (preferred for featured image)
                    if cover_image_url:
                        cover_local_url, cover_id = self.upload_image_to_wordpress(cover_image_url)
                        if cover_local_url and cover_id:
                            cover_image_id = cover_id

                    for i, img in enumerate(img_tags, 1):
                        src = img.get('src') or img.get('data-src')
                        if src and not src.startswith('data:'):
                            # Skip if this is the cover image
                            if cover_image_url and self.normalize_url(src) == self.normalize_url(cover_image_url):
                                continue
                            local_url, attachment_id = self.upload_image_to_wordpress(src)
                            if local_url:
                                img['src'] = local_url
                                article['images'].append(local_url)
                                if i == 1 and attachment_id:
                                    first_image_attachment_id = attachment_id

                    if cover_image_id:
                        article['featured_image_id'] = cover_image_id
                    elif first_image_attachment_id:
                        article['featured_image_id'] = first_image_attachment_id

                    article['content'] = str(soup_content)

            if not article['content']:
                return None

            keywords_info = self.analyze_seo_keywords(article['title'], article['content'])
            article['seo_title'] = self.generate_seo_title(article['title'], keywords_info)
            article['seo_description'] = self.generate_seo_description(article['title'], article['content'], keywords_info)
            article['seo_keywords'] = ','.join(keywords_info['keywords'])
            article['categories'] = [category]
            article['tags'] = self.detect_tags(article['title'], article['content'], keywords_info)

            content_text = BeautifulSoup(article['content'], 'html.parser').get_text()
            content_text = re.sub(r'\s+', ' ', content_text).strip()
            if len(content_text) > 200:
                article['excerpt'] = content_text[:200] + '...'
            else:
                article['excerpt'] = content_text

            print(f"    ✓ 采集完成")
            return article

        except Exception as e:
            print(f"    ✗ 采集失败: {e}")
            return None

    def analyze_seo_keywords(self, title, content):
        soup = BeautifulSoup(content, 'html.parser')
        text = soup.get_text()
        keyword_freq = Counter()

        for cat, keywords in SEO_KEYWORDS.items():
            for keyword in keywords:
                if keyword in title:
                    keyword_freq[keyword] += 10
                freq = text.count(keyword)
                if freq > 0:
                    keyword_freq[keyword] += freq

        top_keywords = keyword_freq.most_common(5)
        keywords_list = [kw[0] for kw in top_keywords]
        primary_keyword = keywords_list[0] if keywords_list else title[:10]

        return {
            'primary': primary_keyword,
            'keywords': keywords_list
        }

    def generate_seo_title(self, title, keywords_info):
        primary = keywords_info['primary']
        if primary and primary not in title:
            return f"{primary} - {title}"
        return title

    def generate_seo_description(self, title, content, keywords_info):
        soup = BeautifulSoup(content, 'html.parser')
        text = soup.get_text()
        text = re.sub(r'\s+', ' ', text).strip()

        if len(text) > 150:
            desc = text[:150].rsplit(' ', 1)[0] + '...'
        else:
            desc = text

        primary = keywords_info['primary']
        if primary and primary not in desc:
            desc = f"{primary}教程：{desc}"

        return desc

    def detect_tags(self, title, content, keywords_info):
        tags = set()

        for keyword in keywords_info['keywords']:
            if len(keyword) >= 2:
                tags.add(keyword)

        ai_tools = ['ChatGPT', 'Claude', '豆包', 'Kimi', 'DeepSeek', '文心一言', '通义千问',
                   'Excel', 'Word', 'PPT', 'PDF', 'Prompt', '可灵', '即梦', '得理法搜', 'Suno']
        text = title + ' ' + BeautifulSoup(content, 'html.parser').get_text()
        for tool in ai_tools:
            if tool in text:
                tags.add(tool)

        return list(tags)[:10]

    def publish_to_wordpress(self, article):
        if not article:
            return False, None

        try:
            post_data = {
                'title': article['title'],
                'content': article['content'],
                'excerpt': article.get('excerpt', ''),
                'status': 'draft',
                'categories': article.get('categories', ['AI工具']),
                'tags': article.get('tags', []),
                'seo_title': article.get('seo_title', ''),
                'seo_description': article.get('seo_description', ''),
                'seo_keywords': article.get('seo_keywords', ''),
                'api_key': WP_PASSWORD
            }

            if article.get('featured_image_id'):
                post_data['featured_image'] = article['featured_image_id']

            publish_url = f"{WP_REST_BASE}/posts" if WP_USE_REST_API else f"{WP_URL}/wp_publish_helper.php"
            response = requests.post(publish_url, data=post_data, timeout=60)

            if not response.text:
                return False, None

            text = response.text
            json_match = re.search(r'\{.*\}', text, re.DOTALL)
            if json_match:
                result = json.loads(json_match.group())
            else:
                result = response.json()

            if result.get('success'):
                return True, result['post_id']
            else:
                print(f"    ✗ 发布失败: {result.get('error', '未知错误')}")
                return False, None

        except Exception as e:
            print(f"    ✗ 发布异常: {e}")
            return False, None

    def run(self):
        print("\n" + "="*120)
        print("采集所有123篇缺失的文章")
        print("="*120)
        print(f"开始时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")

        uncollected = self.load_uncollected()

        if not uncollected:
            print("\nOK - 所有文章都已采集！")
            return

        print(f"\n待采集文章: {len(uncollected)} 篇")

        success_count = 0
        failed_count = 0

        for i, article_info in enumerate(uncollected, 1):
            print(f"\n[{i}/{len(uncollected)}]", end=' ')

            article = self.get_article_detail(article_info)

            if article:
                success, post_id = self.publish_to_wordpress(article)
                if success:
                    success_count += 1
                    self.published_titles.add(article['title'])
                else:
                    failed_count += 1
            else:
                failed_count += 1

        print("\n" + "="*120)
        print(f"采集完成！成功: {success_count}, 失败: {failed_count}")
        print(f"结束时间: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print("="*120)

if __name__ == "__main__":
    if not SERVER_HOST or not SERVER_PASSWORD or not WP_PASSWORD:
        print("缺少凭据：请在 .env 配置 SERVER_HOST、SERVER_PASSWORD 和 WP_API_KEY/WP_APP_PASSWORD")
        sys.exit(1)
    collector = AllCollector()
    collector.run()
