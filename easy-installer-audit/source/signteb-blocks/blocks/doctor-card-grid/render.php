<?php
defined( 'ABSPATH' ) || exit;
$cols     = max(1,min(4,(int)($attributes['columns'] ?? 3)));
$per_page = (int)($attributes['perPage']   ?? 6);
$sp_slug  = sanitize_key($attributes['specialtySlug'] ?? '');
$loc_slug = sanitize_key($attributes['locationSlug']  ?? '');
$title    = esc_html($attributes['title'] ?? '');
$show_filter = (bool)($attributes['showFilter'] ?? false);

$q_args = [
  'post_type'      => 'doctor',
  'posts_per_page' => $per_page,
  'post_status'    => 'publish',
  'orderby'        => 'menu_order',
  'order'          => 'ASC',
];

$tax_query = [];
if ($sp_slug)  $tax_query[] = ['taxonomy'=>'specialty','field'=>'slug','terms'=>$sp_slug];
if ($loc_slug) $tax_query[] = ['taxonomy'=>'location','field'=>'slug','terms'=>$loc_slug];
if (!empty($tax_query)) $q_args['tax_query'] = $tax_query;

$doctors = new WP_Query($q_args);
if ( ! $doctors->have_posts()) return;
?>
<section class="stmb-doctors-section">
  <?php if ($title) : ?>
  <div class="stmb-section-header"><h2 class="stmb-section-title"><?php echo $title; ?></h2></div>
  <?php endif; ?>

  <div class="stmb-doctors-grid stmb-doctors-grid--<?php echo $cols; ?>col">
    <?php while ($doctors->have_posts()) : $doctors->the_post();
      $pid      = get_the_ID();
      $name     = get_the_title();
      $url      = get_permalink();
      $specialty= get_post_meta($pid,'stmc_doctor_specialty',true);
      $exp      = (int)get_post_meta($pid,'stmc_doctor_experience_yrs',true);
      $patients = (int)get_post_meta($pid,'stmc_doctor_patients_count',true);
      $wa       = get_post_meta($pid,'stmc_doctor_whatsapp',true);
      $img      = get_the_post_thumbnail_url($pid,'stmc-doctor-card');
      $terms    = get_the_terms($pid,'specialty');
      $wa_url   = $wa ? 'https://wa.me/'.preg_replace('/[^0-9]/','',   $wa) : '';
    ?>
    <article class="stmb-doctor-card" itemscope itemtype="https://schema.org/Physician">
      <a href="<?php echo esc_url($url); ?>" class="stmb-doctor-card__img-wrap" tabindex="-1">
        <?php if ($img) : ?>
          <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($name); ?>" class="stmb-doctor-card__img" loading="lazy" decoding="async" width="400" height="450" itemprop="image">
        <?php else : ?>
          <div class="stmb-doctor-card__img-placeholder" aria-hidden="true">👨‍⚕️</div>
        <?php endif; ?>
      </a>
      <div class="stmb-doctor-card__body">
        <?php if ($terms && !is_wp_error($terms)) : ?>
          <a href="<?php echo esc_url(get_term_link($terms[0])); ?>" class="stmb-badge stmb-badge--specialty"><?php echo esc_html($terms[0]->name); ?></a>
        <?php endif; ?>
        <h3 class="stmb-doctor-card__name" itemprop="name"><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($name); ?></a></h3>
        <?php if ($specialty) : ?><p class="stmb-doctor-card__specialty" itemprop="medicalSpecialty"><?php echo esc_html($specialty); ?></p><?php endif; ?>
        <?php if ($exp || $patients) : ?>
        <div class="stmb-doctor-card__stats">
          <?php if ($exp) : ?><div class="stmb-doctor-card__stat"><b><?php echo $exp; ?>+</b><span><?php esc_html_e('سال تجربه','signteb-blocks'); ?></span></div><?php endif; ?>
          <?php if ($patients) : ?><div class="stmb-doctor-card__stat"><b><?php echo number_format($patients/1000,0).'K+'; ?></b><span><?php esc_html_e('بیمار','signteb-blocks'); ?></span></div><?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="stmb-doctor-card__actions">
          <a href="<?php echo esc_url($url); ?>" class="stmb-btn stmb-btn--primary stmb-btn--sm"><?php esc_html_e('مشاهده پروفایل','signteb-blocks'); ?></a>
          <?php if ($wa_url) : ?><a href="<?php echo esc_url($wa_url); ?>" class="stmb-btn stmb-btn--wa stmb-btn--sm" target="_blank" rel="noopener noreferrer">WhatsApp</a><?php endif; ?>
        </div>
      </div>
      <meta itemprop="url" content="<?php echo esc_url($url); ?>">
    </article>
    <?php endwhile; wp_reset_postdata(); ?>
  </div>
</section>
<?php
