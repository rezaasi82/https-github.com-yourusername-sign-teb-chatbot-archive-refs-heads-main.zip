<?php
/**
 * Contract for alert delivery channels. The engine dispatches one digest per
 * evaluation run to every enabled channel; PRO channels additionally require
 * the alert_channels feature.
 *
 * @package SEODirector
 */

namespace SEODirector\Alerts\Channels;

defined( 'ABSPATH' ) || exit;

interface AlertChannelInterface {

	public function slug(): string;

	/**
	 * Configured and switched on in settings.
	 */
	public function is_enabled(): bool;

	/**
	 * Whether this channel is gated behind the PRO alert_channels feature.
	 */
	public function requires_pro(): bool;

	/**
	 * @param array<int, array{rule: string, severity: string, message: string}> $alerts
	 */
	public function send_digest( array $alerts ): void;
}
