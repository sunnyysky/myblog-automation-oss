# MyBlog Tools Plugin

Reusable WordPress plugin for automation endpoints and content tooling.

## Features

- REST API for:
  - list drafts
  - list published posts
  - publish draft
  - delete posts
  - create post with SEO fields
  - upload remote image to media library

## Security

API key is required for all routes.

Key source priority:

1. `BLOG_API_KEY` environment variable
2. `BLOG_API_KEY` constant in `wp-config.php`

## Install

1. Copy folder `myblog-tools` into:
   - `wp-content/plugins/`
2. Activate plugin in WordPress admin.
3. Configure API key:
   - `define('BLOG_API_KEY', 'your-strong-key');`
   - or set server env `BLOG_API_KEY`.

## Routes

Base: `/wp-json/myblog/v1`

- `GET /drafts?api_key=...`
- `GET /published?api_key=...`
- `POST /publish-draft`
- `POST /delete-posts`
- `POST /posts`
- `POST /upload-image`

POST body can be JSON or form-data and must include `api_key`.

## Python Script Compatibility

For this repository scripts, set:

- `WP_USE_REST_API=1`
- `WP_REST_BASE=https://your-site/wp-json/myblog/v1`
