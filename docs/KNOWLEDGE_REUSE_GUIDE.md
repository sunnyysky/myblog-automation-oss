# AIBase采集项目 - 知识复用体系

## 📚 项目概览

**项目名称**: AIBase自动采集系统
**项目周期**: 2026-02-07
**采集成果**: 226篇文章
**技术栈**: Python + BeautifulSoup + WordPress API + MySQL

---

## 🎯 核心经验总结

### 1. 技术架构设计

#### 1.1 分层架构
```
采集层 (Collector)
  ├── URL管理 (统一管理所有采集URL)
  ├── 内容提取 (BeautifulSoup解析)
  └── 图片处理 (上传到WordPress)

处理层 (Processor)
  ├── SEO优化 (关键词提取、标题优化)
  ├── 内容清洗 (去噪、格式化)
  └── 分类标签 (自动匹配)

发布层 (Publisher)
  ├── WordPress API对接
  ├── 数据库去重检查
  └── 错误处理重试
```

#### 1.2 关键文件清单

| 文件名 | 用途 | 重要性 |
|--------|------|--------|
| `collect_aibase_enhanced_v2.py` | 通用采集器（带去重） | ⭐⭐⭐⭐⭐ |
| `wp_publish_helper.php` | WordPress发布助手 | ⭐⭐⭐⭐⭐ |
| `check_all_categories_complete.py` | 全分类扫描工具 | ⭐⭐⭐⭐⭐ |
| `compare_complete.py` | 数据库对比验证工具 | ⭐⭐⭐⭐⭐ |

### 2. 关键问题与解决方案

#### 2.1 分类ID错误问题

**问题描述**:
- 最初使用错误的分类ID导致某些分类显示0篇文章
- 例如：营销推广ID应为 `1932311647674830849` 而非 `1932278271483973626`

**解决方案**:
```python
# ✅ 正确做法：从首页动态提取所有分类
response = session.get(f"{base_url}/zh", timeout=30)
soup = BeautifulSoup(response.text, 'html.parser')

categories = []
for link in soup.find_all('a', href=re.compile(r'/zh/class/\d+')):
    match = re.search(r'/zh/class/(\d+)', link.get('href'))
    if match:
        categories.append({
            'id': match.group(1),
            'name': link.get_text().strip(),
            'url': urljoin(base_url, link.get('href'))
        })
```

**教训**:
- ❌ 不要硬编码分类ID
- ✅ 必须动态从页面提取
- ✅ 先验证分类页面是否真的有文章

#### 2.2 去重机制问题

**问题描述**:
- 单纯依靠本地历史文件 (`aibase_published.json`) 无法避免重复
- 数据库中已有103篇，但采集器又采集了重复文章

**解决方案**:
```python
# ✅ 三重去重机制
def check_duplicate(self, title, url):
    # 1. 本地历史检查
    url_hash = hash(url)
    if url_hash in self.published_urls:
        return True

    # 2. 本地标题历史检查
    if title in self.published_titles:
        return True

    # 3. WordPress数据库实时查询
    exists, existing_id = self.check_post_exists_in_db(title)
    if exists:
        return True

    return False
```

**教训**:
- ❌ 不能只依赖本地文件
- ✅ 必须实时查询数据库
- ✅ 使用标题模糊匹配（包含关系）

#### 2.3 图片处理问题

**问题描述**:
- 原始网站图片使用腾讯云COS，有防盗链
- 直接使用外链会导致图片失效

**解决方案**:
```python
# ✅ 所有图片上传到WordPress本地
def upload_image_to_wordpress(self, img_url):
    upload_url = f"{WP_URL}/wp_upload_image.php"
    response = requests.post(
        upload_url,
        data={'img_url': img_url, 'api_key': WP_PASSWORD},
        timeout=60
    )
    result = response.json()
    if result.get('success'):
        return result.get('url'), result.get('attachment_id')
```

**教训**:
- ❌ 不要使用外链图片
- ✅ 必须上传到WordPress媒体库
- ✅ 保存attachment_id用于特色图片

#### 2.4 作者显示问题

**问题描述**:
- 文章发布后作者显示为空
- 原因：`wp_publish_helper.php` 缺少 `post_author` 字段

**解决方案**:
```php
// ✅ 在 wp_publish_helper.php 中添加
$post_data = [
    'post_title'    => $data['title'],
    'post_content'  => $data['content'],
    'post_status'   => isset($data['status']) ? $data['status'] : 'draft',
    'post_excerpt'  => isset($data['excerpt']) ? $data['excerpt'] : '',
    'post_author'   => 1,  // ✅ 固定使用 admin (ID=1) 作为作者
];
```

**教训**:
- ❌ 不要依赖WordPress默认值
- ✅ 明确指定所有必要字段
- ✅ 发布后要在数据库中验证

#### 2.5 PHP OPcache缓存问题

**问题描述**:
- 修改 `wp_publish_helper.php` 后不生效
- 原因：PHP OPcache缓存了旧版本

**解决方案**:
```bash
# ✅ 清除PHP缓存
systemctl restart php-fpm
# 或
systemctl restart httpd
```

**教训**:
- ❌ 修改PHP文件后要清除缓存
- ✅ 使用 `opcache_reset()` 或重启服务
- ✅ 验证修改是否生效

### 3. 开发流程最佳实践

#### 3.1 迭代开发流程

```
第一次迭代（单分类采集）
  ↓
发现分类ID问题
  ↓
第二次迭代（全分类扫描）
  ↓
发现去重问题
  ↓
第三次迭代（数据库去重）
  ↓
发现作者问题
  ↓
第四次迭代（修复字段）
  ↓
最终验证（226篇全部采集）
```

#### 3.2 测试验证流程

```python
# 1. 小批量测试
python collector.py  # 测试3篇

# 2. 中批量测试
python collector.py --count 10  # 测试10篇

# 3. 全量运行
python collector.py  # 采集所有

# 4. 验证对比
python verify.py  # 确认无遗漏
```

#### 3.3 错误处理模式

```python
# ✅ 完善的错误处理
try:
    article = self.get_article_detail(article_info)
    if article:
        success, post_id = self.publish_to_wordpress(article)
        if success:
            self.save_history()
        else:
            # 记录失败但继续下一篇
            self.log_failed(article_info, "发布失败")
    else:
        # 记录失败但继续下一篇
        self.log_failed(article_info, "采集失败")
except Exception as e:
    # 捕获异常但继续下一篇
    self.log_failed(article_info, f"异常: {e}")
    import traceback
    traceback.print_exc()
```

**原则**:
- ✅ 单篇文章失败不影响整体
- ✅ 详细记录错误信息
- ✅ 支持断点续传

### 4. 可复用组件库

#### 4.1 WordPress采集器模板

```python
#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
通用WordPress采集器模板
可复用于任何WordPress站点的文章采集
"""

class WordPressCollectorTemplate:
    """WordPress采集器基类"""

    def __init__(self, wp_url, username, password, api_key=None):
        """
        初始化采集器

        Args:
            wp_url: WordPress站点URL (如: https://example.com)
            username: WordPress用户名
            password: WordPress密码或应用密码
            api_key: API密钥（如果使用自定义API）
        """
        self.wp_url = wp_url
        self.username = username
        self.password = password
        self.api_key = api_key or password
        self.session = requests.Session()
        self.published_urls = set()
        self.published_titles = set()

    def load_published_from_db(self, db_config, table_prefix='wp_'):
        """从数据库加载已发布文章（用于去重）"""
        # TODO: 实现数据库连接逻辑
        pass

    def check_duplicate(self, title, url=None):
        """三重去重检查"""
        # 1. 本地URL检查
        # 2. 本地标题检查
        # 3. WordPress API检查
        pass

    def upload_image(self, img_url):
        """上传图片到WordPress媒体库"""
        # TODO: 实现图片上传逻辑
        pass

    def publish_article(self, article):
        """发布文章到WordPress"""
        # TODO: 实现文章发布逻辑
        pass

    def seo_optimize(self, article):
        """SEO优化（可选）"""
        # TODO: 实现SEO优化逻辑
        pass
```

#### 4.2 采集工具库

```
tools/
├── collectors/           # 采集器模块
│   ├── base.py          # 基础采集器
│   ├── wordpress.py     # WordPress采集器
│   └── generic.py       # 通用采集器
├── processors/          # 处理器模块
│   ├── seo.py           # SEO优化
│   ├── cleaner.py       # 内容清洗
│   └── deduplicator.py  # 去重处理
├── utils/               # 工具模块
│   ├── http.py          # HTTP客户端
│   ├── image.py         # 图片处理
│   └── db.py            # 数据库工具
└── templates/           # 模板文件
    ├── wordpress/       # WordPress相关模板
    └── config.py        # 配置模板
```

### 5. 标准操作程序（SOP）

#### 5.1 新项目采集流程

```
Step 1: 需求分析
  ├── 确定采集源URL
  ├── 确定采集目标（哪些分类）
  └── 确定采集频率（一次性/定时）

Step 2: 技术调研
  ├── 分析目标网站结构
  ├── 查看页面源代码
  ├── 测试反爬机制
  └── 确认数据格式

Step 3: 原型开发
  ├── 编写基础采集脚本
  ├── 测试单篇文章采集
  ├── 验证数据格式
  └── 测试图片上传

Step 4: 去重机制
  ├── 实现本地历史去重
  ├── 实现数据库查询去重
  └── 测试去重效果

Step 5: 批量测试
  ├── 小批量测试（5-10篇）
  ├── 中批量测试（50篇）
  └── 全量测试（所有）

Step 6: 验证对比
  ├── 对比原始网站文章数
  ├── 对比数据库文章数
  └── 确认无遗漏

Step 7: 部署上线
  ├── 配置定时任务
  ├── 监控运行日志
  └── 处理异常情况
```

#### 5.2 问题排查流程

```
遇到问题时的排查顺序：

1. 检查日志
   └── 查看详细的错误信息

2. 检查网络
   ├── 网站是否可访问
   ├── 响应状态码是否正常
   └── 是否被反爬限制

3. 检查代码
   ├── 参数是否正确
   ├── 逻辑是否有误
   └── 边界条件是否考虑

4. 检查数据
   ├── 原始数据格式是否变化
   ├── 数据库结构是否正确
   └── 去重逻辑是否生效

5. 检查环境
   ├── Python版本是否兼容
   ├── 依赖库是否安装
   └── 缓存是否清除
```

### 6. 常见陷阱与避坑指南

#### 6.1 编码问题

```python
# ❌ 错误：Windows控制台编码问题
print("测试中文")  # 可能报错

# ✅ 正确：设置UTF-8编码
import sys
import io
if sys.platform == 'win32':
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')
```

#### 6.2 时间设置问题

```python
# ❌ 错误：采集太快被封IP
for url in urls:
    fetch(url)  # 可能被限制

# ✅ 正确：添加延迟
import time
for url in urls:
    fetch(url)
    time.sleep(2)  # 每次间隔2秒
```

#### 6.3 异常处理问题

```python
# ❌ 错误：异常导致程序中断
for article in articles:
    collect(article)  # 失败后程序退出

# ✅ 正确：捕获异常继续执行
for article in articles:
    try:
        collect(article)
    except Exception as e:
        log_error(article, e)
        continue  # 继续下一篇
```

### 7. 项目文档模板

#### 7.1 项目启动检查清单

- [ ] 需求文档已确认
- [ ] 技术方案已评审
- [ ] 开发环境已搭建
- [ ] 数据库连接已测试
- [ ] API密钥已获取
- [ ] 反爬机制已分析
- [ ] 测试账号已准备
- [ ] 监控日志已配置

#### 7.2 代码审查检查点

- [ ] 去重机制是否完善
- [ ] 异常处理是否完整
- [ ] 图片处理是否正确
- [ ] 编码问题是否处理
- [ ] 性能是否优化
- [ ] 是否支持断点续传
- [ ] 日志是否详细
- [ ] 配置是否灵活

#### 7.3 部署验证清单

- [ ] 数据库备份已创建
- [ ] 配置文件已更新
- [ ] 定时任务已配置
- [ ] 监控告警已设置
- [ ] 日志路径已确认
- [ ] 测试账号已验证
- [ ] 回滚方案已准备

### 8. 关键代码片段库

#### 8.1 WordPress REST API封装

```python
def publish_to_wordpress(self, article):
    """发布到WordPress的通用方法"""
    post_data = {
        'title': article['title'],
        'content': article['content'],
        'excerpt': article.get('excerpt', ''),
        'status': 'draft',  # draft 或 publish
        'categories': article.get('categories', []),
        'tags': article.get('tags', []),
    }

    # 使用WordPress REST API
    response = requests.post(
        f"{self.wp_url}/wp-json/wp/v2/posts",
        auth=(self.username, self.password),
        json=post_data,
        timeout=60
    )

    if response.status_code == 201:
        return True, response.json()['id']
    else:
        return False, response.text
```

#### 8.2 数据库去重查询

```python
def check_exists_in_db(self, title):
    """检查文章是否存在于数据库"""
    # 使用WordPress REST API搜索
    search_url = f"{self.wp_url}/wp-json/wp/v2/posts"
    params = {'search': title, 'per_page': 1}

    response = self.session.get(search_url, params=params)
    if response.status_code == 200:
        posts = response.json()
        for post in posts:
            # 模糊匹配
            if title in post['title']['rendered'] or \
               post['title']['rendered'] in title:
                return True, post['id']
    return False, None
```

#### 8.3 图片上传处理

```python
def process_content_images(self, content, base_url):
    """处理内容中的所有图片"""
    soup = BeautifulSoup(content, 'html.parser')
    img_tags = soup.find_all('img')

    for i, img in enumerate(img_tags, 1):
        src = img.get('src') or img.get('data-src')
        if src and not src.startswith('data:'):
            # 上传到WordPress
            local_url, attachment_id = self.upload_image(src)
            if local_url:
                img['src'] = local_url
                print(f"  图片 {i}/{len(img_tags)} 上传成功")
            else:
                # 失败时使用绝对URL
                img['src'] = urljoin(base_url, src)

    return str(soup)
```

### 9. 知识沉淀形式

#### 9.1 代码库组织

```
project-collection/
├── docs/                    # 文档
│   ├── README.md           # 项目说明
│   ├── architecture.md     # 架构设计
│   ├── api.md              # API文档
│   └── issues.md           # 问题记录
├── src/                     # 源代码
│   ├── collectors/         # 采集器
│   ├── processors/         # 处理器
│   └── utils/              # 工具
├── config/                  # 配置
│   ├── wordpress.example.json
│   └── categories.json
├── tests/                   # 测试
│   ├── test_collector.py
│   └── test_publisher.py
└── scripts/                 # 脚本
    ├── deploy.sh           # 部署脚本
    └── verify.sh           # 验证脚本
```

#### 9.2 经验复用方法

1. **直接使用模板**
   - 复制 `collect_aibase_enhanced_v2.py`
   - 修改配置参数
   - 调整采集规则

2. **参考问题解决手册**
   - 遇到类似问题查看对应章节
   - 按照排查流程逐步检查

3. **复用代码片段**
   - 从代码片段库复制所需功能
   - 根据项目需求调整

4. **遵循SOP流程**
   - 按照标准流程执行
   - 完成检查清单确认

### 10. 下一步优化方向

#### 10.1 功能增强
- [ ] 支持更多采集源（不仅WordPress）
- [ ] 支持分布式采集（多机器并行）
- [ ] 支持定时任务调度（APScheduler）
- [ ] 支持可视化监控面板

#### 10.2 稳定性提升
- [ ] 完善的错误恢复机制
- [ ] 更智能的重试策略
- [ ] 更详细的日志系统
- [ ] 性能监控和告警

#### 10.3 易用性改进
- [ ] 配置文件化（不再硬编码）
- [ ] 命令行参数优化
- [ ] 进度条显示
- [ ] 一键部署脚本

---

## 📖 快速参考

### 关键配置
- WordPress API: `/wp-json/wp/v2`
- 自定义发布助手: `/wp_publish_helper.php`
- 图片上传接口: `/wp_upload_image.php`

### 常用命令
```bash
# 采集到草稿
python simple_collect_drafts.py

# 验证所有文章
python compare_complete.py

# 查看数据库
mysql -u root -p db_name -e "SELECT COUNT(*) FROM wp_posts WHERE post_type='post';"
```

### 重要文件
- 采集器: `collect_aibase_enhanced_v2.py`
- 发布助手: `wp_publish_helper.php`
- 验证工具: `compare_complete.py`

---

**文档创建时间**: 2026-02-07
**最后更新时间**: 2026-02-07
**版本**: v1.0
**维护者**: Claude & User
