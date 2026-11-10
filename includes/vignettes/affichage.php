<?php
/**
 * Affichage des vignettes d'accueil : éléments masqués au choix, vignette par vignette.
 *
 * Deux cases à cocher dans la fiche de la vignette (champs Pods) :
 * - `masquer_titre` : cache le titre (l'étiquette reste affichée) ;
 * - `afficher_description` : affiche le sous-titre et l'extrait (par défaut, seuls l'étiquette et le titre sont affichés).
 *
 * Le code ajoute les classes `onv-sans-titre` et `onv-sans-description` à la vignette (classes d'article,
 * donc présentes sur chaque élément du Loop Grid / Carrousel). Le CSS (assets/css/vignettes-accueil.css)
 * cache alors les widgets des modèles de boucle qui portent la classe `onv-w-titre` ou `onv-w-description`
 * (posée sur les widgets du modèle « standard » seulement : les grandes vignettes gardent leur texte).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'post_class',
	function ( $classes, $extra = array(), $post_id = 0 ) {
		if ( ! $post_id || 'vignette_accueil' !== get_post_type( $post_id ) ) {
			return $classes;
		}
		if ( get_post_meta( $post_id, 'masquer_titre', true ) ) {
			$classes[] = 'onv-sans-titre';
		}
		if ( ! get_post_meta( $post_id, 'afficher_description', true ) ) {
			$classes[] = 'onv-sans-description';
		}
		return $classes;
	},
	10,
	3
);
