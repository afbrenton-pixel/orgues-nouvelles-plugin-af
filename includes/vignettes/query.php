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
		static $running = false;
		if ( $running ) {
			return;
		}
		$running = true;
		try {
			$tax_query = $query->get( 'tax_query' );
			$args      = array(
				'post_type'        => 'vignette_accueil',
				'post_status'      => 'publish',
				'posts_per_page'   => 200,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery
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
				),
				'orderby'          => array(
					'rang_clause' => 'ASC',
					'date'        => 'DESC',
				),
			);
			if ( is_array( $tax_query ) && $tax_query ) {
				$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
			}
			if ( function_exists( 'pll_current_language' ) && pll_current_language() ) {
				$args['lang'] = pll_current_language();
				$args['suppress_filters'] = false;
			}
			$ids = array_map( 'intval', ( new WP_Query( $args ) )->posts );

			$places = (array) apply_filters( 'on_vignettes_grandes_places', array( 1, 14 ) );
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

			// Dernière rangée complète : après la dernière grande vignette, un multiple de trois vignettes.
			$last_big = max( $places );
			$n        = count( $result );
			if ( $n > $last_big ) {
				$result = array_slice( $result, 0, $last_big + 3 * (int) floor( ( $n - $last_big ) / 3 ) );
			} elseif ( $n > 1 ) {
				$result = array_slice( $result, 0, 1 + 3 * (int) floor( ( $n - 1 ) / 3 ) );
			}

			$query->set( 'meta_query', array() );
			$query->set( 'tax_query', array() );
			$query->set( 'post__in', $result ? $result : array( 0 ) );
			$query->set( 'orderby', 'post__in' );
		} catch ( \Throwable $e ) {
			// En cas de problème, on garde la requête d'origine.
			unset( $e );
		}
		$running = false;
	}
);
