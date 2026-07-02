<?php
/**
 * SignTeb Medical Core — Topic Cluster System
 *
 * مدیریت رابطه Pillar ← Cluster برای Topical Authority
 *
 * @package SignTeb_Medical_Core
 */

declare( strict_types=1 );

namespace STMC\Seo;

use STMC\Loader;

defined( 'ABSPATH' ) || exit;

final class TopicCluster {

	public function __construct( private readonly Loader $loader ) {
		$this->loader->add_action( 'add_meta_boxes', $this, 'add_meta_box' );
		$this->loader->add_action( 'save_post',      $this, 'save_cluster', 10, 2 );
		$this->loader->add_filter( 'the_content',    $this, 'append_cluster_links', 30 );
	}

	// ─── Meta Box ─────────────────────────────────────────────────────────────

	public function add_meta_box(): void {
		$screen_cpts = [ 'post', 'page', 'doctor', 'medical-service', 'treatment', 'disease' ];
		add_meta_box(
			'stmc-topic-cluster',
			__( '🗺️ Topic Cluster — Topical Authority', STMC_TEXT ),
			[ $this, 'render_meta_box' ],
			$screen_cpts,
			'side',
			'default'
		);
	}

	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'stmc_cluster_nonce', 'stmc_cluster_nonce' );

		$pillar_id   = (int) get_post_meta( $post->ID, 'stmc_pillar_id', true );
		$is_pillar   = (bool) get_post_meta( $post->ID, 'stmc_is_pillar', true );

		echo '<p style="margin-bottom:8px;">';
		echo '<label><input type="checkbox" name="stmc_is_pillar" value="1"' . checked( $is_pillar, true, false ) . '> ';
		esc_html_e( 'این صفحه یک Pillar Page است', STMC_TEXT );
		echo '</label></p>';

		echo '<p style="margin-top:8px;font-size:12px;color:#666;">';
		esc_html_e( 'صفحه Pillar والد:', STMC_TEXT );
		echo '</p>';

		// Pillar page selector
		$pillar_pages = get_posts( [
			'post_type'      => array_keys( $this->get_meta( 'stmc_is_pillar' ) ),
			'posts_per_page' => 50,
			'post_status'    => 'publish',
			'meta_key'       => 'stmc_is_pillar',
			'meta_value'     => '1',
			'exclude'        => [ $post->ID ],
		] );

		// Fallback: just get pages
		if ( empty( $pillar_pages ) ) {
			$pillar_pages = get_posts( [
				'post_type'      => [ 'page', 'post', 'medical-service', 'disease' ],
				'posts_per_page' => 50,
				'post_status'    => 'publish',
				'exclude'        => [ $post->ID ],
			] );
		}

		echo '<select name="stmc_pillar_id" style="width:100%;">';
		echo '<option value="">' . esc_html__( '— بدون Pillar —', STMC_TEXT ) . '</option>';
		foreach ( $pillar_pages as $p ) {
			printf(
				'<option value="%d"%s>%s</option>',
				$p->ID,
				selected( $pillar_id, $p->ID, false ),
				esc_html( $p->post_title )
			);
		}
		echo '</select>';

		// Show cluster count for pillar pages
		if ( $is_pillar ) {
			global $wpdb;
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}stmc_topic_clusters WHERE pillar_id=%d",
				$post->ID
			) );
			printf(
				'<p style="margin-top:8px;font-size:12px;color:#2271b1;">%s</p>',
				sprintf( esc_html__( '✓ %d صفحه Cluster به این Pillar متصل‌اند.', STMC_TEXT ), (int) $count )
			);
		}
	}

	// ─── Save ─────────────────────────────────────────────────────────────────

	public function save_cluster( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['stmc_cluster_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['stmc_cluster_nonce'] ) ), 'stmc_cluster_nonce' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$is_pillar = ! empty( $_POST['stmc_is_pillar'] ) ? '1' : '';
		update_post_meta( $post_id, 'stmc_is_pillar', $is_pillar );

		$pillar_id = absint( $_POST['stmc_pillar_id'] ?? 0 );
		update_post_meta( $post_id, 'stmc_pillar_id', $pillar_id );

		// Update cluster mapping table
		if ( $pillar_id && $pillar_id !== $post_id ) {
			global $wpdb;
			$wpdb->replace(
				$wpdb->prefix . 'stmc_topic_clusters',
				[
					'pillar_id'  => $pillar_id,
					'cluster_id' => $post_id,
					'weight'     => 5,
					'created_at' => current_time( 'mysql' ),
				],
				[ '%d', '%d', '%d', '%s' ]
			);
		} elseif ( 0 === $pillar_id ) {
			// Detach from any pillar
			global $wpdb;
			$wpdb->delete(
				$wpdb->prefix . 'stmc_topic_clusters',
				[ 'cluster_id' => $post_id ],
				[ '%d' ]
			);
		}
	}

	// ─── Append Cluster Links at bottom of content ───────────────────────────

	public function append_cluster_links( string $content ): string {
		if ( ! is_singular() ) {
			return $content;
		}

		$post_id   = get_the_ID();
		$is_pillar = (bool) get_post_meta( $post_id, 'stmc_is_pillar', true );

		if ( ! $is_pillar ) {
			return $content;
		}

		$clusters = $this->get_clusters_for_pillar( $post_id );

		if ( empty( $clusters ) ) {
			return $content;
		}

		$html  = '<div class="stmc-cluster-links">';
		$html .= '<h3 class="stmc-cluster-links__title">' . esc_html__( 'مطالب مرتبط در این موضوع', STMC_TEXT ) . '</h3>';
		$html .= '<ul class="stmc-cluster-links__list">';

		foreach ( $clusters as $cluster ) {
			$html .= sprintf(
				'<li class="stmc-cluster-links__item"><a href="%s" class="stmc-cluster-link">%s</a></li>',
				esc_url( get_permalink( $cluster->ID ) ),
				esc_html( $cluster->post_title )
			);
		}

		$html .= '</ul></div>';

		return $content . $html;
	}

	// ─── Helpers ──────────────────────────────────────────────────────────────

	private function get_clusters_for_pillar( int $pillar_id ): array {
		global $wpdb;

		$cluster_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT cluster_id FROM {$wpdb->prefix}stmc_topic_clusters WHERE pillar_id=%d ORDER BY weight DESC",
			$pillar_id
		) );

		if ( empty( $cluster_ids ) ) {
			return [];
		}

		return get_posts( [
			'post__in'       => array_map( 'absint', $cluster_ids ),
			'post_type'      => 'any',
			'posts_per_page' => 20,
			'post_status'    => 'publish',
			'orderby'        => 'post__in',
		] );
	}

	private function get_meta( string $meta_key ): array {
		// Returns a dummy array for post type resolution
		return [
			'page'             => true,
			'post'             => true,
			'medical-service'  => true,
			'disease'          => true,
			'treatment'        => true,
		];
	}
}
