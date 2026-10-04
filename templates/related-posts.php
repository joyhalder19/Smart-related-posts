<?php
/** Shared grid/list template. All queries happen before this file. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="srp-related" style="<?php echo esc_attr( $styles ); ?>" <?php if ( '' !== $settings['heading'] ) : ?>aria-labelledby="<?php echo esc_attr( $section_id ); ?>"<?php else : ?>aria-label="<?php esc_attr_e( 'Related articles', 'smart-related-posts' ); ?>"<?php endif; ?>>
	<?php if ( '' !== $settings['heading'] ) : ?>
		<h2 class="srp-heading" id="<?php echo esc_attr( $section_id ); ?>"><?php echo esc_html( $settings['heading'] ); ?></h2>
	<?php endif; ?>
	<div class="srp-cards srp-cards--<?php echo esc_attr( $settings['layout'] ); ?>">
		<?php foreach ( $cards as $card ) : ?>
			<article class="srp-card">
				<a class="srp-link" href="<?php echo esc_url( $card['url'] ); ?>" aria-labelledby="<?php echo esc_attr( $section_id . '-' . $card['id'] ); ?>">
					<?php if ( $card['image'] ) : ?>
						<div class="srp-media"><?php echo wp_kses_post( $card['image'] ); ?></div>
					<?php endif; ?>
					<div class="srp-body">
						<h3 class="srp-title" id="<?php echo esc_attr( $section_id . '-' . $card['id'] ); ?>"><?php echo esc_html( $card['title'] ); ?></h3>
						<?php if ( $card['excerpt'] ) : ?><p class="srp-excerpt"><?php echo esc_html( $card['excerpt'] ); ?></p><?php endif; ?>
						<span class="srp-read" aria-hidden="true"><?php esc_html_e( 'Read article →', 'smart-related-posts' ); ?></span>
					</div>
				</a>
			</article>
		<?php endforeach; ?>
	</div>
</section>
