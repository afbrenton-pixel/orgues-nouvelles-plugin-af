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

/**
 * Langue de repli (Polylang).
 *
 * Sur une page qui n'est pas dans la langue par défaut (par exemple l'anglais), les vignettes
 * françaises dont le type de ressource est audio, partitions ou vidéos sont ajoutées à celles
 * de la langue courante : ce sont des contenus que l'on écoute ou regarde, la langue compte peu.
 * Les textes ne sont pas repris. Une vignette française qui a une traduction dans la langue
 * courante est remplacée par celle-ci.
 * Filtre `on_vignettes_types_partages` : slugs de `type_de_ressource` concernés.
 *
 * @return array|null Langue courante et langue par défaut, ou null s'il n'y a rien à faire.
 */
function on_vignettes_langues_repli() {
	if ( ! function_exists( 'pll_current_language' ) || ! function_exists( 'pll_default_language' ) ) {
		return null;
	}
	$cur = pll_current_language();
	$def = pll_default_language();
	if ( ! $cur || ! $def || $cur === $def ) {
		return null;
	}
	return array( $cur, $def );
}

/** Retire d'une tax_query les clauses de langue ajoutées par Polylang (taxonomie `language`). */
function on_vignettes_sans_langue( $tax_query ) {
	if ( ! is_array( $tax_query ) ) {
		return $tax_query;
	}
	$out = array();
	foreach ( $tax_query as $key => $clause ) {
		if ( is_array( $clause ) && isset( $clause['taxonomy'] ) && 'language' === $clause['taxonomy'] ) {
			continue;
		}
		if ( is_array( $clause ) && ! isset( $clause['taxonomy'] ) ) {
			$clause = on_vignettes_sans_langue( $clause );
			if ( ! array_diff_key( $clause, array( 'relation' => 1 ) ) ) {
				continue;
			}
		}
		$out[ $key ] = $clause;
	}
	return $out;
}

/** Vrai si la vignette est une ressource audio, partition ou vidéo. */
function on_vignettes_partageable( $id ) {
	$types = (array) apply_filters( 'on_vignettes_types_partages', array( 'audio', 'partitions', 'videos' ) );
	return has_term( $types, 'type_de_ressource', $id );
}

/**
 * Identifiants des vignettes : langue courante, plus le repli de la langue par défaut.
 *
 * @param array $args  Arguments de WP_Query (le tri est conservé).
 * @param array $langs Résultat de on_vignettes_langues_repli().
 */
function on_vignettes_ids_repli( array $args, array $langs ) {
	list( $cur, $def )        = $langs;
	$args['lang']             = $cur . ',' . $def;
	$args['suppress_filters'] = false;
	$ids                      = array_map( 'intval', ( new WP_Query( $args ) )->posts );
	$out                      = array();
	foreach ( $ids as $id ) {
		$lang = pll_get_post_language( $id );
		if ( $lang === $cur ) {
			$out[] = $id;
		} elseif ( $lang === $def && on_vignettes_partageable( $id ) && ! pll_get_post( $id, $cur ) ) {
			$out[] = $id;
		}
	}
	return $out;
}

/** Arguments de WP_Query : toutes les vignettes de la requête Elementor, par rang puis par date. */
function on_vignettes_args_rang( $query ) {
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
	$tax_query = on_vignettes_sans_langue( $query->get( 'tax_query' ) );
	if ( is_array( $tax_query ) && $tax_query ) {
		$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery
	}
	return $args;
}

/** Applique le repli de langue à une requête Elementor (ordre par rang puis date). */
function on_vignettes_applique_repli( $query, array $langs ) {
	static $running = false;
	if ( $running ) {
		return;
	}
	$running = true;
	try {
		$ids = on_vignettes_ids_repli( on_vignettes_args_rang( $query ), $langs );
		$query->set( 'meta_query', array() );
		$query->set( 'tax_query', array() );
		$query->set( 'lang', $langs[0] . ',' . $langs[1] );
		$query->set( 'post__in', $ids ? $ids : array( 0 ) );
		$query->set( 'orderby', 'post__in' );
	} catch ( \Throwable $e ) {
		unset( $e );
	}
	$running = false;
}

add_action(
	'elementor/query/vignettes_rang',
	function ( $query ) {
		$langs = on_vignettes_langues_repli();
		if ( $langs ) {
			on_vignettes_applique_repli( $query, $langs );
			return;
		}
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

/**
 * Grandes vignettes et vignettes standard dans des grilles séparées.
 *
 * Dans le Loop Grid, « ID de la requête » :
 *   vignettes_larges    → seulement les vignettes dont le champ « format » vaut « large »
 *                         (par rang puis date). Une grille par grande vignette : la 1ère avec
 *                         décalage 0, la suivante avec décalage 1, etc.
 *   vignettes_standard  → toutes les autres vignettes (rang puis date). Le nombre de vignettes
 *                         et le décalage de chaque grille se règlent dans le Loop Grid.
 * La zone (zone_vignette) se choisit dans le Loop Grid.
 */
function on_vignettes_split_query( $query, $want_large ) {
	static $running = false;
	if ( $running ) {
		return;
	}
	$running = true;
	try {
		$tax_query = $query->get( 'tax_query' );
		if ( on_vignettes_langues_repli() ) {
			$tax_query = on_vignettes_sans_langue( $tax_query );
		}
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
		$langs = on_vignettes_langues_repli();
		if ( $langs ) {
			$ids = on_vignettes_ids_repli( $args, $langs );
			$query->set( 'lang', $langs[0] . ',' . $langs[1] );
		} else {
			if ( function_exists( 'pll_current_language' ) && pll_current_language() ) {
				$args['lang']             = pll_current_language();
				$args['suppress_filters'] = false;
			}
			$ids = array_map( 'intval', ( new WP_Query( $args ) )->posts );
		}
		$result = array();
		foreach ( $ids as $id ) {
			$is_large = 'large' === get_post_meta( $id, 'format', true );
			if ( $is_large === $want_large ) {
				$result[] = $id;
			}
		}
		$query->set( 'meta_query', array() );
		$query->set( 'tax_query', array() );
		$query->set( 'post__in', $result ? $result : array( 0 ) );
		$query->set( 'orderby', 'post__in' );
	} catch ( \Throwable $e ) {
		unset( $e );
	}
	$running = false;
}

add_action(
	'elementor/query/vignettes_larges',
	function ( $query ) {
		on_vignettes_split_query( $query, true );
	}
);

add_action(
	'elementor/query/vignettes_standard',
	function ( $query ) {
		on_vignettes_split_query( $query, false );
	}
);

/**
 * Ressources du dernier numéro.
 *
 * Dans le Loop Grid, « ID de la requête » : vignettes_numero
 *
 * Ne garde que les vignettes dont le champ « numéro » est le plus élevé de la zone choisie
 * (équivalent de numero="auto" de l'ancien plugin), triées par rang puis par date.
 */
add_action(
	'elementor/query/vignettes_numero',
	function ( $query ) {
		static $running = false;
		if ( $running ) {
			return;
		}
		$running = true;
		try {
			$tax  = $query->get( 'tax_query' );
			if ( on_vignettes_langues_repli() ) {
				$tax = on_vignettes_sans_langue( $tax );
			}
			$args = array(
				'post_type'        => 'vignette_accueil',
				'post_status'      => 'publish',
				'posts_per_page'   => 200,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'orderby'          => 'date',
				'order'            => 'DESC',
			);
			if ( is_array( $tax ) && $tax ) {
				$args['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery
			}
			$langs = on_vignettes_langues_repli();
			if ( $langs ) {
				$args['orderby'] = array( 'date' => 'DESC' );
				$ids             = on_vignettes_ids_repli( $args, $langs );
				$query->set( 'lang', $langs[0] . ',' . $langs[1] );
			} else {
				$ids = array_map( 'intval', ( new WP_Query( $args ) )->posts );
			}
			$max = 0;
			foreach ( $ids as $id ) {
				$max = max( $max, (int) get_post_meta( $id, 'numero', true ) );
			}
			if ( $max > 0 ) {
				$keep = array();
				foreach ( $ids as $id ) {
					if ( (int) get_post_meta( $id, 'numero', true ) === $max ) {
						$rang   = (int) get_post_meta( $id, 'rang', true );
						$keep[] = array( $rang > 0 ? $rang : PHP_INT_MAX, count( $keep ), $id );
					}
				}
				usort(
					$keep,
					function ( $x, $y ) {
						return array( $x[0], $x[1] ) <=> array( $y[0], $y[1] );
					}
				);
				$query->set( 'tax_query', array() );
				$query->set( 'post__in', array_column( $keep, 2 ) );
				$query->set( 'orderby', 'post__in' );
			}
		} catch ( \Throwable $e ) {
			unset( $e );
		}
		$running = false;
	}
);

/** Mise en page des colonnes de la page d'accueil (titres de section, colonnes fixes, pubs). */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'on-vignettes-accueil',
			plugins_url( 'assets/css/vignettes-accueil.css', dirname( __DIR__, 2 ) . '/orgues-nouvelles-plugin.php' ),
			array(),
			(string) filemtime( dirname( __DIR__, 2 ) . '/assets/css/vignettes-accueil.css' )
		);
	}
);
