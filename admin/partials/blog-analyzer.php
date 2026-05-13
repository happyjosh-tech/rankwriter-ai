<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $data */
$style    = (array) $data['style_profile'];
$last_run = (string) $data['last_run'];
$msg      = (string) $data['msg'];
?>
<div class="wrap rwai-wrap">
	<h1><?php esc_html_e( 'Blog Content Learning Engine', 'rankwriter-ai' ); ?></h1>
	<p class="rwai-lede"><?php esc_html_e( 'Studies your existing posts and builds a Blog Style Profile so new AI articles match what already works on this site.', 'rankwriter-ai' ); ?></p>

	<?php if ( 'analysis-done' === $msg ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Analysis complete. Blog Style Profile updated.', 'rankwriter-ai' ); ?></p></div>
	<?php endif; ?>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Run analyzer', 'rankwriter-ai' ); ?></h2>
		<p><?php
			/* translators: %s: last analysis timestamp */
			echo $last_run ? esc_html( sprintf( __( 'Last run: %s', 'rankwriter-ai' ), $last_run ) ) : esc_html__( 'Analyzer has never been run on this site.', 'rankwriter-ai' );
		?></p>

		<form method="post" id="rwai-analyze-form">
			<input type="hidden" name="rwai_action" value="run_analysis" />
			<?php wp_nonce_field( RankWriter_AI_Admin::ANALYZER_NONCE ); ?>
			<button type="submit" class="button button-primary" id="rwai-analyze-btn"><?php esc_html_e( 'Analyze my blog now', 'rankwriter-ai' ); ?></button>
			<button type="button" class="button button-secondary" id="rwai-deep-btn"><?php esc_html_e( 'Deep analyze with Claude', 'rankwriter-ai' ); ?></button>
			<span id="rwai-analyze-status" class="rwai-status"></span>
		</form>
		<p class="description"><?php esc_html_e( 'The deterministic analyzer reads up to N most recent posts (set in Settings) and inspects titles, categories, tags, tone heuristics, length, headings, keywords, internal links, meta descriptions, images, cadence, audience intent, content gaps, common topics, and monetization. The "Deep analyze with Claude" pass sends 8 random sample posts to Claude for a prose voice/tone analysis. Run the regular analyzer first.', 'rankwriter-ai' ); ?></p>
	</div>

	<?php if ( empty( $style ) ) : ?>
		<div class="rwai-card rwai-card-wide">
			<p><?php esc_html_e( 'No Blog Style Profile yet. Run the analyzer above to create one.', 'rankwriter-ai' ); ?></p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<div class="rwai-card rwai-card-wide">
		<h2><?php esc_html_e( 'Blog Style Profile', 'rankwriter-ai' ); ?></h2>
		<p><strong><?php esc_html_e( 'Summary:', 'rankwriter-ai' ); ?></strong> <?php echo esc_html( isset( $style['summary'] ) ? $style['summary'] : '' ); ?></p>

		<div class="rwai-grid rwai-grid-2">
			<div>
				<h3><?php esc_html_e( 'Style', 'rankwriter-ai' ); ?></h3>
				<dl class="rwai-dl">
					<dt><?php esc_html_e( 'Preferred tone', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( $style['preferred_tone'] ); ?></dd>
					<dt><?php esc_html_e( 'Common headline style', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( $style['common_headline_style'] ); ?></dd>
					<dt><?php esc_html_e( 'Average word count', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( RankWriter_AI_Helpers::format_number( $style['average_word_count'] ) ); ?></dd>
					<dt><?php esc_html_e( 'Median word count', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( RankWriter_AI_Helpers::format_number( $style['median_word_count'] ) ); ?></dd>
					<dt><?php esc_html_e( 'Preferred formatting', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( isset( $style['preferred_formatting']['description'] ) ? $style['preferred_formatting']['description'] : '' ); ?></dd>
				</dl>
			</div>
			<div>
				<h3><?php esc_html_e( 'Monetization', 'rankwriter-ai' ); ?></h3>
				<?php $m = isset( $style['monetization_patterns'] ) ? $style['monetization_patterns'] : array(); ?>
				<dl class="rwai-dl">
					<dt><?php esc_html_e( 'Dominant strategy', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( isset( $m['dominant_strategy'] ) ? $m['dominant_strategy'] : '' ); ?></dd>
					<dt><?php esc_html_e( 'AdSense code present', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( ( isset( $m['adsense_code_pct'] ) ? $m['adsense_code_pct'] : 0 ) . '%' ); ?></dd>
					<dt><?php esc_html_e( 'Affiliate links present', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( ( isset( $m['affiliate_link_pct'] ) ? $m['affiliate_link_pct'] : 0 ) . '%' ); ?></dd>
					<dt><?php esc_html_e( 'Amazon affiliate hits', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( ( isset( $m['amazon_link_pct'] ) ? $m['amazon_link_pct'] : 0 ) . '%' ); ?></dd>
					<dt><?php esc_html_e( 'CTA phrases', 'rankwriter-ai' ); ?></dt><dd><?php echo esc_html( ( isset( $m['cta_phrase_pct'] ) ? $m['cta_phrase_pct'] : 0 ) . '%' ); ?></dd>
				</dl>
			</div>
		</div>

		<h3><?php esc_html_e( 'Dominant categories', 'rankwriter-ai' ); ?></h3>
		<ul class="rwai-bullet-cols">
			<?php foreach ( (array) $style['dominant_categories'] as $c ) : ?>
				<li><?php echo esc_html( $c['name'] . ' (' . $c['count'] . ')' ); ?></li>
			<?php endforeach; ?>
		</ul>

		<h3><?php esc_html_e( 'Top recurring keywords', 'rankwriter-ai' ); ?></h3>
		<p class="rwai-tagcloud">
			<?php foreach ( (array) $style['top_keywords'] as $kw => $count ) : ?>
				<span class="rwai-tag"><?php echo esc_html( $kw ); ?> <em>(<?php echo esc_html( $count ); ?>)</em></span>
			<?php endforeach; ?>
		</p>

		<h3><?php esc_html_e( 'Common topics already covered', 'rankwriter-ai' ); ?></h3>
		<?php $topics = isset( $style['common_topics_covered'] ) ? $style['common_topics_covered'] : array(); ?>
		<p class="rwai-muted"><?php esc_html_e( 'Two- and three-word phrases recurring across your titles. Used to prevent duplicate coverage in new articles.', 'rankwriter-ai' ); ?></p>
		<p class="rwai-tagcloud">
			<?php foreach ( (array) ( isset( $topics['bigrams'] ) ? $topics['bigrams'] : array() ) as $phrase => $c ) : ?>
				<span class="rwai-tag"><?php echo esc_html( $phrase ); ?> <em>(<?php echo esc_html( $c ); ?>)</em></span>
			<?php endforeach; ?>
		</p>
		<?php if ( ! empty( $topics['trigrams'] ) ) : ?>
		<p class="rwai-tagcloud">
			<?php foreach ( $topics['trigrams'] as $phrase => $c ) : ?>
				<span class="rwai-tag rwai-tag-strong"><?php echo esc_html( $phrase ); ?> <em>(<?php echo esc_html( $c ); ?>)</em></span>
			<?php endforeach; ?>
		</p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Audience intent', 'rankwriter-ai' ); ?></h3>
		<?php $intent = isset( $style['audience_intent'] ) ? $style['audience_intent'] : array(); ?>
		<?php if ( ! empty( $intent['dominant'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Dominant intent:', 'rankwriter-ai' ); ?></strong> <?php echo esc_html( $intent['dominant'] ); ?></p>
			<dl class="rwai-dl">
				<?php foreach ( (array) ( isset( $intent['distribution_pct'] ) ? $intent['distribution_pct'] : array() ) as $k => $v ) : ?>
					<dt><?php echo esc_html( ucfirst( $k ) ); ?></dt>
					<dd><?php echo esc_html( $v . '% (' . ( isset( $intent['distribution_counts'][ $k ] ) ? $intent['distribution_counts'][ $k ] : 0 ) . ' posts)' ); ?></dd>
				<?php endforeach; ?>
			</dl>
		<?php else : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No intent signal yet.', 'rankwriter-ai' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $style['image_style_guess'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Detected image style:', 'rankwriter-ai' ); ?></strong> <?php echo esc_html( $style['image_style_guess'] ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Structural patterns', 'rankwriter-ai' ); ?></h3>
		<?php
		$sp = isset( $style['structural_patterns'] ) ? $style['structural_patterns'] : array();
		$pattern_labels = array(
			'faq_section'           => 'FAQ section',
			'comparison_table'      => 'Comparison tables',
			'numbered_steps'        => 'Numbered step lists',
			'salary_range_mentions' => 'Salary ranges',
			'price_mentions'        => 'Specific prices',
			'company_list'          => 'Company lists',
			'product_roundup'       => 'Product roundups',
			'emotional_hook_intro'  => 'Emotional hook intros',
			'stats_with_citation'   => 'Stats with %',
			'tl_dr_summary'         => 'TL;DR summaries',
			'cta_block'             => 'CTA blocks',
			'pros_cons_lists'       => 'Pros/Cons lists',
		);
		?>
		<?php if ( ! empty( $sp['percentages'] ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'How often each structural element appears across analyzed posts. Patterns above 30% are treated as required by the generator.', 'rankwriter-ai' ); ?></p>
			<table class="widefat striped" style="max-width:640px;">
				<thead><tr>
					<th><?php esc_html_e( 'Pattern', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Frequency', 'rankwriter-ai' ); ?></th>
					<th><?php esc_html_e( 'Required for new posts?', 'rankwriter-ai' ); ?></th>
				</tr></thead>
				<tbody>
					<?php foreach ( $pattern_labels as $key => $label ) :
						$p = isset( $sp['percentages'][ $key ] ) ? $sp['percentages'][ $key ] : 0;
						$req = $p >= 30;
					?>
						<tr>
							<td><?php echo esc_html( $label ); ?></td>
							<td><?php echo esc_html( $p . '%' ); ?></td>
							<td><?php echo $req ? '<span class="rwai-pill rwai-pill-ok">' . esc_html__( 'Yes', 'rankwriter-ai' ) . '</span>' : '<span class="rwai-pill rwai-pill-warn">' . esc_html__( 'No', 'rankwriter-ai' ) . '</span>'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No structural patterns detected yet.', 'rankwriter-ai' ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Headline pattern distribution', 'rankwriter-ai' ); ?></h3>
		<ul class="rwai-bullet-cols">
			<?php foreach ( (array) $style['headline_pattern_distribution'] as $name => $n ) : ?>
				<li><?php echo esc_html( $name . ' — ' . $n ); ?></li>
			<?php endforeach; ?>
		</ul>

		<h3><?php esc_html_e( 'Publishing cadence (last 12 months)', 'rankwriter-ai' ); ?></h3>
		<ul class="rwai-bullet-cols">
			<?php
			$cad = isset( $style['publishing_cadence'] ) ? $style['publishing_cadence'] : array();
			foreach ( $cad as $ym => $cnt ) {
				if ( '_per_month_avg' === $ym ) {
					continue;
				}
				echo '<li>' . esc_html( $ym . ' — ' . $cnt . ' posts' ) . '</li>';
			}
			?>
		</ul>
		<?php if ( ! empty( $cad['_per_month_avg'] ) ) : ?>
			<p><strong><?php esc_html_e( 'Average per month:', 'rankwriter-ai' ); ?></strong> <?php echo esc_html( $cad['_per_month_avg'] ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Internal linking opportunities', 'rankwriter-ai' ); ?></h3>
		<ul>
			<?php foreach ( (array) $style['internal_linking_opportunities'] as $opp ) : ?>
				<li>
					<?php if ( ! empty( $opp['post']['title'] ) ) : ?>
						<strong><?php echo esc_html( $opp['post']['title'] ); ?></strong> — <?php echo esc_html( $opp['reason'] ); ?>. <em><?php echo esc_html( $opp['action'] ); ?></em>
					<?php else : ?>
						<?php echo esc_html( $opp['reason'] . '. ' . $opp['action'] ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<h3><?php esc_html_e( 'SEO gaps', 'rankwriter-ai' ); ?></h3>
		<ul>
			<?php foreach ( (array) $style['seo_gaps'] as $gap ) : ?>
				<li><?php echo esc_html( $gap ); ?></li>
			<?php endforeach; ?>
		</ul>

		<h3><?php esc_html_e( 'Content expansion opportunities', 'rankwriter-ai' ); ?></h3>
		<ul>
			<?php foreach ( (array) $style['content_expansion_opportunities'] as $opp ) : ?>
				<li>
					<?php if ( ! empty( $opp['post']['title'] ) ) : ?>
						<strong><?php echo esc_html( $opp['post']['title'] ); ?></strong> — <?php echo esc_html( $opp['reason'] ); ?>. <em><?php echo esc_html( $opp['action'] ); ?></em>
					<?php else : ?>
						<?php echo esc_html( $opp['reason'] . '. ' . $opp['action'] ); ?>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<h3><?php esc_html_e( 'Content gaps', 'rankwriter-ai' ); ?></h3>
		<?php if ( empty( $style['content_gaps'] ) ) : ?>
			<p class="rwai-muted"><?php esc_html_e( 'No structural gaps detected.', 'rankwriter-ai' ); ?></p>
		<?php else : ?>
			<ul>
				<?php foreach ( (array) $style['content_gaps'] as $g ) : ?>
					<li>
						<span class="rwai-pill rwai-pill-warn"><?php echo esc_html( $g['type'] ); ?></span>
						<strong><?php echo esc_html( $g['gap'] ); ?></strong> — <em><?php echo esc_html( $g['suggestion'] ); ?></em>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Deep analysis (Claude)', 'rankwriter-ai' ); ?></h3>
		<?php if ( ! empty( $style['claude_deep_analysis'] ) ) : ?>
			<div class="rwai-claude-block"><?php echo esc_html( $style['claude_deep_analysis'] ); ?></div>
		<?php else : ?>
			<p class="rwai-muted"><?php esc_html_e( 'Click "Deep analyze with Claude" above to read 8 random posts and produce a prose voice / tone / weakness brief that will be injected into every new article.', 'rankwriter-ai' ); ?></p>
		<?php endif; ?>

		<?php if ( ! empty( $style['duplicate_topic_warnings'] ) ) : ?>
			<h3><?php esc_html_e( 'Duplicate topic warnings', 'rankwriter-ai' ); ?></h3>
			<ul>
				<?php foreach ( (array) $style['duplicate_topic_warnings'] as $dup ) : ?>
					<li>
						<?php
						$titles = array();
						foreach ( $dup['posts'] as $dp ) {
							$titles[] = $dp['title'];
						}
						echo esc_html( implode( ' / ', $titles ) );
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $style['top_performing_posts'] ) ) : ?>
			<h3><?php esc_html_e( 'Top-performing posts', 'rankwriter-ai' ); ?></h3>
			<ul>
				<?php foreach ( (array) $style['top_performing_posts'] as $tp ) : ?>
					<li>
						<strong><?php echo esc_html( $tp['title'] ); ?></strong>
						<?php if ( isset( $tp['views'] ) ) : ?> — <?php echo esc_html( $tp['views'] . ' views' ); ?><?php endif; ?>
						<?php if ( isset( $tp['comments'] ) ) : ?> — <?php echo esc_html( $tp['comments'] . ' comments' ); ?><?php endif; ?>
						<small class="rwai-muted"><?php echo esc_html( '(' . $tp['source'] . ')' ); ?></small>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( ! empty( $style['existing_post_titles'] ) ) : ?>
			<h3><?php esc_html_e( 'All analyzed post titles', 'rankwriter-ai' ); ?></h3>
			<details class="rwai-details">
				<summary><?php
					/* translators: %d: number of titles */
					echo esc_html( sprintf( _n( '%d post title', '%d post titles', count( $style['existing_post_titles'] ), 'rankwriter-ai' ), count( $style['existing_post_titles'] ) ) );
				?></summary>
				<ol class="rwai-titles-list">
					<?php foreach ( (array) $style['existing_post_titles'] as $pt ) : ?>
						<li>
							<a href="<?php echo esc_url( get_edit_post_link( $pt['id'] ) ); ?>"><?php echo esc_html( $pt['title'] ); ?></a>
							<small class="rwai-muted"><?php echo esc_html( $pt['date'] ); ?></small>
						</li>
					<?php endforeach; ?>
				</ol>
			</details>
		<?php endif; ?>
	</div>
</div>
