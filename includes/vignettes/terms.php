<?php
/**
 * Vignettes d'accueil : termes des taxonomies `zone_vignette` et `etiquette_vignette`.
 *
 * Le type de contenu et les taxonomies sont définis dans Pods (voir docs/vignettes.md).
 * Ce fichier crée seulement les termes de départ, une fois, sans doublon.
 *
 * @package Orgues-Nouvelles Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Numéro de version de la liste de termes : le changer relance la création (les termes existants ne sont jamais modifiés). */
const ON_VIGNETTES_TERMS_VERSION = '2';

/**
 * Zones : emplacement précis d'une vignette sur la page d'accueil.
 * slug => libellé.
 */
function on_vignettes_default_zones() {
	return array(
		'centre-actus'        => 'Centre – Actus',
		'centre-publications' => 'Centre – Publications',
		'centre-videos'       => 'Centre – Vidéos',
		'gauche-fixe'         => 'Gauche – fixe en haut',
		'gauche-ressources'   => 'Gauche – Ressources',
		'droite-fixe'         => 'Droite – fixe en haut',
		'droite-pubs'         => 'Droite – Pubs',
	);
}

/**
 * Étiquettes : le mot de l'étiquette orange (version française).
 * Les étiquettes Audio, Textes et Partitions viennent du type de ressource, elles ne sont pas ici.
 * slug => libellé.
 */
function on_vignettes_default_etiquettes() {
	return array(
		'publications' => 'Publications',
		'cd'           => 'CD',
		'sorties-cd'   => 'Sorties CD',
		'videos'       => 'Vidéos',
		'echos'        => 'Échos',
		'blog'         => 'Blog',
		'pub'          => 'Pub',
		'la-revue'     => 'La Revue',
		'actus'        => 'Actus',
		'newsletter'   => 'Newsletter',
		'ressources'   => 'Ressources',
		'photos'       => 'Photos',
	);
}

/**
 * Traductions anglaises des étiquettes (slug français => libellé anglais).
 * Utilisées seulement si Polylang est actif.
 */
function on_vignettes_etiquettes_en() {
	return array(
		'echos' => 'Echoes',
	);
}

/** Rend le type de contenu et les étiquettes traduisibles avec Polylang. Les zones ne le sont pas : une seule zone sert aux deux langues. */
add_filter(
	'pll_get_post_types',
	function ( $post_types ) {
		$post_types['vignette_accueil'] = 'vignette_accueil';
		return $post_types;
	}
);
add_filter(
	'pll_get_taxonomies',
	function ( $taxonomies ) {
		$taxonomies['etiquette_vignette'] = 'etiquette_vignette';
		return $taxonomies;
	}
);

/** Crée les termes manquants (une fois par version de liste). */
function on_vignettes_create_terms() {
	if ( get_option( 'on_vignettes_terms_version' ) === ON_VIGNETTES_TERMS_VERSION ) {
		return;
	}
	if ( ! taxonomy_exists( 'zone_vignette' ) || ! taxonomy_exists( 'etiquette_vignette' ) ) {
		return; // Pods n'a pas (encore) créé les taxonomies : on réessaiera au prochain chargement.
	}

	foreach ( on_vignettes_default_zones() as $slug => $label ) {
		on_vignettes_ensure_term( $slug, $label, 'zone_vignette' );
	}

	$has_polylang = function_exists( 'pll_set_term_language' ) && function_exists( 'pll_save_term_translations' ) && function_exists( 'pll_default_language' );
	$fr           = $has_polylang ? pll_default_language( 'slug' ) : '';
	$english      = on_vignettes_etiquettes_en();

	foreach ( on_vignettes_default_etiquettes() as $slug => $label ) {
		$term_id = on_vignettes_ensure_term( $slug, $label, 'etiquette_vignette' );
		if ( ! $term_id || ! $has_polylang ) {
			continue;
		}
		if ( ! pll_get_term_language( $term_id ) ) {
			pll_set_term_language( $term_id, $fr );
		}
		if ( isset( $english[ $slug ] ) && ! pll_get_term( $term_id, 'en' ) ) {
			$en_id = on_vignettes_ensure_term( $slug . '-en', $english[ $slug ], 'etiquette_vignette' );
			if ( $en_id ) {
				pll_set_term_language( $en_id, 'en' );
				pll_save_term_translations(
					array(
						$fr  => $term_id,
						'en' => $en_id,
					)
				);
			}
		}
	}

	update_option( 'on_vignettes_terms_version', ON_VIGNETTES_TERMS_VERSION, false );
}
add_action( 'init', 'on_vignettes_create_terms', 99 );

/**
 * Retourne l'identifiant du terme (existant ou créé). Ne modifie jamais un terme existant.
 *
 * @param string $slug     Slug du terme.
 * @param string $label    Libellé.
 * @param string $taxonomy Taxonomie.
 * @return int 0 en cas d'échec.
 */
function on_vignettes_ensure_term( $slug, $label, $taxonomy ) {
	$existing = get_term_by( 'slug', $slug, $taxonomy );
	if ( $existing ) {
		return (int) $existing->term_id;
	}
	$result = wp_insert_term( $label, $taxonomy, array( 'slug' => $slug ) );
	if ( is_wp_error( $result ) ) {
		return 0;
	}
	return (int) $result['term_id'];
}
