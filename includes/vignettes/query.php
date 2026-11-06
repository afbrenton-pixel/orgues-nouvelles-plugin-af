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
