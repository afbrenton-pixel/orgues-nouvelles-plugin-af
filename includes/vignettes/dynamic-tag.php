<?php
/**
 * Balise dynamique Elementor : « Lien de la vignette ».
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'elementor/dynamic_tags/register',
	function ( $dynamic_tags ) {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) || ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}
		if ( ! class_exists( 'ON_Vignettes_Lien_Tag' ) ) {
			/** Lien de la vignette (champ « lien », sinon adresse du contenu d'origine). */
			class ON_Vignettes_Lien_Tag extends \Elementor\Core\DynamicTags\Data_Tag {
				public function get_name() {
					return 'on-vignettes-lien';
				}

				public function get_title() {
					return 'Lien de la vignette';
				}

				public function get_group() {
					return 'site';
				}

				public function get_categories() {
					return array( \Elementor\Modules\DynamicTags\Module::URL_CATEGORY );
				}

				public function get_value( array $options = array() ) {
					$id  = get_the_ID();
					$url = $id ? get_post_meta( $id, 'lien', true ) : '';
					if ( is_array( $url ) ) {
						$url = reset( $url );
					}
					if ( ! $url && $id ) {
						$source = (int) get_post_meta( $id, '_on_source_id', true );
						$url    = $source ? get_permalink( $source ) : '';
					}
					return $url ? esc_url_raw( (string) $url ) : '';
				}
			}
		}
		$dynamic_tags->register( new ON_Vignettes_Lien_Tag() );
	}
);
