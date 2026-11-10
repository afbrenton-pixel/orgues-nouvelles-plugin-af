<?php
/**
 * Lecteur audio et vidéo des vignettes d'accueil.
 *
 * Reprend le comportement de l'ancien plugin « Vignettes d'accueil » :
 * - une ressource contenant des MP3 : clic sur l'image ou le pictogramme = lecture, MP3 enchaînés ;
 * - une ressource contenant une vidéo Vimeo : clic sur l'image = lecteur intégré ;
 * - pictogramme (audio, textes, partitions) à côté de l'étiquette des ressources compactes.
 *
 * Les adresses sont trouvées dans le contenu de la ressource d'origine et passées au script
 * (window.ONV_MEDIA, indexé par l'identifiant de la vignette). Le script repère les vignettes
 * des Loop Grid Elementor par leur classe `e-loop-item-{identifiant}`.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** MP3 (dans l'ordre) ou vidéo Vimeo contenus dans la ressource source d'une vignette. */
function on_vignettes_media_for( $vignette_id ) {
	$source = (int) get_post_meta( $vignette_id, '_onv_source_id', true );
	$post   = $source ? get_post( $source ) : null;
	if ( ! $post || 'ressource' !== $post->post_type || ! on_vignettes_source_is_public( $post ) ) {
		return null; // source privée, en brouillon ou protégée par mot de passe : aucune adresse exportée.
	}
	$c   = (string) $post->post_content;
	$out = array(
		'audio' => array(),
		'video' => '',
		'kind'  => '',
	);
	if ( preg_match_all( '#https?://[^"\'\s<>]+?\.mp3#i', $c, $m ) ) {
		$out['audio'] = array_values( array_unique( array_map( 'esc_url_raw', $m[0] ) ) );
	} elseif ( preg_match( '#https?://player\.vimeo\.com/video/\d+[^"\'\s<>]*#i', $c, $v ) ) {
		$out['video'] = html_entity_decode( $v[0] );
	} elseif ( preg_match( '#vimeo\.com/(\d+)(?:/([0-9a-f]+))?#i', $c, $v ) ) {
		// Lien de partage vimeo.com/numéro/code → adresse du lecteur (sans suivi : dnt=1).
		$out['video'] = 'https://player.vimeo.com/video/' . $v[1] . '?' . ( ! empty( $v[2] ) ? 'h=' . $v[2] . '&dnt=1' : 'dnt=1' );
	}
	$types = get_the_terms( $source, 'type_de_ressource' );
	if ( $types && ! is_wp_error( $types ) ) {
		foreach ( $types as $t ) {
			$slug = sanitize_title( $t->name );
			if ( in_array( $slug, array( 'audio', 'textes', 'partitions' ), true ) ) {
				$out['kind'] = $slug;
				break;
			}
		}
	}
	return ( $out['audio'] || $out['video'] || $out['kind'] ) ? $out : null;
}

add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! is_singular( 'page' ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'        => 'vignette_accueil',
				'post_status'      => 'publish',
				'posts_per_page'   => 300,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'lang'             => '', // Polylang : toutes les langues (la page anglaise reprend des vignettes françaises).
				'meta_key'         => '_onv_source_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => 'ressource', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$map = array();
		foreach ( $ids as $id ) {
			$info = on_vignettes_media_for( $id );
			if ( $info ) {
				$map[ (int) $id ] = $info;
			}
		}
		if ( ! $map ) {
			return;
		}
		$main = dirname( __DIR__, 2 ) . '/orgues-nouvelles-plugin.php';
		wp_enqueue_script( 'on-vignettes-player', plugins_url( 'assets/js/vignettes-player.js', $main ), array(), (string) filemtime( dirname( __DIR__, 2 ) . '/assets/js/vignettes-player.js' ), true );
		wp_add_inline_script( 'on-vignettes-player', 'window.ONV_MEDIA=' . wp_json_encode( $map ) . ';', 'before' );
	}
);
