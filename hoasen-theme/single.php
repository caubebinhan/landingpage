<?php
$lang = function_exists( 'pll_current_language' ) ? pll_current_language() : 'en';
$is_vi = strpos( $lang, 'vi' ) === 0;
$is_ja = strpos( $lang, 'ja' ) === 0;
$t = array(
    'back_blog' => $is_vi ? '← Về Blog' : ( $is_ja ? '← ブログへ戻る' : '← Back to Blog' ),
    'related'   => $is_vi ? 'Bài liên quan' : ( $is_ja ? '関連記事' : 'Related articles' ),
    'read_next' => $is_vi ? 'Đọc tiếp' : ( $is_ja ? '次に読む' : 'Read next' ),
    'minute'    => $is_vi ? 'phút đọc' : ( $is_ja ? '分で読めます' : 'min read' ),
);
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( get_stylesheet_directory_uri() . '/logo_svg.svg' ); ?>"/>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0"/>
<title><?php the_title(); ?> — HoaSen Table Journal</title>
<?php wp_head(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=Outfit:wght@400;500;600;700;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{--bg:#f0efed;--red-rgb:190,24,74;--red2-rgb:219,39,119;--red:rgb(var(--red-rgb));--red2:rgb(var(--red2-rgb));--text:#0f0f0f;--rs:rgba(var(--red-rgb),.07)}
*{box-sizing:border-box;margin:0;padding:0}
body{background:var(--bg);color:var(--text);font-family:"Outfit",sans-serif;overflow-x:hidden}
body::before{content:"";position:fixed;inset:0;pointer-events:none;z-index:-1;background:radial-gradient(circle at 14% 13%,rgba(var(--red-rgb),.042),transparent 31%),linear-gradient(168deg,#f3f2ef,#e9e6e0 55%,#eee8e1)}
.bg-lotus{position:fixed;top:50%;left:65%;transform:translate(-50%,-50%);width:max(90vh,600px);height:max(90vh,600px);z-index:0;opacity:.035;pointer-events:none;background-image:url('<?php echo esc_url( get_stylesheet_directory_uri() . '/logo.png' ); ?>');background-size:contain;background-repeat:no-repeat;background-position:center}
.dot-grid{position:absolute;inset:0;opacity:.13;pointer-events:none;background-image:radial-gradient(circle,rgba(0,0,0,.16) 1px,transparent 1px);background-size:42px 42px;mask-image:radial-gradient(ellipse at 50% 10%,black,transparent 70%)}
.brand{position:absolute;top:22px;left:32px;z-index:40;display:flex;align-items:center;gap:10px;text-decoration:none}
.brand-name{font-family:"Cormorant Garamond",serif;font-size:21px;font-weight:700;color:var(--text);letter-spacing:-.025em}
.back-btn{position:absolute;top:28px;right:32px;z-index:40;font-size:11px;font-weight:700;color:#6b7280;text-decoration:none;transition:color .15s}
.back-btn:hover{color:var(--red)}
.single-wrap{position:relative;z-index:5;max-width:820px;margin:120px auto 80px;padding:50px;background:#fff;border-radius:16px;border:1px solid rgba(0,0,0,.08);box-shadow:0 32px 80px rgba(0,0,0,.06)}
.post-header{margin-bottom:38px;border-bottom:1px solid rgba(0,0,0,.08);padding-bottom:32px}
.post-kicker{font-size:10px;font-weight:900;letter-spacing:.18em;text-transform:uppercase;color:var(--red);margin-bottom:14px}
.post-header h1{font-family:"Cormorant Garamond",serif;font-size:clamp(32px,5vw,48px);line-height:1.12;font-weight:700;color:#0f0f0f;margin-bottom:16px;letter-spacing:-.025em;text-wrap:balance}
.post-deck{font-size:18px;line-height:1.65;color:#475569;max-width:700px;margin:18px 0;text-wrap:pretty}
.meta{display:flex;flex-wrap:wrap;gap:10px;align-items:center;font-size:11px;color:#6b7280;font-family:"Outfit",sans-serif;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.post-tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}
.post-tags a{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;background:rgba(var(--red-rgb),.07);color:var(--red);text-decoration:none;font:700 10px "Outfit",sans-serif;letter-spacing:.03em}
.post-body{font-family:"Cormorant Garamond",serif;font-size:19px;line-height:1.82;color:#2d3748}
.post-body p{margin-bottom:24px;text-wrap:pretty}
.post-body code{font-family:"JetBrains Mono",monospace;font-size:14px;background:rgba(0,0,0,.05);padding:2px 6px;border-radius:4px;color:var(--red2)}
.post-body h2,.post-body h3{font-family:"Cormorant Garamond",serif;font-weight:700;margin:38px 0 16px;color:#111;letter-spacing:-.01em}
.post-body h2{font-size:31px}.post-body h3{font-size:24px}
.post-body blockquote{border-left:2px solid rgba(var(--red-rgb),.4);padding-left:20px;margin:28px 0;font-style:italic;color:#4b5563;font-size:21px}
.post-body ul,.post-body ol{margin:20px 0 24px 24px;font-size:17px;color:#374151;font-family:"Outfit",sans-serif;line-height:1.65}
.post-body li{margin-bottom:8px}
.post-body pre{background:#f7f6f3;padding:20px;border-radius:10px;overflow-x:auto;margin:28px 0;border:1px solid rgba(0,0,0,.05)}
.post-body pre code{background:transparent;padding:0;color:#1f2937;font-size:13px}
.related-wrap{margin-top:46px;padding-top:30px;border-top:1px solid rgba(0,0,0,.08)}
.related-wrap h2{font-family:"Cormorant Garamond",serif;font-size:30px;letter-spacing:-.02em;margin-bottom:18px}
.related-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.related-card{display:block;text-decoration:none;color:inherit;padding:18px;border:1px solid rgba(0,0,0,.08);border-radius:12px;background:#fafaf8;transition:transform .18s,border-color .18s,background .18s}
.related-card:hover{transform:translateY(-2px);border-color:rgba(var(--red-rgb),.22);background:#fff}
.related-card small{display:block;font:800 10px "Outfit",sans-serif;letter-spacing:.12em;text-transform:uppercase;color:var(--red);margin-bottom:8px}
.related-card strong{display:block;font-family:"Cormorant Garamond",serif;font-size:20px;line-height:1.2;margin-bottom:8px}
.related-card span{display:block;font-size:12px;line-height:1.5;color:#64748b}
@media(max-width:768px){.brand{left:18px;top:18px}.back-btn{right:18px;top:25px}.single-wrap{margin:96px 12px 52px;padding:30px 20px;border-radius:12px}.post-header h1{font-size:34px}.post-deck{font-size:16px}.post-body{font-size:18px}.related-grid{grid-template-columns:1fr}}
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
</body>
</html>
