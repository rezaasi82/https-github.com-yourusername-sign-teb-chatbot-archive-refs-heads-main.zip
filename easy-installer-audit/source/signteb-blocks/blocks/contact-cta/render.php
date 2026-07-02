<?php
defined('ABSPATH') || exit;
$title    = esc_html($attributes['title']    ?? __('با ما تماس بگیرید','signteb-blocks'));
$subtitle = esc_html($attributes['subtitle'] ?? '');
$phone    = esc_html($attributes['phone']    ?? get_option('stmc_clinic_phone',''));
$wa       = esc_attr($attributes['whatsapp']  ?? get_option('stmc_clinic_whatsapp',''));
$email    = esc_html($attributes['email']    ?? get_option('stmc_clinic_email',''));
$theme    = esc_attr($attributes['theme']    ?? 'dark');
$wa_url   = $wa ? 'https://wa.me/'.preg_replace('/[^0-9]/','', $wa) : '';
?>
<section class="stmb-contact-cta stmb-contact-cta--<?php echo $theme; ?>">
  <div class="stmb-contact-cta__inner">
    <div class="stmb-contact-cta__text">
      <h2 class="stmb-contact-cta__title"><?php echo $title; ?></h2>
      <?php if ($subtitle) : ?><p class="stmb-contact-cta__subtitle"><?php echo $subtitle; ?></p><?php endif; ?>
    </div>
    <div class="stmb-contact-cta__btns">
      <?php if ($phone) : ?>
        <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/','', $phone)); ?>" class="stmb-cta-btn stmb-cta-btn--phone">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.64A2 2 0 012 .18h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z"/></svg>
          <?php echo $phone; ?>
        </a>
      <?php endif; ?>
      <?php if ($wa_url) : ?>
        <a href="<?php echo esc_url($wa_url); ?>" class="stmb-cta-btn stmb-cta-btn--wa" target="_blank" rel="noopener noreferrer">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413z"/></svg>
          WhatsApp
        </a>
      <?php endif; ?>
      <?php if ($email) : ?>
        <a href="mailto:<?php echo esc_attr($email); ?>" class="stmb-cta-btn stmb-cta-btn--email">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          <?php echo $email; ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php
