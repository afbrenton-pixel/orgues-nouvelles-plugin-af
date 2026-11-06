<?php
/**
 * Balise dynamique Elementor : « Numéro des ressources de l'accueil ».
 *
 * Donne le numéro le plus élevé des vignettes de la zone « gauche-ressources »
 * (le numéro dont on affiche les ressources). À utiliser dans le titre
 * « Ressources N°… » de la page d'accueil, à la place de l'ancien code court [on_numero].
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Numéro le plus élevé parmi les vignettes publiées de la zone gauche-ressources (0 si aucun). */
function on_vignettes_dernier_numero() {
	$ids = get_posts(
		array(
			'post_type'        => 'vignette_accueil',
			'post_status'      => 'publish',
			'posts_per_page'   => 200,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => 'zone_vignette',
					'field'    => 'slug',
					'terms'    => 'gauche-ressources',
				),
			),
		)
	);
	$max = 0;
	foreach ( $ids as $id ) {
		$max = max( $max, (int) get_post_meta( $id, 'numero', true ) );
	}
	return $max;
}

add_action(
	'elementor/dynamic_tags/register',
	function ( $dynamic_tags ) {
		if ( ! class_exists( '\Elementor\Core\DynamicTags\Tag' ) || ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}
		if ( ! class_exists( 'ON_Vignettes_Numero_Tag' ) ) {
			class ON_Vignettes_Numero_Tag extends \Elementor\Core\DynamicTags\Tag {
				public function get_name() {
					return 'on-vignettes-numero';
				}

				public function get_title() {
					return 'Numéro des ressources de l\'accueil';
				}

				public function get_group() {
					return 'site';
				}

				public function get_categories() {
					return array( \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY );
				}

				protected function register_controls() {
					$this->add_control(
						'avant',
						array(
							'label'   => 'Texte avant le numéro',
							'type'    => \Elementor\Controls_Manager::TEXT,
							'default' => 'Ressources N°',
						)
					);
				}

				public function render() {
					$n = on_vignettes_dernier_numero();
					if ( ! $n ) {
						return;
					}
					$avant = trim( (string) $this->get_settings( 'avant' ) );
					echo esc_html( ( '' !== $avant ? $avant . ' ' : '' ) . $n );
				}
			}
		}
		$dynamic_tags->register( new ON_Vignettes_Numero_Tag() );
	}
);
