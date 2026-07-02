<?php
defined( 'ABSPATH' ) || exit;
$theme   = esc_attr( $attributes['theme']   ?? 'dark' );
$columns = max( 1, min( 6, (int) ( $attributes['columns'] ?? 4 ) ) );
$stats   = is_array( $attributes['stats'] ?? null ) ? $attributes['stats'] : [];
?>
<section class="stmb-stats stmb-stats--<?php echo $theme; ?>">
  <div class="stmb-stats__inner stmb-stats--<?php echo $columns; ?>col">
    <?php foreach ( $stats as $stat ) :
      $val    = (int) ( $stat['value']  ?? 0 );
      $suffix = esc_html( $stat['suffix'] ?? '' );
      $label  = esc_html( $stat['label']  ?? '' );
    ?>
    <div class="stmb-stat-card">
      <div class="stmb-stat-card__value" data-counter="<?php echo $val; ?>" data-suffix="<?php echo esc_attr( $suffix ); ?>">
        <?php echo $val . $suffix; ?>
      </div>
      <div class="stmb-stat-card__label"><?php echo $label; ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php
