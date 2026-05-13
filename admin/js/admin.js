(function ($) {
	'use strict';

	$(function () {

		// ====================== WordPress category picker ======================
		// Reveal / hide the "new category name" input as the dropdown changes.

		$(document).on('change', '[data-rwai-picker-select]', function () {
			var $select = $(this);
			var $wrap = $select.closest('[data-rwai-picker]');
			var $new = $wrap.find('[data-rwai-picker-new]');
			if ($select.val() === '__new__') {
				$new.show();
				$new.find('input').first().focus();
			} else {
				$new.hide();
			}
		});

		// ====================== Viral Title Intelligence ======================

		var STYLE_LABELS = {
			seo:       'SEO',
			viral:     'Viral',
			discover:  'Google Discover',
			pinterest: 'Pinterest',
			social:    'Social media'
		};

		function tlBand(score) {
			if (score >= 75) return 'ok';
			if (score >= 50) return 'warn';
			return 'bad';
		}

		function tlRenderVariant(analysis, postId) {
			var bars = '';
			Object.keys(STYLE_LABELS).forEach(function (s) {
				var sc = (analysis.platform_scores && analysis.platform_scores[s]) || 0;
				bars += '<div class="rwai-tl-bar-row">' +
					'<span class="rwai-tl-bar-label">' + STYLE_LABELS[s] + '</span>' +
					'<span class="rwai-tl-bar-track"><span class="rwai-tl-bar-fill rwai-tl-bar-' + tlBand(sc) + '" style="width:' + sc + '%"></span></span>' +
					'<span class="rwai-tl-bar-score">' + sc + '</span>' +
					'</div>';
			});

			var triggers = (analysis.emotional_triggers || []).map(function (t) {
				return '<span class="rwai-tl-tag rwai-tl-tag-trigger">' + t + '</span>';
			}).join('');
			var powers = (analysis.power_words || []).slice(0, 6).map(function (p) {
				return '<span class="rwai-tl-tag rwai-tl-tag-power">' + p.word + '</span>';
			}).join('');
			var clickbait = '';
			if (analysis.clickbait && analysis.clickbait.is_clickbait) {
				clickbait = '<span class="rwai-tl-tag rwai-tl-tag-bad">⚠️ clickbait</span>';
			}

			var overall = analysis.overall_score || 0;
			var swap = '';
			if (postId) {
				swap = '<button type="button" class="button button-small rwai-tl-use-title" data-post-id="' + postId + '" data-title="' + $('<div>').text(analysis.title).html() + '">' + 'Use this title' + '</button>';
			}

			return '<div class="rwai-tl-variant">' +
				'<div class="rwai-tl-variant-head">' +
					'<div class="rwai-tl-variant-title">' + $('<div>').text(analysis.title).html() + '</div>' +
					'<div class="rwai-tl-variant-meta">' +
						'<span class="rwai-tl-meta-item">' + analysis.length + ' chars</span>' +
						'<span class="rwai-tl-overall rwai-tl-overall-' + tlBand(overall) + '">' + overall + '/100</span>' +
					'</div>' +
				'</div>' +
				'<div class="rwai-tl-variant-bars">' + bars + '</div>' +
				'<div class="rwai-tl-variant-tags">' + triggers + powers + clickbait + '</div>' +
				(swap ? '<p class="rwai-tl-actions">' + swap + '</p>' : '') +
				'</div>';
		}

		function tlRenderResults($container, variantsByStyle, postId) {
			var html = '';
			Object.keys(STYLE_LABELS).forEach(function (s) {
				var rows = (variantsByStyle && variantsByStyle[s]) || [];
				if (!rows.length) return;
				html += '<div class="rwai-tl-style-block">';
				html += '<h3>' + STYLE_LABELS[s] + ' <small class="rwai-muted">(' + rows.length + ' variants)</small></h3>';
				rows.forEach(function (analysis) {
					html += tlRenderVariant(analysis, postId);
				});
				html += '</div>';
			});
			$container.html(html || '<p class="rwai-muted">No variants returned.</p>');
		}

		// ----- Title Lab: generate -----
		$('#rwai-tl-generate').on('click', function () {
			var topic = $.trim($('#rwai_tl_topic').val() || '');
			if (!topic) { window.alert('Enter a topic first.'); return; }
			var intent = $('#rwai_tl_intent').val() || '';
			var $btn = $(this);
			var $status = $('#rwai-tl-generate-status');
			var $out = $('#rwai-tl-results');
			$btn.prop('disabled', true);
			$status.removeClass('is-ok is-error').text(RWAI.i18n.titleGen);
			$out.html('<p class="rwai-muted">' + RWAI.i18n.titleGen + '</p>');

			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: { action: 'rwai_title_generate', nonce: RWAI.titleNonce, topic: topic, intent: intent, count: 3 },
				dataType: 'json', timeout: 120000
			}).done(function (res) {
				if (res && res.success && res.data && res.data.variants) {
					$status.addClass('is-ok').text('Done.');
					tlRenderResults($out, res.data.variants, 0);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : RWAI.i18n.titleFail;
					$status.addClass('is-error').text(msg);
					$out.html('<p class="rwai-pill rwai-pill-bad">' + msg + '</p>');
				}
			}).fail(function () {
				$status.addClass('is-error').text(RWAI.i18n.titleFail);
				$out.html('<p class="rwai-pill rwai-pill-bad">' + RWAI.i18n.titleFail + '</p>');
			}).always(function () { $btn.prop('disabled', false); });
		});

		// ----- Title Lab: analyze custom title -----
		$('#rwai-tl-analyze').on('click', function () {
			var title = $.trim($('#rwai-tl-analyze-input').val() || '');
			if (!title) return;
			var $out = $('#rwai-tl-analyze-result');
			$out.html('<p class="rwai-muted">Analyzing…</p>');
			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: { action: 'rwai_title_analyze', nonce: RWAI.titleNonce, title: title },
				dataType: 'json'
			}).done(function (res) {
				if (res && res.success && res.data) {
					$out.html('<div class="rwai-tl-style-block">' + tlRenderVariant(res.data, 0) + '</div>');
				} else {
					$out.html('<p class="rwai-pill rwai-pill-bad">Analysis failed.</p>');
				}
			});
		});

		// ----- Title Lab: compare -----
		$('#rwai-tl-compare').on('click', function () {
			var raw = $('#rwai-tl-compare-input').val() || '';
			var titles = raw.split(/\r?\n/).map(function (t) { return t.trim(); }).filter(function (t) { return t.length > 0; });
			if (titles.length < 2) { window.alert('Enter at least 2 titles, one per line.'); return; }
			var $out = $('#rwai-tl-compare-result');
			$out.html('<p class="rwai-muted">Comparing…</p>');
			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: { action: 'rwai_title_compare', nonce: RWAI.titleNonce, titles: titles },
				dataType: 'json'
			}).done(function (res) {
				if (!res || !res.success || !res.data || !res.data.rows) {
					$out.html('<p class="rwai-pill rwai-pill-bad">Comparison failed.</p>');
					return;
				}
				var rows = res.data.rows;
				var styles = res.data.styles || Object.keys(STYLE_LABELS);
				var html = '<table class="widefat striped rwai-tl-compare-table"><thead><tr>';
				html += '<th>Title</th><th>Overall</th>';
				styles.forEach(function (s) { html += '<th>' + STYLE_LABELS[s] + '</th>'; });
				html += '<th>Length</th><th>Flags</th></tr></thead><tbody>';
				rows.forEach(function (r) {
					html += '<tr>';
					html += '<td><strong>' + $('<div>').text(r.title).html() + '</strong></td>';
					html += '<td><span class="rwai-pill rwai-pill-' + tlBand(r.overall_score) + '">' + r.overall_score + '</span></td>';
					styles.forEach(function (s) {
						var sc = (r.platform_scores && r.platform_scores[s]) || 0;
						html += '<td><span class="rwai-tl-tinybar"><span class="rwai-tl-tinybar-fill rwai-tl-bar-' + tlBand(sc) + '" style="width:' + sc + '%"></span></span> ' + sc + '</td>';
					});
					html += '<td>' + r.length + '</td>';
					var flags = '';
					if (r.clickbait && r.clickbait.is_clickbait) flags += '<span class="rwai-tl-tag rwai-tl-tag-bad">clickbait</span>';
					(r.emotional_triggers || []).slice(0, 3).forEach(function (t) {
						flags += '<span class="rwai-tl-tag rwai-tl-tag-trigger">' + t + '</span>';
					});
					html += '<td>' + flags + '</td>';
					html += '</tr>';
				});
				html += '</tbody></table>';
				$out.html(html);
			});
		});

		// ----- Post-edit: generate alternative titles + swap -----
		$(document).on('click', '.rwai-title-swap-trigger', function () {
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var topic  = $btn.data('topic');
			var $panel = $('#rwai-title-swap-' + postId);
			$panel.slideDown();
			$panel.html('<p class="rwai-muted">' + RWAI.i18n.titleGen + '</p>');
			$btn.prop('disabled', true);
			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: { action: 'rwai_title_generate', nonce: RWAI.titleNonce, topic: topic, count: 2 },
				dataType: 'json', timeout: 120000
			}).done(function (res) {
				if (res && res.success && res.data && res.data.variants) {
					var html = '';
					Object.keys(STYLE_LABELS).forEach(function (s) {
						var rows = (res.data.variants && res.data.variants[s]) || [];
						if (!rows.length) return;
						html += '<h4 style="margin:8px 0 4px;font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#50575e;">' + STYLE_LABELS[s] + '</h4>';
						rows.forEach(function (analysis) {
							html += tlRenderVariant(analysis, postId);
						});
					});
					$panel.html(html);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : RWAI.i18n.titleFail;
					$panel.html('<p class="rwai-pill rwai-pill-bad">' + msg + '</p>');
				}
			}).fail(function () {
				$panel.html('<p class="rwai-pill rwai-pill-bad">' + RWAI.i18n.titleFail + '</p>');
			}).always(function () { $btn.prop('disabled', false); });
		});

		$(document).on('click', '.rwai-tl-use-title', function () {
			var $btn = $(this);
			var postId = $btn.data('post-id');
			var newTitle = $btn.data('title');
			if (!postId || !newTitle) return;
			if (!window.confirm('Replace the post title with:\n\n"' + newTitle + '"?')) return;
			$btn.prop('disabled', true).text('Saving…');
			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: { action: 'rwai_title_swap', nonce: RWAI.titleNonce, post_id: postId, title: newTitle },
				dataType: 'json'
			}).done(function (res) {
				if (res && res.success) {
					// Try to update the visible title input on the post-edit screen.
					var $titleInput = $('#title, input[name="post_title"]');
					if ($titleInput.length) $titleInput.val(newTitle);
					$btn.removeClass('button-small').addClass('button-primary').text('✓ Title updated');
					$btn.prop('disabled', true);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : 'Failed.';
					window.alert(msg);
					$btn.prop('disabled', false).text('Use this title');
				}
			}).fail(function () {
				window.alert(RWAI.i18n.titleFail);
				$btn.prop('disabled', false).text('Use this title');
			});
		});

		// ====================== Google Discover Optimizer ======================

		function doBand(score) {
			if (score >= 75) return 'ok';
			if (score >= 50) return 'warn';
			return 'bad';
		}

		function doRenderResult(d) {
			if (!d) return '<p class="rwai-pill rwai-pill-bad">No result.</p>';
			var dims = {
				mobile_engagement: 'Mobile engagement',
				freshness:         'Freshness',
				emotional_engagement: 'Emotional pull',
				image_readiness:   'Image readiness'
			};
			var html = '<div class="rwai-do-result">';
			html += '<div class="rwai-do-overall rwai-do-band-' + doBand(d.overall) + '">';
			html += '<div class="rwai-do-overall-num">' + d.overall + '<small>/100</small></div>';
			html += '<div class="rwai-do-overall-label">Discover score</div>';
			html += '</div>';

			html += '<div class="rwai-do-dim-grid">';
			Object.keys(dims).forEach(function (key) {
				var dim = d[key] || { score: 0, reasons: [] };
				var sc = dim.score || 0;
				html += '<div class="rwai-do-dim">';
				html += '<div class="rwai-do-dim-head"><span>' + dims[key] + '</span>';
				html += '<span class="rwai-pill rwai-pill-' + doBand(sc) + '">' + sc + '</span></div>';
				html += '<div class="rwai-do-dim-bar"><span class="rwai-tl-bar-fill rwai-tl-bar-' + doBand(sc) + '" style="width:' + sc + '%"></span></div>';
				if (dim.reasons && dim.reasons.length) {
					html += '<ul class="rwai-do-reasons">';
					dim.reasons.slice(0, 3).forEach(function (r) {
						html += '<li>' + $('<div>').text(r).html() + '</li>';
					});
					html += '</ul>';
				}
				html += '</div>';
			});
			html += '</div>';

			if (d.recommendations && d.recommendations.length) {
				html += '<div class="rwai-do-recos-block"><h4>Quick wins</h4><ol>';
				d.recommendations.forEach(function (r) {
					html += '<li>' + $('<div>').text(r).html() + '</li>';
				});
				html += '</ol></div>';
			}

			html += '</div>';
			return html;
		}

		// Score a saved post.
		$('#rwai-do-score-post').on('click', function () {
			var postId = $('#rwai-do-post-id').val();
			if (!postId) { window.alert('Pick a post first.'); return; }
			var $out = $('#rwai-do-post-result');
			$out.html('<p class="rwai-muted">' + RWAI.i18n.discoverScore + '</p>');
			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: { action: 'rwai_discover_score_post', nonce: RWAI.discoverNonce, post_id: postId },
				dataType: 'json'
			}).done(function (res) {
				if (res && res.success && res.data) {
					$out.html(doRenderResult(res.data));
				} else {
					$out.html('<p class="rwai-pill rwai-pill-bad">' + (res && res.data && res.data.message ? res.data.message : RWAI.i18n.discoverFail) + '</p>');
				}
			});
		});

		// Score a pasted draft.
		$('#rwai-do-score-content').on('click', function () {
			var title = $('#rwai-do-title').val();
			var content = $('#rwai-do-content').val();
			var img = $('#rwai-do-image').val();
			if (!content) { window.alert('Paste content first.'); return; }
			var $out = $('#rwai-do-draft-result');
			$out.html('<p class="rwai-muted">' + RWAI.i18n.discoverScore + '</p>');
			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: { action: 'rwai_discover_score_content', nonce: RWAI.discoverNonce, title: title, content: content, image_url: img },
				dataType: 'json'
			}).done(function (res) {
				if (res && res.success && res.data) {
					$out.html(doRenderResult(res.data));
				} else {
					$out.html('<p class="rwai-pill rwai-pill-bad">' + (res && res.data && res.data.message ? res.data.message : RWAI.i18n.discoverFail) + '</p>');
				}
			});
		});

		// Generate Discover hooks.
		$('#rwai-do-hook-generate').on('click', function () {
			var topic = $.trim($('#rwai-do-hook-topic').val() || '');
			if (!topic) { window.alert('Enter a topic.'); return; }
			var $btn = $(this);
			var $status = $('#rwai-do-hook-status');
			var $out = $('#rwai-do-hook-result');
			$btn.prop('disabled', true);
			$status.removeClass('is-ok is-error').text(RWAI.i18n.discoverHooks);
			$out.html('');
			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: { action: 'rwai_discover_hooks', nonce: RWAI.discoverNonce, topic: topic },
				dataType: 'json', timeout: 120000
			}).done(function (res) {
				if (res && res.success && res.data && res.data.hooks) {
					$status.addClass('is-ok').text('');
					var hooks = res.data.hooks;
					var html = '<div class="rwai-do-hooks">';
					hooks.forEach(function (h, idx) {
						html += '<div class="rwai-do-hook"><h4>Hook ' + (idx + 1) + '</h4>';
						html += '<p>' + $('<div>').text(h).html() + '</p></div>';
					});
					html += '</div>';
					$out.html(html);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : RWAI.i18n.discoverFail;
					$status.addClass('is-error').text(msg);
				}
			}).fail(function () {
				$status.addClass('is-error').text(RWAI.i18n.discoverFail);
			}).always(function () { $btn.prop('disabled', false); });
		});

		// ====================== AI Humanization Lab ======================

		function humBand(score) {
			if (score >= 75) return 'ok';
			if (score >= 50) return 'warn';
			return 'bad';
		}

		function humRenderAnalysis(d) {
			if (!d) return '';
			var band = humBand(d.score);
			var html = '<div class="rwai-hum-result">';
			html += '<div class="rwai-hum-score rwai-do-band-' + band + '">';
			html += '<div class="rwai-do-overall-num">' + d.score + '<small>/100</small></div>';
			html += '<div class="rwai-do-overall-label">Human-likeness</div>';
			html += '</div>';

			html += '<div class="rwai-hum-result-meta">';
			html += '<dl class="rwai-dl">';
			html += '<dt>Words</dt><dd>' + d.word_count + '</dd>';
			html += '<dt>Paragraphs</dt><dd>' + d.paragraph_count + ' (avg ' + d.avg_paragraph_words + ' words, σ ' + d.paragraph_stddev + ')</dd>';
			html += '<dt>Sentences</dt><dd>' + d.sentence_count + ' (avg ' + d.avg_sentence_words + ' words)</dd>';
			html += '<dt>Contractions / 200 words</dt><dd>' + d.contractions_per_200 + '</dd>';
			html += '<dt>Questions / 600 words</dt><dd>' + d.questions_per_600 + '</dd>';
			html += '<dt>Total pattern hits</dt><dd>' + d.total_pattern_hits + '</dd>';
			html += '</dl>';
			html += '</div>';

			if (d.hits_by_group && Object.keys(d.hits_by_group).length) {
				html += '<div class="rwai-hum-result-hits"><h4>AI tells detected</h4><ul>';
				Object.keys(d.hits_by_group).forEach(function (group) {
					var phrases = d.hits_by_group[group];
					html += '<li><strong>' + group.replace(/_/g, ' ') + ':</strong> ';
					html += phrases.slice(0, 6).map(function (p) {
						return '<code>' + $('<div>').text(p).html() + '</code>';
					}).join(' · ');
					html += '</li>';
				});
				html += '</ul></div>';
			}

			html += '</div>';
			return html;
		}

		// Analyze button.
		$('#rwai-hum-analyze').on('click', function () {
			var content = $('#rwai-hum-input').val();
			if (!content) { window.alert('Paste content first.'); return; }
			var $out = $('#rwai-hum-analysis');
			$out.html('<p class="rwai-muted">' + RWAI.i18n.humanizeAnalyze + '</p>');
			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: { action: 'rwai_humanize_analyze', nonce: RWAI.humanizerNonce, content: content },
				dataType: 'json'
			}).done(function (res) {
				if (res && res.success && res.data) {
					$out.html(humRenderAnalysis(res.data));
				} else {
					$out.html('<p class="rwai-pill rwai-pill-bad">Analysis failed.</p>');
				}
			});
		});

		// Rewrite button.
		$('#rwai-hum-rewrite').on('click', function () {
			var content = $('#rwai-hum-input').val();
			if (!content || content.length < 200) {
				window.alert('Paste at least 200 characters of content first.');
				return;
			}
			var $btn = $(this);
			var $status = $('#rwai-hum-rewrite-status');
			$btn.prop('disabled', true);
			$status.removeClass('is-ok is-error').text(RWAI.i18n.humanizeRewrite);

			$.ajax({
				url: RWAI.ajaxUrl, method: 'POST',
				data: {
					action: 'rwai_humanize_rewrite',
					nonce: RWAI.humanizerNonce,
					content: content,
					strength:    $('#rwai-hum-strength').val(),
					tone:        $('#rwai-hum-tone').val(),
					personality: $('#rwai-hum-persona').val(),
					readability: $('#rwai-hum-readability').val()
				},
				dataType: 'json', timeout: 180000
			}).done(function (res) {
				if (res && res.success && res.data) {
					$status.addClass('is-ok').text(RWAI.i18n.humanizeDone);
					$('#rwai-hum-output-card').show();
					$('#rwai-hum-before').html(content);
					$('#rwai-hum-after').html(res.data.rewritten);

					var b = res.data.before_score, a = res.data.after_score;
					$('#rwai-hum-before-score').text(b + '/100').removeClass().addClass('rwai-pill rwai-pill-' + humBand(b));
					$('#rwai-hum-after-score').text(a + '/100').removeClass().addClass('rwai-pill rwai-pill-' + humBand(a));
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : RWAI.i18n.humanizeFail;
					$status.addClass('is-error').text(msg);
				}
			}).fail(function () {
				$status.addClass('is-error').text(RWAI.i18n.humanizeFail);
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});

		// Copy rewritten HTML.
		$('#rwai-hum-copy').on('click', function () {
			var html = $('#rwai-hum-after').html();
			if (!html) return;
			var temp = $('<textarea>').css({ position: 'fixed', left: '-9999px' }).val(html).appendTo('body');
			temp[0].select();
			try {
				document.execCommand('copy');
				$('#rwai-hum-copy-status').text('Copied!').delay(1500).queue(function () { $(this).text('').dequeue(); });
			} catch (e) {
				$('#rwai-hum-copy-status').text('Copy failed — select manually.');
			}
			temp.remove();
		});

		// ====================== Autopilot frequency toggles ======================
		// Time row hides for "hourly"; weekday row shows only for "weekly".

		function applyFrequencyVisibility() {
			var $freq = $('[data-rwai-frequency]');
			if (!$freq.length) return;
			var v = $freq.val();
			$('[data-rwai-time-row]').toggle(v !== 'hourly');
			$('[data-rwai-day-row]').toggle(v === 'weekly');
		}
		$(document).on('change', '[data-rwai-frequency]', applyFrequencyVisibility);
		applyFrequencyVisibility();

		// ============================ AI field auto-fill ============================
		// Runs on every plugin admin page that has a form with data-rwai-ai-context.

		function collectFormPayload($form) {
			var payload = {};
			$form.find('[data-rwai-ai-target]').each(function () {
				var $el = $(this);
				var key = $el.data('rwai-ai-target');
				payload[key] = $el.val() != null ? String($el.val()) : '';
			});
			return payload;
		}

		function setFieldValue($form, fieldKey, value) {
			var $target = $form.find('[data-rwai-ai-target="' + fieldKey + '"]').first();
			if (!$target.length) return false;
			if ($target.is('select')) {
				$target.val(value).trigger('change');
			} else {
				$target.val(value).trigger('input').trigger('change');
			}
			return true;
		}

		function aiFill($form, fieldKey, $btn) {
			var context = $form.data('rwai-ai-context');
			var needs = $btn ? $btn.data('rwai-ai-needs') : '';
			var payload = collectFormPayload($form);

			if (needs && !String(payload[needs] || '').trim()) {
				var $needsField = $form.find('[data-rwai-ai-target="' + needs + '"]').first();
				if ($needsField.length) {
					$needsField.focus();
				}
				window.alert('Fill in the "' + needs.replace(/_/g, ' ') + '" field first so AI knows what to base the suggestion on.');
				return $.Deferred().reject().promise();
			}

			var originalLabel = $btn ? $btn.html() : '';
			if ($btn) {
				$btn.prop('disabled', true).text(RWAI.i18n.aiThinking);
			}

			return $.ajax({
				url: RWAI.ajaxUrl,
				method: 'POST',
				data: {
					action: 'rwai_ai_suggest',
					nonce: RWAI.aiSuggestNonce,
					context: context,
					field: fieldKey,
					payload: payload
				},
				dataType: 'json',
				timeout: 60000
			}).done(function (res) {
				if (res && res.success && res.data && typeof res.data.value !== 'undefined') {
					setFieldValue($form, fieldKey, res.data.value);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : RWAI.i18n.aiFailed;
					window.alert(msg);
				}
			}).fail(function () {
				window.alert(RWAI.i18n.aiFailed);
			}).always(function () {
				if ($btn) {
					$btn.prop('disabled', false).html(originalLabel);
				}
			});
		}

		$(document).on('click', '.rwai-ai-fill', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $form = $btn.closest('form[data-rwai-ai-context]');
			if (!$form.length) return;
			var fieldKey = $btn.data('rwai-ai-field');
			if (!fieldKey) return;
			aiFill($form, fieldKey, $btn);
		});

		$(document).on('click', '.rwai-ai-fill-all', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var $form = $btn.closest('form[data-rwai-ai-context]');
			if (!$form.length) return;

			var needs = $btn.data('rwai-ai-needs');
			if (needs && !String($form.find('[data-rwai-ai-target="' + needs + '"]').val() || '').trim()) {
				window.alert('Fill in the "' + needs.replace(/_/g, ' ') + '" field first so AI knows what to base suggestions on.');
				return;
			}

			var $fields = $form.find('.rwai-ai-fill');
			var emptyFields = [];
			$fields.each(function () {
				var k = $(this).data('rwai-ai-field');
				var $target = $form.find('[data-rwai-ai-target="' + k + '"]').first();
				if (!$target.length) return;
				var val = String($target.val() || '').trim();
				var isSelect = $target.is('select');
				if (val === '' || (isSelect && $target.find('option:selected').index() === 0)) {
					emptyFields.push(k);
				}
			});

			if (emptyFields.length === 0) {
				window.alert('No empty fields to fill.');
				return;
			}

			var originalLabel = $btn.html();
			$btn.prop('disabled', true).text(RWAI.i18n.aiThinking + ' (0/' + emptyFields.length + ')');

			function next(idx) {
				if (idx >= emptyFields.length) {
					$btn.prop('disabled', false).html(originalLabel);
					return;
				}
				$btn.text(RWAI.i18n.aiThinking + ' (' + (idx + 1) + '/' + emptyFields.length + ')');
				aiFill($form, emptyFields[idx], null).always(function () {
					next(idx + 1);
				});
			}
			next(0);
		});

		// ============================ Blog Analyzer page ============================
		// Only attaches on the Blog Analyzer page; quietly no-ops elsewhere.

		var $analyzeForm = $('#rwai-analyze-form');
		if (!$analyzeForm.length) {
			return;
		}
		var $status = $('#rwai-analyze-status');
		var $analyzeBtn = $('#rwai-analyze-btn');
		var $deepBtn = $('#rwai-deep-btn');

		$analyzeForm.on('submit', function (e) {
			e.preventDefault();
			$analyzeBtn.prop('disabled', true);
			$deepBtn.prop('disabled', true);
			$status.removeClass('is-ok is-error').text(RWAI.i18n.running);

			$.ajax({
				url: RWAI.ajaxUrl,
				method: 'POST',
				data: { action: 'rwai_run_analysis', nonce: RWAI.analysisNonce },
				dataType: 'json',
				timeout: 180000
			}).done(function (res) {
				if (res && res.success) {
					$status.addClass('is-ok').text(RWAI.i18n.done);
					window.setTimeout(function () { window.location.reload(); }, 800);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : RWAI.i18n.failed;
					$status.addClass('is-error').text(msg);
					$analyzeBtn.prop('disabled', false);
					$deepBtn.prop('disabled', false);
				}
			}).fail(function () {
				$status.addClass('is-error').text(RWAI.i18n.failed);
				$analyzeBtn.prop('disabled', false);
				$deepBtn.prop('disabled', false);
			});
		});

		$deepBtn.on('click', function (e) {
			e.preventDefault();
			$analyzeBtn.prop('disabled', true);
			$deepBtn.prop('disabled', true);
			$status.removeClass('is-ok is-error').text(RWAI.i18n.deepRunning);

			$.ajax({
				url: RWAI.ajaxUrl,
				method: 'POST',
				data: { action: 'rwai_deep_analysis', nonce: RWAI.deepNonce },
				dataType: 'json',
				timeout: 240000
			}).done(function (res) {
				if (res && res.success) {
					$status.addClass('is-ok').text(RWAI.i18n.deepDone);
					window.setTimeout(function () { window.location.reload(); }, 800);
				} else {
					var msg = (res && res.data && res.data.message) ? res.data.message : RWAI.i18n.failed;
					$status.addClass('is-error').text(msg);
					$analyzeBtn.prop('disabled', false);
					$deepBtn.prop('disabled', false);
				}
			}).fail(function () {
				$status.addClass('is-error').text(RWAI.i18n.failed);
				$analyzeBtn.prop('disabled', false);
				$deepBtn.prop('disabled', false);
			});
		});
	});
})(jQuery);
