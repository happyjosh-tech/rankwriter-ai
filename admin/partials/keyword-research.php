<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$result   = isset( $data['result'] ) ? (array) $data['result'] : array();
$last_seed = isset( $data['seed'] ) ? (string) $data['seed'] : '';
$last_country = isset( $data['country'] ) ? (string) $data['country'] : 'US';
$msg      = (string) $data['msg'];
$err      = (string) $data['err'];
$pool     = (array) $data['pool'];
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'Keyword & Title Research', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'Pulls fresh, current keywords and competitor titles from Google Suggest, Google Trends, your competitor blogs (RSS), and optionally SerpAPI / DataForSEO. These signals are injected into every Claude generation so articles target what people search TODAY — not training-data memory.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'research-error' === $msg ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $err ?: __( 'Research failed.', 'rankwriter-ai' ) ); ?></p></div>
	<?php endif; ?>

	<form method="post" class="rwai-form">
		<input type="hidden" name="rwai_action" value="run_keyword_research" />
		<?php wp_nonce_field( RankWriter_AI_Admin::RESEARCH_NONCE ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="rwai_seed"><?php esc_html_e( 'Seed keyword or topic', 'rankwriter-ai' ); ?></label></th>
				<td><input type="text" class="regular-text" id="rwai_seed" name="seed" value="<?php echo esc_attr( $last_seed ); ?>" required placeholder="<?php esc_attr_e( 'e.g. agriculture grants', 'rankwriter-ai' ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="rwai_country"><?php esc_html_e( 'Country (ISO 2-letter)', 'rankwriter-ai' ); ?></label></th>
				<td><input type="text" class="small-text" id="rwai_country" name="country" maxlength="2" value="<?php echo esc_attr( strtoupper( $last_country ) ); ?>" /></td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Discover fresh keywords', 'rankwriter-ai' ); ?></button>
		</p>
	</form>

	<?php if ( ! empty( $result ) ) : ?>
		<div class="rwai-card rwai-card-wide">
			<h2><?php esc_html_e( 'Live results', 'rankwriter-ai' ); ?>
				<small class="rwai-muted"><?php echo esc_html( $result['fetched_at'] ); ?></small>
			</h2>

			<h3><?php esc_html_e( 'Ranked keyword pool (used by the generator)', 'rankwriter-ai' ); ?></h3>

			<?php if ( ! empty( $result['intent_counts'] ) ) : ?>
				<p class="rwai-muted" style="margin-bottom:8px;">
					<strong><?php esc_html_e( 'Intent mix:', 'rankwriter-ai' ); ?></strong>
					<?php
					$intent_labels = array(
						'informational' => __( 'Informational', 'rankwriter-ai' ),
						'commercial'    => __( 'Commercial', 'rankwriter-ai' ),
						'transactional' => __( 'Transactional', 'rankwriter-ai' ),
						'navigational'  => __( 'Navigational', 'rankwriter-ai' ),
					);
					$intent_pieces = array();
					foreach ( $result['intent_counts'] as $intent_key => $count ) {
						if ( $count <= 0 ) { continue; }
						$label = isset( $intent_labels[ $intent_key ] ) ? $intent_labels[ $intent_key ] : $intent_key;
						$intent_pieces[] = '<span class="rwai-intent-badge rwai-intent-' . esc_attr( $intent_key ) . '">' . esc_html( $label ) . ' ' . (int) $count . '</span>';
					}
					echo wp_kses_post( implode( ' ', $intent_pieces ) );
					?>
				</p>
			<?php endif; ?>

			<?php $cpc_sum = isset( $result['cpc_summary'] ) ? $result['cpc_summary'] : array(); ?>
			<?php if ( ! empty( $cpc_sum['count'] ) ) : ?>
				<div class="rwai-cpc-summary-row">
					<div class="rwai-cpc-summary-card">
						<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Avg estimated CPC', 'rankwriter-ai' ); ?></div>
						<div class="rwai-cpc-summary-value">$<?php echo esc_html( number_format( (float) $cpc_sum['avg_cpc'], 2 ) ); ?></div>
					</div>
					<div class="rwai-cpc-summary-card">
						<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Top CPC in pool', 'rankwriter-ai' ); ?></div>
						<div class="rwai-cpc-summary-value">$<?php echo esc_html( number_format( (float) $cpc_sum['max_cpc'], 2 ) ); ?></div>
					</div>
					<div class="rwai-cpc-summary-card">
						<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Predicted RPM', 'rankwriter-ai' ); ?></div>
						<div class="rwai-cpc-summary-value">$<?php echo esc_html( number_format( (float) $cpc_sum['avg_rpm'], 0 ) ); ?></div>
					</div>
					<div class="rwai-cpc-summary-card">
						<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Monetization score', 'rankwriter-ai' ); ?></div>
						<div class="rwai-cpc-summary-value"><?php echo esc_html( (int) $cpc_sum['avg_score'] ); ?>/100</div>
					</div>
					<div class="rwai-cpc-summary-card">
						<div class="rwai-cpc-summary-label"><?php esc_html_e( 'Dominant tier', 'rankwriter-ai' ); ?></div>
						<div class="rwai-cpc-summary-value">
							<span class="rwai-cpc-badge rwai-cpc-<?php echo esc_attr( $cpc_sum['dominant_tier'] ); ?>"><?php echo esc_html( RankWriter_AI_CPC_Scorer::tier_label( $cpc_sum['dominant_tier'] ) ); ?></span>
						</div>
					</div>
				</div>
			<?php endif; ?>

			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:36%;"><?php esc_html_e( 'Keyword', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Intent', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'CPC tier', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Est. CPC', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'RPM', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Mon. score', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Competition', 'rankwriter-ai' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( array_slice( $result['merged_seed_pool'], 0, 30 ) as $kw ) :
					$intent       = isset( $kw['intent'] ) ? $kw['intent'] : 'informational';
					$intent_label = isset( $kw['intent_label'] ) ? $kw['intent_label'] : ucfirst( $intent );
					$cpc          = isset( $kw['cpc'] ) ? (float) $kw['cpc'] : 0;
					$tier         = isset( $kw['cpc_tier'] ) ? $kw['cpc_tier'] : 'low';
					$tier_label   = isset( $kw['cpc_tier_label'] ) ? $kw['cpc_tier_label'] : 'Low CPC';
					$rpm          = isset( $kw['rpm'] ) ? (float) $kw['rpm'] : 0;
					$mscore       = isset( $kw['monetization_score'] ) ? (int) $kw['monetization_score'] : 0;
					$comp         = isset( $kw['competition'] ) ? $kw['competition'] : 'medium';
					$priority     = ! empty( $kw['priority_niche'] );
					?>
					<tr>
						<td>
							<strong><?php echo esc_html( $kw['keyword'] ); ?></strong>
							<?php if ( $priority ) : ?> <span class="rwai-priority-star" title="<?php esc_attr_e( 'Priority high-value niche', 'rankwriter-ai' ); ?>">★</span><?php endif; ?>
							<?php if ( ! empty( $kw['used_real_cpc'] ) ) : ?> <small class="rwai-muted"><?php esc_html_e( '(real data)', 'rankwriter-ai' ); ?></small><?php endif; ?>
						</td>
						<td><span class="rwai-intent-badge rwai-intent-<?php echo esc_attr( $intent ); ?>"><?php echo esc_html( $intent_label ); ?></span></td>
						<td><span class="rwai-cpc-badge rwai-cpc-<?php echo esc_attr( $tier ); ?>"><?php echo esc_html( $tier_label ); ?></span></td>
						<td>$<?php echo esc_html( number_format( $cpc, 2 ) ); ?></td>
						<td>$<?php echo esc_html( number_format( $rpm, 0 ) ); ?></td>
						<td><?php echo esc_html( $mscore ); ?></td>
						<td><span class="rwai-pill rwai-comp-<?php echo esc_attr( $comp ); ?>"><?php echo esc_html( ucfirst( $comp ) ); ?></span></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<div class="rwai-grid rwai-grid-2">
				<div>
					<h3><?php esc_html_e( 'Google Suggest autocomplete', 'rankwriter-ai' ); ?></h3>
					<ul class="rwai-bullet-cols">
						<?php foreach ( (array) $result['suggest_keywords'] as $kw ) : ?>
							<li><?php echo esc_html( $kw ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div>
					<h3><?php esc_html_e( 'Trending in country (Google Trends)', 'rankwriter-ai' ); ?></h3>
					<ul class="rwai-bullet-cols">
						<?php foreach ( (array) $result['trending_topics'] as $t ) : ?>
							<li><?php echo esc_html( $t['title'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<?php if ( ! empty( $result['competitor_titles'] ) ) : ?>
				<h3><?php esc_html_e( 'Recent posts on competitor blogs you configured', 'rankwriter-ai' ); ?></h3>
				<ul>
					<?php foreach ( $result['competitor_titles'] as $c ) : ?>
						<li>
							<a href="<?php echo esc_url( $c['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $c['title'] ); ?></a>
							<small class="rwai-muted"><?php echo esc_html( $c['source'] . ' · ' . $c['date'] ); ?></small>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $result['serpapi_related']['related_questions'] ) ) : ?>
				<h3><?php esc_html_e( 'People Also Ask (SerpAPI)', 'rankwriter-ai' ); ?></h3>
				<ul>
					<?php foreach ( $result['serpapi_related']['related_questions'] as $q ) : ?>
						<li><?php echo esc_html( $q ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $result['serpapi_related']['organic_titles'] ) ) : ?>
				<h3><?php esc_html_e( 'Top-ranking organic titles (SerpAPI)', 'rankwriter-ai' ); ?></h3>
				<ul>
					<?php foreach ( $result['serpapi_related']['organic_titles'] as $r ) : ?>
						<li><a href="<?php echo esc_url( $r['link'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $r['title'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( ! empty( $result['dataforseo_volume'] ) ) : ?>
				<h3><?php esc_html_e( 'Search volume (DataForSEO)', 'rankwriter-ai' ); ?></h3>
				<table class="widefat striped">
					<thead><tr>
						<th><?php esc_html_e( 'Keyword', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Volume', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'Competition', 'rankwriter-ai' ); ?></th>
						<th><?php esc_html_e( 'CPC', 'rankwriter-ai' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $result['dataforseo_volume'] as $r ) : ?>
							<tr>
								<td><?php echo esc_html( $r['keyword'] ); ?></td>
								<td><?php echo esc_html( RankWriter_AI_Helpers::format_number( $r['search_volume'] ) ); ?></td>
								<td><?php echo esc_html( $r['competition'] ); ?></td>
								<td><?php echo esc_html( '$' . number_format( $r['cpc'], 2 ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $pool ) ) : ?>
		<div class="rwai-card rwai-card-wide">
			<h2><?php esc_html_e( 'Recent research history', 'rankwriter-ai' ); ?></h2>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Seed', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Country', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Top keywords', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'When', 'rankwriter-ai' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( array_slice( $pool, 0, 15 ) as $row ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $row['seed'] ); ?></strong></td>
							<td><?php echo esc_html( $row['country'] ); ?></td>
							<td><?php
								$tops = array();
								foreach ( array_slice( $row['keywords'], 0, 6 ) as $k ) {
									$tops[] = $k['keyword'];
								}
								echo esc_html( implode( ', ', $tops ) );
							?></td>
							<td><small><?php echo esc_html( $row['fetched_at'] ); ?></small></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
