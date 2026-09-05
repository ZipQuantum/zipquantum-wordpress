<?php
/**
 * Content-to-Smart-Link synchronization.
 *
 * @package ZipQuantumSmartLinks
 */

defined( 'ABSPATH' ) || exit;

final class ZIPQUANTUM_Sync {

	/** @var ZIPQUANTUM_API_Client */
	private $api;

	public function __construct( ZIPQUANTUM_API_Client $api ) {
		$this->api = $api;
	}

	public function hooks() {
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );
		add_action( 'edited_product_cat', array( $this, 'on_save_product_category' ), 20 );
		add_action( 'created_product_cat', array( $this, 'on_save_product_category' ), 20 );
		add_action( 'before_delete_post', array( $this, 'on_delete_post' ) );
		add_action( 'delete_product_cat', array( $this, 'on_delete_product_category' ) );
	}

	public function on_save_post( $post_id, $post, $update ) {
		unset( $update );
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'publish' !== $post->post_status ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );

		$is_woocommerce_object = class_exists( 'WooCommerce' ) && in_array( $post->post_type, array( 'product', 'shop_coupon' ), true );
		if ( ! $post_type_object || ( ! $post_type_object->public && ! $is_woocommerce_object ) ) {
			return;
		}

		$object_type = $this->object_type_for_post( $post );
		if ( ! $this->should_enqueue( $object_type, $post_id ) ) {
			return;
		}

		ZIPQUANTUM_Queue::enqueue( 'sync', $object_type, $post_id, $this->build_payload( $object_type, $post_id ) );
	}

	public function on_save_product_category( $term_id ) {
		if ( $this->should_enqueue( 'product_cat', $term_id ) ) {
			ZIPQUANTUM_Queue::enqueue( 'sync', 'product_cat', $term_id, $this->build_payload( 'product_cat', $term_id ) );
		}
	}

	public function on_delete_post( $post_id ) {
		$post = get_post( $post_id );
		if ( $post ) {
			$object_type = $this->object_type_for_post( $post );
			ZIPQUANTUM_Associations::delete( $object_type, $post_id );
			ZIPQUANTUM_Queue::cancel_object( $object_type, $post_id );
		}
	}

	public function on_delete_product_category( $term_id ) {
		ZIPQUANTUM_Associations::delete( 'product_cat', $term_id );
		ZIPQUANTUM_Queue::cancel_object( 'product_cat', $term_id );
	}

	public function sync( $object_type, $object_id, $payload = array() ) {
		if ( empty( $payload ) ) {
			$payload = $this->build_payload( $object_type, $object_id );
		}

		$credentials = ZIPQUANTUM_Options::get_secret( ZIPQUANTUM_Options::CREDENTIALS, array() );
		if ( empty( $credentials['installation_id'] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception; escaped only at the eventual HTML output boundary.
			throw new ZIPQUANTUM_HTTP_Exception( __( 'Reconnect ZipQuantum to synchronize content.', 'zipquantum-smart-links' ), 401, 'reconnect_required' );
		}

		$hash   = self::payload_hash( $payload );
		$key    = implode(
			':',
			array( 'wp', $credentials['installation_id'], $payload['object_type'], $payload['object_id'], $hash )
		);
		$result = $this->api->request(
			'POST',
			'/api/v1/integration-links/sync',
			$payload,
			array( 'Idempotency-Key' => $key )
		);

		$local = array(
			'management_mode' => $payload['management_mode'],
			'managed_fields'  => isset( $payload['managed_fields'] ) ? $payload['managed_fields'] : array(),
			'smart_link'      => isset( $result['smart_link'] ) ? $result['smart_link'] : array(),
			'association'     => isset( $result['association'] ) ? $result['association'] : array(),
			'last_status'     => isset( $result['status'] ) ? $result['status'] : 'updated',
			'local_status'    => 'active',
			'payload_hash'    => $hash,
		);
		ZIPQUANTUM_Associations::set( $object_type, $object_id, $local );

		return $result;
	}

	public function build_payload( $object_type, $object_id, $management_mode = '' ) {
		$association = ZIPQUANTUM_Associations::get( $object_type, $object_id );
		$mode        = $management_mode ? $management_mode : ( isset( $association['management_mode'] ) ? $association['management_mode'] : 'managed' );
		$payload     = array(
			'provider'        => 'wordpress',
			'object_type'     => sanitize_key( $object_type ),
			'object_id'       => (string) absint( $object_id ),
			'management_mode' => $mode,
		);

		if ( 'attached' === $mode ) {
			$link_id = isset( $association['smart_link']['id'] ) ? absint( $association['smart_link']['id'] ) : 0;
			if ( ! $link_id ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception; escaped only at the eventual HTML output boundary.
				throw new InvalidArgumentException( __( 'Choose a Smart Link before attaching it.', 'zipquantum-smart-links' ) );
			}
			$payload['link_id'] = $link_id;
			return $payload;
		}

		$content                   = $this->content_data( $object_type, $object_id );
		$settings                  = ZIPQUANTUM_Options::settings();
		$payload['managed_fields'] = array(
			'destination_url',
			'preview_title',
			'preview_description',
			'preview_image_url',
		);
		$payload['source_url']     = $content['url'];
		$routing_subdomain         = empty( $settings['custom_domain'] ) ? sanitize_title( $settings['managed_subdomain'] ) : '';
		$payload['link']           = array_filter(
			array(
				'link'                => $content['url'],
				'reference'           => $content['reference'],
				'subdomain'           => $routing_subdomain,
				'custom_domain'       => sanitize_text_field( $settings['custom_domain'] ),
				'preview_title'       => $content['title'],
				'preview_description' => $content['description'],
				'preview_image_url'   => $content['image'],
			),
			static function ( $value ) {
				return null !== $value && '' !== $value;
			}
		);

		return $payload;
	}

	private function content_data( $object_type, $object_id ) {
		if ( 'product_cat' === $object_type ) {
			$term = get_term( (int) $object_id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception; escaped only at the eventual HTML output boundary.
				throw new InvalidArgumentException( __( 'Product category not found.', 'zipquantum-smart-links' ) );
			}
			$image        = '';
			$thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
			if ( $thumbnail_id ) {
				$image = (string) wp_get_attachment_image_url( $thumbnail_id, 'full' );
			}

			$url = get_term_link( $term );
			if ( is_wp_error( $url ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception; escaped only at the eventual HTML output boundary.
				throw new InvalidArgumentException( $url->get_error_message() );
			}

			return array(
				'url'         => $url,
				'title'       => wp_strip_all_tags( $term->name ),
				'description' => wp_trim_words( wp_strip_all_tags( $term->description ), 35 ),
				'image'       => $image,
				'reference'   => $this->reference( $term->slug, $term->term_id ),
			);
		}

		$post = get_post( (int) $object_id );
		if ( ! $post ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception; escaped only at the eventual HTML output boundary.
			throw new InvalidArgumentException( __( 'WordPress content not found.', 'zipquantum-smart-links' ) );
		}
		$url = get_permalink( $post );
		if ( 'coupon' === $object_type ) {
			$settings = ZIPQUANTUM_Options::settings();
			$to       = isset( $settings['coupon_destination'] ) ? $settings['coupon_destination'] : '/checkout/';
			$url      = home_url( '/zipquantum-coupon/' . rawurlencode( $post->post_title ) . '/' ) . '?to=' . rawurlencode( $to );
		}

		return array(
			'url'         => $url,
			'title'       => wp_strip_all_tags( get_the_title( $post ) ),
			'description' => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ? $post->post_excerpt : $post->post_content ), 35 ),
			'image'       => (string) get_the_post_thumbnail_url( $post, 'full' ),
			'reference'   => $this->reference( $post->post_name ? $post->post_name : $post->post_title, $post->ID ),
		);
	}

	private function should_enqueue( $object_type, $object_id ) {
		if ( ! ZIPQUANTUM_Options::get_secret( ZIPQUANTUM_Options::CREDENTIALS, array() ) ) {
			return false;
		}
		$association = ZIPQUANTUM_Associations::get( $object_type, $object_id );
		if ( ! empty( $association ) && 'quarantined' !== ( $association['local_status'] ?? '' ) ) {
			return 'managed' === ( $association['management_mode'] ?? 'managed' );
		}

		$settings = ZIPQUANTUM_Options::settings();
		return ! empty( $settings['auto_create'] ) && in_array( $object_type, (array) $settings['object_types'], true );
	}

	private function object_type_for_post( $post ) {
		if ( 'product' === $post->post_type ) {
			return 'product';
		}
		if ( 'shop_coupon' === $post->post_type ) {
			return 'coupon';
		}
		return sanitize_key( $post->post_type );
	}

	private function reference( $slug, $id ) {
		$slug = substr( sanitize_title( $slug ), 0, 60 );
		return trim( $slug . '-' . absint( $id ), '-' );
	}

	public static function payload_hash( $payload ) {
		$canonical = self::canonicalize( $payload );
		return hash( 'sha256', wp_json_encode( $canonical, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}
}
