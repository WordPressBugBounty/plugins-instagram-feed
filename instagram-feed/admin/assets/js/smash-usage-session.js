/**
 * Smash Usage Tracking: session duration and record-event from JS.
 *
 * @package InstagramFeed
 */
(function ($) {
	'use strict';

	if (typeof window.sbiSmashUsageSession === 'undefined') {
		return;
	}

	var config = window.sbiSmashUsageSession;
	var sessionStart = Date.now();

	function sendSessionDuration() {
		var durationSeconds = Math.round((Date.now() - sessionStart) / 1000);
		if (durationSeconds < 3) {
			return;
		}
		if (navigator.sendBeacon) {
			var data = new FormData();
			data.append('action', 'sbi_smash_usage_record_session');
			data.append('nonce', config.nonce);
			data.append('duration_seconds', durationSeconds);
			navigator.sendBeacon(config.ajax_url, data);
		} else {
			$.post(config.ajax_url, {
				action: 'sbi_smash_usage_record_session',
				nonce: config.nonce,
				duration_seconds: durationSeconds
			});
		}
	}

	function recordEvent(eventName) {
		$.post(config.ajax_url, {
			action: 'sbi_smash_usage_record_event',
			nonce: config.event_nonce,
			event_name: eventName
		});
	}

	/**
	 * Fire-and-forget event record that survives an immediate page navigation.
	 *
	 * recordEvent() above uses $.post, which is async and can be aborted by the
	 * browser if the page navigates away (e.g. a hidden-form POST to an external
	 * OAuth broker) before the request completes. Losing that request under-counts
	 * the event -- for a funnel "started" event, that inflates the completion rate
	 * in the flattering direction, which is worse than not recording it at all.
	 * Use this instead whenever the call is immediately followed by a navigation.
	 *
	 * @since 6.13 (SMASH-1856)
	 *
	 * @param {string} eventName
	 */
	function recordEventBeacon(eventName) {
		if (navigator.sendBeacon) {
			var data = new FormData();
			data.append('action', 'sbi_smash_usage_record_event');
			data.append('nonce', config.event_nonce);
			data.append('event_name', eventName);
			navigator.sendBeacon(config.ajax_url, data);
			return;
		}

		// Fallback for browsers without sendBeacon: fire-and-forget fetch with
		// keepalive so the request can outlive the page unload that follows.
		if (typeof fetch === 'function') {
			try {
				fetch(config.ajax_url, {
					method: 'POST',
					keepalive: true,
					credentials: 'same-origin',
					body: new URLSearchParams({
						action: 'sbi_smash_usage_record_event',
						nonce: config.event_nonce,
						event_name: eventName
					})
				});
			} catch (e) {
				// Neither sendBeacon nor fetch is available -- accept the loss
				// rather than block or delay the navigation that follows.
			}
		}
	}

	$(window).on('beforeunload pagehide', function () {
		sendSessionDuration();
	});

	window.sbiSmashUsageRecordEvent = recordEvent;
	window.sbiSmashUsageRecordEventBeacon = recordEventBeacon;
})(jQuery);
