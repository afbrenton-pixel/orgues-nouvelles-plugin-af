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

/**
 * Objet attaché à une vignette : celui d'où elle a été créée, sinon celui de sa vignette d'origine (traduction
 * faite à la main), sinon (vignette créée à la main) celui que désigne son lien, si ce lien pointe vers un contenu du site.
 */
function on_vignettes_attached_post( $vignette_id ) {
	$source_id = (int) get_post_meta( $vignette_id, '_onv_source_id', true );
	if ( ! $source_id && function_exists( 'pll_get_post_translations' ) ) {
		// Vignette traduite à la main : objet de la vignette d'origine, dans la langue de la vignette si possible.
		foreach ( pll_get_post_translations( $vignette_id ) as $translation_id ) {
			if ( (int) $translation_id === (int) $vignette_id ) {
				continue;
			}
			$origin = (int) get_post_meta( $translation_id, '_onv_source_id', true );
			if ( ! $origin ) {
				$lien_origine = (string) get_post_meta( $translation_id, 'lien', true );
				$origin       = '' !== $lien_origine ? (int) url_to_postid( $lien_origine ) : 0;
			}
			if ( $origin ) {
				$lang      = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $vignette_id ) : '';
				$translated = ( $lang && function_exists( 'pll_get_post' ) ) ? (int) pll_get_post( $origin, $lang ) : 0;
				$source_id = $translated ? $translated : $origin;
				break;
			}
		}
	}
	if ( ! $source_id ) {
		$lien = (string) get_post_meta( $vignette_id, 'lien', true );
		if ( '' !== $lien ) {
			$source_id = (int) url_to_postid( $lien );
		}
	}
	return $source_id ? get_post( $source_id ) : null;
}

/**
 * Fiche de la vignette : lien vers l'objet (actualité, ressource, vidéo, magazine…) à partir duquel
 * la vignette a été créée. Boîte dans la colonne de droite ; absente si aucun objet n'est retrouvé.
 */
add_action(
	'add_meta_boxes_vignette_accueil',
	function ( $post ) {
		if ( ! on_vignettes_attached_post( $post->ID ) ) {
			return;
		}
		add_meta_box(
			'onv-objet-source',
			__( 'Objet source', 'orgues-nouvelles-plugin' ),
			function ( $vignette ) {
				$source = on_vignettes_attached_post( $vignette->ID );
				if ( ! $source ) {
					return;
				}
				$type  = get_post_type_object( $source->post_type );
				$label = $type ? $type->labels->singular_name : $source->post_type;
				printf(
					'<p><strong>%1$s</strong><br>%2$s (%3$s)</p>',
					esc_html( get_the_title( $source ) ),
					esc_html( $label ),
					esc_html( get_post_status_object( $source->post_status )->label )
				);
				if ( current_user_can( 'edit_post', $source->ID ) ) {
					printf(
						'<p><a class="button button-primary" href="%s">%s</a></p>',
						esc_url( get_edit_post_link( $source->ID, 'raw' ) ),
						esc_html__( 'Modifier l\'objet', 'orgues-nouvelles-plugin' )
					);
				}
				if ( 'publish' === $source->post_status ) {
					printf(
						'<p><a href="%s" target="_blank" rel="noopener">%s</a></p>',
						esc_url( get_permalink( $source ) ),
						esc_html__( 'Voir l\'objet sur le site', 'orgues-nouvelles-plugin' )
					);
				}
			},
			'vignette_accueil',
			'side',
			'high'
		);
	}
);
