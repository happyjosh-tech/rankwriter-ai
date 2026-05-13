<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$upcoming = (array) ( $data['upcoming'] ?? array() );
$insights = (array) ( $data['insights'] ?? array() );
$calendar = (array) ( $data['calendar'] ?? array() );
$niches   = (array) ( $data['detected_niches'] ?? array() );
$year     = (int) ( $data['year'] ?? (int) date( 'Y' ) );
$niche_filter = isset( $_GET['niche'] ) ? sanitize_key( wp_unslash( $_GET['niche'] ) ) : '';
$msg      = (string) ( $data['msg'] ?? '' );

$niche_labels = array(
	'general'   => __( 'General', 'rankwriter-ai' ),
	'finance'   => __( 'Finance', 'rankwriter-ai' ),
	'education' => __( 'Education', 'rankwriter-ai' ),
	'retail'    => __( 'Retail', 'rankwriter-ai' ),
	'travel'    => __( 'Travel', 'rankwriter-ai' ),
	'food'      => __( 'Food', 'rankwriter-ai' ),
	'health'    => __( 'Health', 'rankwriter-ai' ),
	'parenting' => __( 'Parenting', 'rankwriter-ai' ),
	'tech'      => __( 'Tech', 'rankwriter-ai' ),
);

if ( ! function_exists( 'rwai_heat_band' ) ) {
	function rwai_heat_band( $heat ) {
		if ( $heat >= 80 ) return 'rwai-tl-bar-bad';   // hot = red ⇒ urgent
		if ( $heat >= 50 ) return 'rwai-tl-bar-warn';
		if ( $heat > 0 )   return 'rwai-tl-bar-ok';
		return '';
	}
}
?>
<div class="wrap rwai-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Seasonal Trend Engine', 'rankwriter-ai' ); ?></h1>
	<hr class="wp-header-end" />

	<p class="rwai-lede"><?php esc_html_e( 'Detects upcoming seasonal traffic spikes — Black Friday, scholarship season, tax season, back-to-school, holiday travel, and more. Each event gets a heat score, ideal publish window, and topic suggestions. Events you have no coverage for float to the top.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'seasonal-refreshed' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Coverage cache rebuilt.', 'rankwriter-ai' ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-cpc-summary-row">
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Upcoming events (next 120d)', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo (int) count( $upcoming ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Hot right now (heat ≥ 60)', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo (int) count( array_filter( $upcoming, function( $r ){ return $r['heat'] >= 60; } ) ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Coverage gaps', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value"><?php echo (int) ( $insights['gap_count'] ?? 0 ); ?></div>
		</div>
		<div class="rwai-cpc-summary-card">
			<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Detected niches', 'rankwriter-ai' ); ?></div>
			<div class="rwai-cpc-summary-value" style="font-size:13px;"><?php
				foreach ( $niches as $n ) {
					echo '<span class="rwai-pill rwai-pill-ok" style="margin-right:4px;">' . esc_html( $niche_labels[ $n ] ?? $n ) . '</span>';
				}
			?></div>
		</div>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Filter by niche', 'rankwriter-ai' ); ?>
			<form method="post" style="float:right;margin-top:-4px;">
				<input type="hidden" name="rwai_action" value="seasonal_refresh_coverage" />
				<?php wp_nonce_field( RankWriter_AI_Admin::SEASONAL_NONCE ); ?>
				<button type="submit" class="button button-small"><?php esc_html_e( '↻ Refresh coverage', 'rankwriter-ai' ); ?></button>
			</form>
		</h2>
		<p>
			<a class="button button-small <?php echo '' === $niche_filter ? 'button-primary' : ''; ?>" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SEASONAL_SLUG ) ); ?>"><?php esc_html_e( 'All', 'rankwriter-ai' ); ?></a>
			<?php foreach ( $niche_labels as $key => $label ) : ?>
				<a class="button button-small <?php echo $niche_filter === $key ? 'button-primary' : ''; ?>" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::SEASONAL_SLUG, array( 'niche' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</p>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Upcoming opportunities — prioritized', 'rankwriter-ai' ); ?></h2>
		<?php if ( empty( $upcoming ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No active or upcoming events in this niche window.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Event', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Date', 'rankwriter-ai' ); ?></th>
						<th style="width:160px;"><?php esc_html_e( 'Heat', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Publish by', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Your coverage', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Topic ideas', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $upcoming as $row ) :
					$ev   = $row['event'];
					$heat = (int) $row['heat'];
					$band = rwai_heat_band( $heat );
					$cov  = (int) ( $row['coverage']['count'] ?? 0 );
					$cov_pill = $cov === 0 ? 'rwai-pill-bad' : ( $cov < 3 ? 'rwai-pill-warn' : 'rwai-pill-ok' );
				?>
					<tr>
						<td>
							<strong><?php echo esc_html( $ev['name'] ); ?></strong>
							<?php if ( ! empty( $ev['niches'] ) ) : ?>
								<br><span class="rwai-muted"><?php echo esc_html( implode( ' · ', array_map( function( $n ) use ( $niche_labels ) { return $niche_labels[ $n ] ?? $n; }, $ev['niches'] ) ) ); ?></span>
							<?php endif; ?>
							<br><span class="rwai-muted"><?php echo esc_html( strtoupper( $ev['traffic'] ) ); ?> traffic</span>
						</td>
						<td>
							<?php echo esc_html( $row['next_human'] ); ?>
							<br><span class="rwai-muted"><?php echo esc_html( sprintf( _n( 'in %d day', 'in %d days', $row['days_until_event'], 'rankwriter-ai' ), $row['days_until_event'] ) ); ?></span>
						</td>
						<td>
							<div class="rwai-tl-bar-row">
								<div class="rwai-tl-bar-label"><?php echo esc_html( $heat ); ?></div>
								<div class="rwai-tl-bar-track"><span class="rwai-tl-bar-fill <?php echo esc_attr( $band ); ?>" style="width:<?php echo esc_attr( $heat ); ?>%"></span></div>
								<div class="rwai-tl-bar-score">/100</div>
							</div>
						</td>
						<td>
							<strong><?php echo esc_html( $row['window']['ideal_publish'] ); ?></strong>
							<br><span class="rwai-muted"><?php echo esc_html( sprintf( __( 'latest %s', 'rankwriter-ai' ), $row['window']['latest_publish'] ) ); ?></span>
						</td>
						<td>
							<span class="rwai-pill <?php echo esc_attr( $cov_pill ); ?>"><?php echo esc_html( sprintf( _n( '%d post', '%d posts', $cov, 'rankwriter-ai' ), $cov ) ); ?></span>
							<?php if ( ! empty( $row['coverage']['matched_posts'] ) ) : ?>
								<br>
								<?php foreach ( array_slice( $row['coverage']['matched_posts'], 0, 2 ) as $mp ) : ?>
									<a class="rwai-muted" href="<?php echo esc_url( get_edit_post_link( $mp['ID'] ) ); ?>"><?php echo esc_html( wp_trim_words( $mp['title'], 6 ) ); ?></a><br>
								<?php endforeach; ?>
							<?php endif; ?>
						</td>
						<td style="max-width:380px;">
							<?php foreach ( array_slice( $row['topic_suggestions'], 0, 3 ) as $seed ) : ?>
								<div style="margin-bottom:4px;">
									<a class="button button-small button-primary" href="<?php echo esc_url( RankWriter_AI_Helpers::admin_url( RankWriter_AI_Admin::GENERATE_SLUG, array( 'prefill_topic' => rawurlencode( $seed ) ) ) ); ?>"><?php esc_html_e( '✨ Generate', 'rankwriter-ai' ); ?></a>
									<span style="font-size:12px;"><?php echo esc_html( $seed ); ?></span>
								</div>
							<?php endforeach; ?>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<div class="rwai-card rwai-card-wide">
		<h2><?php echo esc_html( sprintf( __( '%d annual calendar', 'rankwriter-ai' ), $year ) ); ?></h2>
		<div class="rwai-seasonal-cal-grid">
			<?php foreach ( $calendar as $m_num => $month ) : ?>
				<div class="rwai-seasonal-month">
					<h4><?php echo esc_html( $month['name'] ); ?></h4>
					<?php if ( empty( $month['events'] ) ) : ?>
						<p class="rwai-muted" style="font-size:12px;"><?php esc_html_e( '—', 'rankwriter-ai' ); ?></p>
					<?php else : ?>
						<ul>
							<?php foreach ( $month['events'] as $e ) :
								$band = rwai_heat_band( $e['heat'] );
							?>
								<li>
									<?php if ( $band ) : ?>
										<span class="rwai-seasonal-dot <?php echo esc_attr( $band ); ?>"></span>
									<?php else : ?>
										<span class="rwai-seasonal-dot rwai-seasonal-dot-cold"></span>
									<?php endif; ?>
									<span class="rwai-seasonal-day"><?php echo esc_html( (int) $e['day'] ); ?></span>
									<span class="rwai-seasonal-name"><?php echo esc_html( $e['event']['name'] ); ?></span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="rwai-muted" style="margin-top:12px;font-size:12px;">
			<span class="rwai-seasonal-dot rwai-tl-bar-bad"></span> <?php esc_html_e( 'Hot — publish now', 'rankwriter-ai' ); ?> &nbsp;
			<span class="rwai-seasonal-dot rwai-tl-bar-warn"></span> <?php esc_html_e( 'Warming — start drafting', 'rankwriter-ai' ); ?> &nbsp;
			<span class="rwai-seasonal-dot rwai-tl-bar-ok"></span> <?php esc_html_e( 'On radar', 'rankwriter-ai' ); ?> &nbsp;
			<span class="rwai-seasonal-dot rwai-seasonal-dot-cold"></span> <?php esc_html_e( 'Off-season', 'rankwriter-ai' ); ?>
		</p>
	</div>
</div>
