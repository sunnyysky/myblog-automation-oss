# Homepage Module Spec (Magazine Layout)

## Scope

Apply to WordPress home/blog index page in child theme.

## Goals

1. Keep hero area, but reduce visual weight and loading cost.
2. Present multiple fixed high-value sections after latest posts.
3. Add practical right-sidebar modules (search/hot/category/subscribe).
4. Hide empty categories and empty sidebar blocks.

## Section Order

1. Latest posts
2. Prompt Tutorials (`prompt-tutorials`)
3. AI Tools (`ai-tools`)
4. AI Writing (`ai-writing`)
5. Word Tutorials (`word-tutorials`)
6. Creative Design (`creative-design`)
7. Marketing Growth (`marketing-growth`)
8. Excel Tutorials (`excel-tutorials`)
9. PPT Design (`ppt-design`)
10. Video Production (`video-production`)
11. Tool Recommendations (`tool-recommendations`)

## Per-section Rules

1. Show max 6 posts per section.
2. Skip rendering if category has zero published posts.
3. Show "View all" link to category archive.
4. Use existing theme list template for card consistency.

## Sidebar Modules

1. Site Search.
2. Hot posts (prefer 30-day hot list, fallback to latest posts).
3. Category quick links (based on rendered homepage sections).
4. Subscribe block (RSS/about/register links).
5. Keep original sidebar widgets and remove placeholder widgets containing "暂无内容".

## Hero Rules

1. Keep existing slider/options source.
2. Limit slider items to first 4.
3. Keep feature images max 3.

## Sidebar Rules

1. Render existing sidebar widgets.
2. Remove widgets that only show "暂无内容".

## Performance Rules

1. Do not add additional blocking JS.
2. Keep section queries bounded (`posts_per_page=6`).
3. Keep thumbnail rendering through theme lazy image helper.
