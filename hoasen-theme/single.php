<?php
$lang = function_exists( 'pll_current_language' ) ? pll_current_language() : 'en';
$t = array(
    'back_blog' => __( '← Back to Blog', 'hoasen-theme' ),
    'related'   => __( 'Related articles', 'hoasen-theme' ),
    'read_next' => __( 'Read next', 'hoasen-theme' ),
    'minute'    => __( 'min read', 'hoasen-theme' ),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logo_svg.svg' ); ?>"/>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<!-- title is emitted by Yoast SEO via wp_head() below -->
<?php wp_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Spectral:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Public+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#f7f5f0;--red-rgb:190,24,74;--red2-rgb:219,39,119;--red:rgb(var(--red-rgb));--red2:rgb(var(--red2-rgb));--text:#1c1917;--rs:rgba(var(--red-rgb),.07)}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:"Public Sans",sans-serif;overflow-x:hidden}
body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:-1;background:radial-gradient(circle at 14% 13%,rgba(var(--red-rgb),.042),transparent 31%),linear-gradient(168deg,#f3f2ef,#e9e6e0 55%,#eee8e1)}
.bg-lotus{position:fixed;top:50%;left:65%;transform:translate(-50%,-50%);width:max(90vh,600px);height:max(90vh,600px);z-index:0;opacity:.035;pointer-events:none;background-image:url('<?php echo esc_url( get_stylesheet_directory_uri() . '/logo.png' ); ?>');background-size:contain;background-repeat:no-repeat;background-position:center}
.dot-grid{position:absolute;inset:0;opacity:.13;pointer-events:none;background-image:radial-gradient(circle,rgba(0,0,0,.16) 1px,transparent 1px);background-size:42px 42px;mask-image:radial-gradient(ellipse at 50% 10%,black,transparent 70%)}
.brand{position:absolute;top:22px;left:32px;z-index:40;display:flex;align-items:center;gap:10px;text-decoration:none}
.brand-name{font-family:"Public Sans",sans-serif;font-size:21px;font-weight:800;color:var(--text);letter-spacing:-.03em}
.back-btn{position:absolute;top:28px;right:32px;z-index:40;font-size:11px;font-weight:700;color:#6b7280;text-decoration:none;transition:color .15s}
.back-btn:hover{color:var(--red)}
.single-wrap{position:relative;z-index:5;max-width:760px;margin:120px auto 80px;padding:50px;background:#fff;border-radius:6px;border:1px solid rgba(0,0,0,0.08);box-shadow:0 12px 42px rgba(0,0,0,0.02)}
.post-cover{margin:-50px -50px 32px;border-radius:6px 6px 0 0;overflow:hidden}
.post-cover img{width:100%;height:auto;display:block}
@media(max-width:768px){.post-cover{margin:-30px -20px 24px}}
.post-header{margin-bottom:38px;border-bottom:1.5px solid rgba(0,0,0,.08);padding-bottom:32px}
.post-kicker{font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--red);margin-bottom:14px;font-family:"Public Sans",sans-serif}
.post-header h1{font-family:"Spectral",serif;font-size:clamp(32px,5vw,46px);line-height:1.15;font-weight:600;font-style:italic;color:#1c1917;margin-bottom:16px;letter-spacing:-.02em;text-wrap:balance}
.post-deck{font-size:15px;line-height:1.65;color:#57534e;max-width:700px;margin:18px 0;text-wrap:pretty;font-family:"Public Sans",sans-serif}
.meta{display:flex;flex-wrap:wrap;gap:10px;align-items:center;font-size:11px;color:#6b7280;font-family:"Public Sans",sans-serif;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.post-tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}
.post-tags a{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:rgba(var(--red-rgb),.07);color:var(--red);text-decoration:none;font:700 10px "Public Sans",sans-serif;letter-spacing:.03em}
.post-body{font-family:"Public Sans",sans-serif;font-size:15.5px;line-height:1.75;color:#292524}
.post-body p{margin-bottom:24px;text-wrap:pretty}
.post-body code{font-family:"JetBrains Mono",monospace;font-size:13.5px;background:rgba(var(--red-rgb),0.05);padding:2px 6px;border-radius:4px;color:var(--red)}
.post-body h2,.post-body h3{font-family:"Spectral",serif;font-weight:600;font-style:italic;margin:38px 0 16px;color:#1c1917;letter-spacing:-.01em}
.post-body h2{font-size:27px}.post-body h3{font-size:21px}
.post-body blockquote{border-left:2px solid rgba(var(--red-rgb),.4);padding-left:20px;margin:28px 0;font-style:italic;color:#57534e;font-size:17px;line-height:1.7}
.post-body ul,.post-body ol{margin:20px 0 24px 24px;font-size:15px;color:#374151;font-family:"Public Sans",sans-serif;line-height:1.7}
.post-body li{margin-bottom:8px}
.post-body pre{background:#f8f9fa;padding:20px;border-radius:6px;overflow-x:auto;margin:28px 0;border:1px solid rgba(0,0,0,.05)}
.post-body pre code{background:transparent;padding:0;color:#1f2937;font-size:13px}
.post-body pre.mermaid{background:#fff;padding:24px;text-align:center}
.post-body hr{border:none;border-top:1.5px solid rgba(0,0,0,.08);margin:36px 0}
.post-body table{width:100%;border-collapse:collapse;margin:28px 0;font-size:14px}
.post-body th,.post-body td{padding:10px 14px;border:1px solid rgba(0,0,0,.08);text-align:left}
.post-body thead th{background:#f8f9fa;font-family:"Public Sans",sans-serif;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#57534e}
.post-body a{color:var(--red);text-decoration:underline;text-decoration-color:rgba(var(--red-rgb),.3)}
.related-wrap{margin-top:46px;padding-top:30px;border-top:1.5px solid rgba(0,0,0,.08)}
.related-wrap h2{font-family:"Spectral",serif;font-size:24px;font-weight:600;font-style:italic;letter-spacing:-.01em;margin-bottom:18px}
.related-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.related-card{display:block;text-decoration:none;color:inherit;padding:18px;border:1px solid rgba(0,0,0,.08);border-radius:6px;background:#ffffff;transition:transform .18s,border-color .18s,background .18s}
.related-card:hover{transform:translateY(-2px);border-color:rgba(var(--red-rgb),.22);background:#ffffff;box-shadow:0 6px 18px rgba(0,0,0,0.02)}
.related-card small{display:block;font:700 10px "Public Sans",sans-serif;letter-spacing:.12em;text-transform:uppercase;color:var(--red);margin-bottom:8px}
.related-card strong{display:block;font-family:"Spectral",serif;font-size:17px;font-weight:600;line-height:1.3;margin-bottom:8px;letter-spacing:-.01em}
.related-card span{display:block;font-size:12px;line-height:1.5;color:#6b7280;font-family:"Public Sans",sans-serif}
@media(max-width:768px){.brand{left:18px;top:18px}.back-btn{right:18px;top:25px}.single-wrap{margin:96px 12px 52px;padding:30px 20px;border-radius:6px}.post-header h1{font-size:28px}.post-deck{font-size:14.5px}.post-body{font-size:14.5px}.related-grid{grid-template-columns:1fr}}
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
<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="brand">
  <img src="<?php echo esc_url( get_stylesheet_directory_uri() . '/logo_svg.svg' ); ?>" width="32" height="32" alt="HoaSen Table logo" style="filter:drop-shadow(0 3px 10px rgba(var(--red-rgb),.22))"/>
  <div class="brand-name">HoaSen Table</div>
</a>
<a href="<?php echo esc_url( function_exists( 'hoasen_blog_url' ) ? hoasen_blog_url() : home_url( '/blog/' ) ); ?>" class="back-btn"><?php echo esc_html( $t['back_blog'] ); ?></a>
<?php if ( function_exists( 'pll_the_languages' ) ) : ?>
<div class="lang-sw"><ul><?php pll_the_languages( array( 'show_flags' => 0, 'show_names' => 1, 'hide_current' => 0 ) ); ?></ul></div>
<?php endif; ?>
<div class="single-wrap">
  <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    <?php
    $word_count = str_word_count( wp_strip_all_tags( get_the_content() ) );
    $read_time = max( 3, (int) ceil( $word_count / 220 ) );
    $categories = get_the_category();
    $tags = get_the_tags();
    $tag_ids = $tags ? wp_list_pluck( $tags, 'term_id' ) : array();
    $related_args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 3,
        'post__not_in' => array( get_the_ID() ),
        'ignore_sticky_posts' => true,
        'suppress_filters' => false,
    );
    if ( $tag_ids ) {
        $related_args['tag__in'] = $tag_ids;
    } elseif ( $categories ) {
        $related_args['category__in'] = wp_list_pluck( $categories, 'term_id' );
    }
    $related = new WP_Query( $related_args );
    if ( ! $related->have_posts() ) {
        $related = new WP_Query( array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 3,
            'post__not_in' => array( get_the_ID() ),
            'ignore_sticky_posts' => true,
            'suppress_filters' => false,
        ) );
    }
    ?>
    <?php if ( has_post_thumbnail() ) : ?>
      <div class="post-cover"><?php the_post_thumbnail( 'large' ); ?></div>
    <?php endif; ?>
    <header class="post-header">
      <div class="post-kicker"><?php echo esc_html( $categories ? $categories[0]->name : 'Engineering Notes' ); ?></div>
      <h1><?php the_title(); ?></h1>
      <?php if ( has_excerpt() ) : ?>
        <p class="post-deck"><?php echo esc_html( get_the_excerpt() ); ?></p>
      <?php endif; ?>
      <div class="meta">
        <span><?php echo esc_html( get_the_date() ); ?></span>
        <span>· <?php the_author(); ?></span>
        <span>· <?php echo esc_html( $read_time . ' ' . $t['minute'] ); ?></span>
      </div>
      <?php if ( $tags ) : ?>
        <div class="post-tags">
          <?php foreach ( $tags as $tag ) : ?>
            <a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>"><?php echo esc_html( $tag->name ); ?></a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </header>
    <main class="post-body">
      <?php the_content(); ?>
    </main>
    <?php if ( $related->have_posts() ) : ?>
      <aside class="related-wrap" aria-label="<?php echo esc_attr( $t['related'] ); ?>">
        <h2><?php echo esc_html( $t['related'] ); ?></h2>
        <div class="related-grid">
          <?php while ( $related->have_posts() ) : $related->the_post(); ?>
            <a class="related-card" href="<?php the_permalink(); ?>">
              <small><?php echo esc_html( $t['read_next'] ); ?></small>
              <strong><?php the_title(); ?></strong>
              <span><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18, '...' ) ); ?></span>
            </a>
          <?php endwhile; wp_reset_postdata(); ?>
        </div>
      </aside>
    <?php endif; ?>
  <?php endwhile; endif; ?>
</div>
<?php wp_footer(); ?>
<script src="https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js"></script>
<script>if (window.mermaid) { mermaid.initialize({ startOnLoad: true, theme: 'neutral' }); }</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.css">
<script src="https://cdn.jsdelivr.net/npm/katex@0.16/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16/dist/contrib/auto-render.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (window.renderMathInElement) {
    renderMathInElement(document.querySelector('.post-body'), {
      delimiters: [
        { left: '$$', right: '$$', display: true },
        { left: '$', right: '$', display: false }
      ],
      throwOnError: false
    });
  }
});
</script>
</body>
</html>
