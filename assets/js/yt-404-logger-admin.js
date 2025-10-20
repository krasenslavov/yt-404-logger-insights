/**
 * YT 404 Logger & Insights - Admin JavaScript
 *
 * @format
 * @package YT_404_Logger_Insights
 */

(function ($) {
	"use strict";

	$(document).ready(function () {
		/**
		 * Handle delete single log entry
		 */
		$(".yt-404-delete-log").on("click", function (e) {
			e.preventDefault();

			if (!confirm(yt404Logger.confirmDelete)) {
				return;
			}

			var $button = $(this);
			var $row = $button.closest("tr");
			var logId = $button.data("log-id");

			$row.addClass("deleting");
			$button.prop("disabled", true);

			$.ajax({
				url: yt404Logger.ajaxUrl,
				type: "POST",
				data: {
					action: "yt_404_logger_delete_log",
					nonce: yt404Logger.nonce,
					log_id: logId
				},
				success: function (response) {
					if (response.success) {
						$row.addClass("deleted").fadeOut(400, function () {
							$(this).remove();
							checkEmptyTable();
						});
					} else {
						alert(yt404Logger.errorOccurred);
						$row.removeClass("deleting");
						$button.prop("disabled", false);
					}
				},
				error: function () {
					alert(yt404Logger.errorOccurred);
					$row.removeClass("deleting");
					$button.prop("disabled", false);
				}
			});
		});

		/**
		 * Handle clear all logs
		 */
		$(".yt-404-clear-logs").on("click", function (e) {
			e.preventDefault();

			if (!confirm(yt404Logger.confirmClear)) {
				return;
			}

			var $button = $(this);
			$button.prop("disabled", true).text("Clearing...");

			$.ajax({
				url: yt404Logger.ajaxUrl,
				type: "POST",
				data: {
					action: "yt_404_logger_clear_logs",
					nonce: yt404Logger.nonce
				},
				success: function (response) {
					if (response.success) {
						location.reload();
					} else {
						alert(yt404Logger.errorOccurred);
						$button.prop("disabled", false).text("Clear All Logs");
					}
				},
				error: function () {
					alert(yt404Logger.errorOccurred);
					$button.prop("disabled", false).text("Clear All Logs");
				}
			});
		});

		/**
		 * Check if table is empty and show message
		 */
		function checkEmptyTable() {
			var $tbody = $(".yt-404-logger-wrap table tbody");
			if ($tbody.find("tr").length === 0) {
				$tbody.html('<tr><td colspan="5">No 404 errors logged yet.</td></tr>');
			}
		}
	});
})(jQuery);
