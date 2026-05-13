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
