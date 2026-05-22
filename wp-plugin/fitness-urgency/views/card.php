<?php
/**
 * Template for [fu_card]. Variables available: $renderer, $slots, $atts.
 * Override by copying to your-theme/fitness-urgency/card.php
 */
defined( 'ABSPATH' ) || exit;

$extra_class = sanitize_html_class( $atts['class'] ?? '' );
?>
<div class="fu-urgency <?php echo esc_attr( $extra_class ); ?>" data-fu-program="<?php echo esc_attr( $atts['program'] ?: ( FU_Programs::default()['slug'] ?? '' ) ); ?>">
  <?php foreach ( $slots as $i => $slot ) :
    $slot_num = $i + 1;
    $is_high_price = $slot['type'] === 'high_price';
    $is_sold_out   = $is_high_price ? false : ( $slot['spots']['sold_out'] ?? false );
    $card_classes  = array_filter( [
      'fu-card',
      $is_sold_out   ? 'fu-sold-out'  : '',
      $is_high_price ? 'fu-high-price' : '',
    ] );
  ?>
  <div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>" data-fu-slot="<?php echo $slot_num; ?>">
    <span class="fu-card-date">
      <?php if ( $is_high_price ) :
        echo esc_html( $renderer->high_price_message );
      else :
        echo esc_html( $renderer->format_date( $slot['date'] ) );
      endif; ?>
    </span>
    <span class="fu-card-spots">
      <?php if ( $is_high_price ) :
        echo esc_html( $renderer->spots_label( $renderer->high_price_spots ) );
      elseif ( $is_sold_out ) :
        echo esc_html( 'SOLD OUT' );
      else :
        echo esc_html( $renderer->spots_label( $slot['spots']['count'] ) );
      endif; ?>
    </span>
  </div>
  <?php endforeach; ?>
</div>
