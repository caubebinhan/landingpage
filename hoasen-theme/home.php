<?php
/**
 * Template Name: Blog
 */
$lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
$t = [
  'title'   => __( 'HoaSen Table Journal', 'hoasen-theme' ),
  'desc'    => __( 'Articles on engineering, performance, and architecture of HoaSen Table.', 'hoasen-theme' ),
  'back'    => __( '← Back to Home', 'hoasen-theme' ),
];

// Query real blog posts
$paged = ( get_query_var('paged') ) ? absint( get_query_var('paged') ) : 1;
$args = array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'paged'          => $paged,
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
<!-- title and meta description are emitted by Yoast SEO via wp_head() below -->
<script type="application/ld+json">
<?php
$posts_schema = [];
if ( $blog_query->have_posts() ) {
    while ( $blog_query->have_posts() ) {
        $blog_query->the_post();
        $posts_schema[] = [
            '@type' => 'BlogPosting',
            'headline' => get_the_title(),
            'url' => get_permalink(),
            'datePublished' => get_the_date(DATE_W3C),
            'author' => [
                '@type' => 'Person',
                'name' => get_the_author()
            ]
        ];
    }
    wp_reset_postdata();
} else {
    // No posts published yet in this language: emit an empty list rather than
    // fabricated placeholder articles (fake BlogPosting entries are bad schema data).
    $posts_schema = [];
}

echo wp_json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Blog',
    'name' => __( 'HoaSen Journal', 'hoasen-theme' ),
    'description' => $t['desc'],
    'url' => home_url('/blog/'),
    'inLanguage' => $lang,
    'blogPost' => $posts_schema
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
</script>
<?php wp_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Spectral:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Public+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f7f5f0;--red-rgb:190,24,74;--red2-rgb:219,39,119;--red:rgb(var(--red-rgb));--red2:rgb(var(--red2-rgb));
  --text:#1c1917;--rs:rgba(var(--red-rgb),.07);
}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:"Public Sans",sans-serif;overflow-x:hidden}
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
.brand-name{font-family:"Public Sans",sans-serif;font-size:21px;font-weight:800;color:var(--text);letter-spacing:-.03em}
.back-btn{position:absolute;top:28px;right:32px;z-index:40;font-size:11px;font-weight:700;color:#6b7280;text-decoration:none;transition:color .15s}
.back-btn:hover{color:var(--red)}

.blog-wrap{position:relative;z-index:5;max-width:1040px;margin:120px auto 80px;padding:0 24px}

.blog-hdr {
  margin-bottom: 40px;
  border-bottom: 1.5px solid rgba(0,0,0,.08);
  padding-bottom: 32px;
}
.blog-hdr .kicker {
  font-size: 10px;
  letter-spacing: .22em;
  text-transform: uppercase;
  color: var(--red);
  font-weight: 700;
  margin-bottom: 12px;
  font-family: "Public Sans", sans-serif;
}
.blog-hdr h1 {
  font-family: "Spectral", serif;
  font-size: clamp(34px, 5.2vw, 56px);
  line-height: 1.1;
  font-weight: 600;
  font-style: italic;
  color: #1c1917;
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
  background: #ffffff;
  border-radius: 6px;
  border: 1px solid rgba(0, 0, 0, .08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, .02);
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.post-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 32px rgba(var(--red-rgb), .05);
  border-color: rgba(var(--red-rgb), .3);
  background: #ffffff;
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
  font-family: "Public Sans", sans-serif;
}
.card-title {
  font-family: "Spectral", serif;
  font-size: 22px;
  font-weight: 600;
  color: #1c1917;
  line-height: 1.25;
  margin-bottom: 12px;
  letter-spacing: -0.01em;
  transition: color 0.2s ease;
}
.post-card:hover .card-title {
  color: var(--red);
}
.card-excerpt {
  font-size: 14px;
  line-height: 1.65;
  color: #57534e;
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
  font-weight: 700;
  color: var(--red);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-top: auto;
  transition: gap 0.25s ease;
  font-family: "Public Sans", sans-serif;
}
.post-card:hover .card-more {
  gap: 10px;
}
.pagination {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 8px;
  margin-top: 50px;
  font-family: "Public Sans", sans-serif;
}
.pagination a, .pagination span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  height: 38px;
  min-width: 38px;
  padding: 0 12px;
  border-radius: 6px;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  color: #57534e;
  background: #fff;
  border: 1px solid rgba(0,0,0,.08);
  transition: all 0.2s;
  box-shadow: 0 2px 4px rgba(0,0,0,.02);
}
.pagination a:hover {
  border-color: rgba(var(--red-rgb),.3);
  color: var(--red);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(var(--red-rgb),.08);
}
.pagination .current {
  background: var(--red);
  color: #fff;
  border-color: var(--red);
}
.lang-sw{position:absolute;top:28px;right:150px;z-index:40;font-family:"Public Sans",sans-serif;font-size:11px;font-weight:700}
.lang-sw ul{list-style:none;display:flex;gap:4px}
.lang-sw a{text-decoration:none;color:#6b7280;padding:4px 8px;border-radius:4px;transition:color .15s}
.lang-sw a:hover{color:var(--red)}
.lang-sw .current-lang a{color:var(--red);pointer-events:none}
@media(max-width:768px){.lang-sw{position:static;margin:70px 18px 0;display:flex;justify-content:flex-end}}
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
<?php if ( function_exists( 'pll_the_languages' ) ) : ?>
<div class="lang-sw"><ul><?php pll_the_languages( array( 'show_flags' => 0, 'show_names' => 1, 'hide_current' => 0 ) ); ?></ul></div>
<?php endif; ?>

<div class="blog-wrap">
  <header class="blog-hdr">
    <div class="kicker"><?php esc_html_e( 'HoaSen Journal', 'hoasen-theme' ); ?></div>
    <h1><?php esc_html_e( 'Engineering & Performance', 'hoasen-theme' ); ?></h1>
    <p><?php esc_html_e( 'Deep-dives into database engine design, query performance optimization, and front-end architecture from the HoaSen Table team.', 'hoasen-theme' ); ?></p>
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
      <p class="blog-empty"><?php esc_html_e( 'No articles have been published in this language yet.', 'hoasen-theme' ); ?></p>
    <?php endif; ?>
  </div>

  <?php
  $big = 999999999;
  $pagination = paginate_links(array(
      'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
      'format'    => '?paged=%#%',
      'current'   => max(1, get_query_var('paged')),
      'total'     => $blog_query->max_num_pages,
      'prev_text' => __( '« Prev', 'hoasen-theme' ),
      'next_text' => __( 'Next »', 'hoasen-theme' ),
  ));
  if ($pagination) : ?>
    <div class="pagination">
      <?php echo $pagination; ?>
    </div>
  <?php endif; ?>

</div>

<?php wp_footer(); ?>
</body>
</html>
