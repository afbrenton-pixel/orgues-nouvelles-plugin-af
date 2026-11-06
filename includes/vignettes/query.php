<?php
/**
 * Vignettes d'accueil : ordre des Loop Grid Elementor.
 *
 * Dans le Loop Grid, section « Requête », champ « ID de la requête » :
 *   vignettes_rang  → les vignettes qui ont un rang (champ « rang ») viennent
 *                     d'abord, du plus petit au plus grand ; les autres suivent,
 *                     de la plus récente à la plus ancienne.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'elementor/query/vignettes_rang',
	function ( $query ) {
		$query->set(
			'meta_query',
			array(
				'relation'    => 'OR',
				'rang_clause' => array(
					'key'     => 'rang',
					'type'    => 'NUMERIC',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => 'rang',
					'compare' => 'NOT EXISTS',
				),
			)
		);
		// Sans rang (ou rang 0) : après les autres.
		$query->set(
			'orderby',
			array(
				'rang_clause' => 'ASC',
				'date'        => 'DESC',
			)
		);
	}
);

/**
 * Grandes vignettes aux bonnes places.
 *
 * Dans le Loop Grid, « ID de la requête » : vignettes_centre
 *
 * Les vignettes dont le champ « format » vaut « large » sont mises aux places
 * 1 et 6 de la liste (filtre `on_vignettes_grandes_places` pour changer).
 * Elles sont prises dans l'ordre des rangs, puis des dates ; les autres vignettes
 * suivent le même ordre. Le modèle alternatif du Loop Grid (positions 1 et 6)
 * tombe alors sur les bonnes vignettes.
 */
add_action(
	'elementor/query/vignettes_centre',
	function ( $query ) {
		$ids = get_posts(
			array(
				'post_type'        => 'vignette_accueil',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'tax_query'        => $query->get( 'tax_query' ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_query'       => array(
					'relation'    => 'OR',
					'rang_clause' => array(
						'key'     => 'rang',
						'type'    => 'NUMERIC',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'rang',
						'compare' => 'NOT EXISTS',
					),
				), // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'          => array(
					'rang_clause' => 'ASC',
					'date'        => 'DESC',
				),
				'suppress_filters' => false,
			)
		);

		$places = apply_filters( 'on_vignettes_grandes_places', array( 1, 6 ) );
		$bigs   = array();
		foreach ( $ids as $id ) {
			if ( 'large' === get_post_meta( $id, 'format', true ) ) {
				$bigs[] = $id;
			}
		}
		$bigs   = array_slice( $bigs, 0, count( $places ) );
		$others = array_values( array_diff( $ids, $bigs ) );

		$result = array();
		$total  = count( $ids );
		for ( $slot = 1; $slot <= $total; $slot++ ) {
			if ( in_array( $slot, $places, true ) && $bigs ) {
				$result[] = array_shift( $bigs );
			} elseif ( $others ) {
				$result[] = array_shift( $others );
			}
		}
		$result = array_merge( $result, $bigs, $others );

		$query->set( 'meta_query', array() );
		$query->set( 'tax_query', array() );
		$query->set( 'post__in', $result ? $result : array( 0 ) );
		$query->set( 'orderby', 'post__in' );
	}
);
