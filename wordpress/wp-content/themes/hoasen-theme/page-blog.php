<?php
/**
 * Template Name: Blog
 */
$lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
$is_vi = (strpos($lang,'vi')===0);
$t = [
  'title'   => $is_vi ? 'HoaSen Table Journal' : 'HoaSen Table Journal',
  'desc'    => $is_vi ? 'Bài viết về kỹ thuật, hiệu năng và kiến trúc của HoaSen Table.' : 'Articles on engineering, performance, and architecture of HoaSen Table.',
  'back'    => $is_vi ? '← Về trang chủ' : '← Back to Home',
];

// Query real blog posts
$args = array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'orderby'        => 'date',
    'order'          => 'DESC',
);
$blog_query = new WP_Query($args);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<link rel="icon" type="image/svg+xml" href="<?php echo get_stylesheet_directory_uri(); ?>/logo_svg.svg"/>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<title><?php echo esc_html($t['title']); ?></title>
<meta name="description" content="<?php echo esc_attr($t['desc']); ?>"/>
<?php wp_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@400;500;600;700;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f0efed;--red-rgb:190,24,74;--red2-rgb:219,39,119;--red:rgb(var(--red-rgb));--red2:rgb(var(--red2-rgb));
  --text:#0f0f0f;--rs:rgba(var(--red-rgb),.07);
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:"Outfit",sans-serif;overflow-x:hidden}
body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:-1;
  background:radial-gradient(circle at 14% 13%,rgba(var(--red-rgb),.042),transparent 31%),
             linear-gradient(168deg,#f3f2ef,#e9e6e0 55%,#eee8e1)}
.bg-lotus{position:fixed;top:50%;left:65%;transform:translate(-50%,-50%);
  width:max(90vh,600px);height:max(90vh,600px);z-index:0;opacity:.035;pointer-events:none;
  background-image:url('<?php echo get_stylesheet_directory_uri(); ?>/logo.png');
  background-size:contain;background-repeat:no-repeat;background-position:center}
.dot-grid{position:absolute;inset:0;opacity:.13;pointer-events:none;
  background-image:radial-gradient(circle,rgba(0,0,0,.16) 1px,transparent 1px);
  background-size:42px 42px;
  mask-image:radial-gradient(ellipse at 50% 10%,black,transparent 70%)}
.brand{position:absolute;top:22px;left:32px;z-index:40;display:flex;align-items:center;gap:10px;text-decoration:none}
.brand-name{font-family:"Cormorant Garamond",serif;font-size:21px;font-weight:700;color:var(--text);letter-spacing:-.025em}
.back-btn{position:absolute;top:28px;right:32px;z-index:40;font-size:11px;font-weight:700;color:#6b7280;text-decoration:none;transition:color .15s}
.back-btn:hover{color:var(--red)}

.blog-wrap{position:relative;z-index:5;max-width:1040px;margin:120px auto 80px;padding:0 24px}

.blog-hdr {
  margin-bottom: 40px;
  border-bottom: 1px solid rgba(0,0,0,.08);
  padding-bottom: 32px;
}
.blog-hdr .kicker {
  font-size: 10px;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: var(--red);
  font-weight: 900;
  margin-bottom: 12px;
  font-family: "Outfit", sans-serif;
}
.blog-hdr h1 {
  font-family: "Cormorant Garamond", serif;
  font-size: clamp(32px, 5vw, 54px);
  line-height: 1.1;
  font-weight: 700;
  color: #0f0f0f;
  margin-bottom: 12px;
  letter-spacing: -0.02em;
}
.blog-hdr p {
  font-size: 15px;
  line-height: 1.6;
  color: #6b7280;
  max-width: 580px;
}

.blog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
  gap: 32px;
  width: 100%;
}
.post-card {
  position: relative;
  background: rgba(255, 255, 255, 0.7);
  border-radius: 16px;
  border: 1px solid rgba(0, 0, 0, .06);
  box-shadow: 0 4px 20px rgba(0, 0, 0, .02);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  backdrop-filter: blur(12px);
}
.post-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 20px 45px rgba(0, 0, 0, .08);
  border-color: rgba(137, 24, 24, .18);
  background: #fff;
}
.card-link-overlay {
  position: absolute;
  inset: 0;
  z-index: 2;
}
.card-img-wrap {
  position: relative;
  width: 100%;
  height: 180px;
  overflow: hidden;
  background: #e9e6e0;
}
.card-img-wrap img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.5s ease;
}
.post-card:hover .card-img-wrap img {
  transform: scale(1.05);
}
.card-thumb-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--red);
  background: radial-gradient(circle at 50% 50%, rgba(137, 24, 24, 0.06), transparent);
}
.card-thumb-placeholder svg {
  width: 42px;
  height: 42px;
  opacity: 0.22;
  transition: transform 0.3s ease;
}
.post-card:hover .card-thumb-placeholder svg {
  transform: scale(1.1);
}
.card-body {
  padding: 24px;
  display: flex;
  flex-direction: column;
  flex-grow: 1;
}
.card-meta {
  font-size: 10px;
  font-weight: 700;
  color: #888;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 12px;
  font-family: "Outfit", sans-serif;
}
.card-title {
  font-family: "Cormorant Garamond", serif;
  font-size: 24px;
  font-weight: 700;
  color: #111;
  line-height: 1.25;
  margin-bottom: 12px;
  transition: color 0.2s ease;
}
.post-card:hover .card-title {
  color: var(--red);
}
.card-excerpt {
  font-size: 14.5px;
  line-height: 1.6;
  color: #4b5563;
  margin-bottom: 24px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  flex-grow: 1;
}
.card-more {
  font-size: 10.5px;
  font-weight: 800;
  color: var(--red);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: auto;
  transition: gap 0.25s ease;
  font-family: "Outfit", sans-serif;
}
.post-card:hover .card-more {
  gap: 10px;
}
</style>
</head>
<body>

<div class="bg-lotus" aria-hidden="true"></div>
<div class="dot-grid"></div>

<a href="<?php echo esc_url(home_url('/')); ?>" class="brand">
  <img src="<?php echo get_stylesheet_directory_uri(); ?>/logo_svg.svg" width="32" height="32" alt="HoaSen Table logo" style="filter:drop-shadow(0 3px 10px rgba(var(--red-rgb),.22))"/>
  <div class="brand-name">HoaSen Table</div>
</a>
<a href="<?php echo esc_url(home_url('/')); ?>" class="back-btn"><?php echo esc_html($t['back']); ?></a>

<div class="blog-wrap">
  <header class="blog-hdr">
    <div class="kicker"><?php esc_html_e($is_vi ? 'HoaSen Nhật Ký' : 'HoaSen Journal', 'hoasen-theme'); ?></div>
    <h1><?php esc_html_e($is_vi ? 'Kỹ Thuật & Hiệu Năng' : 'Engineering & Performance', 'hoasen-theme'); ?></h1>
    <p><?php esc_html_e($is_vi ? 'Các bài viết đi sâu vào kỹ thuật, hiệu năng và kiến trúc thiết kế hệ thống dữ liệu của đội ngũ HoaSen Table.' : 'Deep-dives into database engine design, query performance optimization, and front-end architecture from the HoaSen Table team.', 'hoasen-theme'); ?></p>
  </header>

  <div class="blog-grid">
    <?php if ( $blog_query->have_posts() ) : ?>
      <?php while ( $blog_query->have_posts() ) : $blog_query->the_post(); ?>
        <?php 
        $thumb = get_the_post_thumbnail_url(get_the_ID(), 'large'); 
        $has_thumb = !empty($thumb);
        ?>
        <article class="post-card">
          <a href="<?php the_permalink(); ?>" class="card-link-overlay" aria-label="<?php the_title_attribute(); ?>"></a>
          <div class="card-img-wrap <?php echo $has_thumb ? '' : 'no-thumb'; ?>">
            <?php if ($has_thumb) : ?>
              <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" />
            <?php else : ?>
              <div class="card-thumb-placeholder">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20M4 19.5V3a1 1 0 0 1 1-1h13a1 1 0 0 1 1 1v16.5M6 7H14M6 11H12"/></svg>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body">
            <div class="card-meta">
              <span class="card-date"><?php echo get_the_date(); ?></span>
              <span class="card-author">/ <?php the_author(); ?></span>
            </div>
            <h2 class="card-title"><?php the_title(); ?></h2>
            <p class="card-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 18, '...'); ?></p>
            <span class="card-more">Read Article <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
          </div>
        </article>
      <?php endwhile; wp_reset_postdata(); ?>

    <?php else : ?>
      <!-- Fallback Premium Placeholder Grid if database is empty -->
      <article class="post-card">
        <a href="#" class="card-link-overlay"></a>
        <div class="card-img-wrap">
          <div class="card-thumb-placeholder">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20M4 19.5V3a1 1 0 0 1 1-1h13a1 1 0 0 1 1 1v16.5M6 7H14M6 11H12"/></svg>
          </div>
        </div>
        <div class="card-body">
          <div class="card-meta">
            <span class="card-date">09/07/2026</span>
            <span class="card-author">/ Engineering</span>
          </div>
          <h2 class="card-title"><?php esc_html_e($is_vi ? 'Tối ưu Autocomplete với Grammar Parsing' : 'Optimizing SQL Autocomplete', 'hoasen-theme'); ?></h2>
          <p class="card-excerpt"><?php esc_html_e($is_vi ? 'Abstract Syntax Tree (AST) phân tích cú pháp hoạt động trực tiếp cho mỗi phương ngữ SQL để quyết định gợi ý chính xác.' : 'Abstract Syntax Tree (AST) parsing running directly for each SQL dialect to decide correct suggestions.'); ?></p>
          <span class="card-more">Read Article <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </article>

      <article class="post-card">
        <a href="#" class="card-link-overlay"></a>
        <div class="card-img-wrap">
          <div class="card-thumb-placeholder">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20M4 19.5V3a1 1 0 0 1 1-1h13a1 1 0 0 1 1 1v16.5M6 7H14M6 11H12"/></svg>
          </div>
        </div>
        <div class="card-body">
          <div class="card-meta">
            <span class="card-date">02/07/2026</span>
            <span class="card-author">/ Performance</span>
          </div>
          <h2 class="card-title"><?php esc_html_e($is_vi ? 'Virtual Grid: Render 1 triệu hàng 60 FPS' : 'Virtual Grid: 1M rows at 60 FPS', 'hoasen-theme'); ?></h2>
          <p class="card-excerpt"><?php esc_html_e($is_vi ? 'Duy trì chính xác 23 DOM node cho dù tập dữ liệu lớn đến đâu, tái chế các phần tử khi cuộn.' : 'Maintain exactly 23 DOM nodes regardless of dataset size, recycling elements as you scroll.'); ?></p>
          <span class="card-more">Read Article <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </article>

      <article class="post-card">
        <a href="#" class="card-link-overlay"></a>
        <div class="card-img-wrap">
          <div class="card-thumb-placeholder">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20M4 19.5V3a1 1 0 0 1 1-1h13a1 1 0 0 1 1 1v16.5M6 7H14M6 11H12"/></svg>
          </div>
        </div>
        <div class="card-body">
          <div class="card-meta">
            <span class="card-date">25/06/2026</span>
            <span class="card-author">/ Design</span>
          </div>
          <h2 class="card-title"><?php esc_html_e($is_vi ? 'Tại sao Native đánh bại Electron' : 'Why Native beats Electron', 'hoasen-theme'); ?></h2>
          <p class="card-excerpt"><?php esc_html_e($is_vi ? 'egui biên dịch ra tệp thực thi chỉ 3 MB, khởi động trong 78ms, sử dụng 18 MB RAM.' : 'egui compiles to a 3 MB binary, starts in 78ms, and uses 18 MB idle RAM.'); ?></p>
          <span class="card-more">Read Article <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
        </div>
      </article>
    <?php endif; ?>
  </div>
</div>

<?php wp_footer(); ?>
</body>
</html>
