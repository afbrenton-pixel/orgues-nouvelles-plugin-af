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
 * (window.ON_MEDIA, indexé par l'identifiant de la vignette). Le script repère les vignettes
 * des Loop Grid Elementor par leur classe `e-loop-item-{identifiant}`.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** MP3 (dans l'ordre) ou vidéo Vimeo contenus dans la ressource source d'une vignette. */
function on_vignettes_media_for( $vignette_id ) {
	$source = (int) get_post_meta( $vignette_id, '_on_source_id', true );
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

/**
 * Titre d'une piste : le titre du média dans la médiathèque (celui que l'on modifie dans WordPress), pour que le texte affiché
 * corresponde au média. Si ce titre est vide ou n'est que le nom du fichier (« 01 », « piste_14 »), on prend l'étiquette ID3 du MP3.
 *
 * @param int   $aid  Identifiant de la pièce jointe.
 * @param array $meta Métadonnées WordPress du fichier audio (wp_get_attachment_metadata).
 * @return string
 */
function on_vignettes_track_title( $aid, $meta ) {
	$media = trim( (string) get_the_title( $aid ) );
	$id3   = ! empty( $meta['title'] ) ? trim( (string) $meta['title'] ) : '';
	$file  = pathinfo( (string) get_attached_file( $aid ), PATHINFO_FILENAME );
	$norm  = static function ( $t ) {
		return strtolower( trim( preg_replace( '/[\s_\-]+/', ' ', (string) $t ) ) );
	};
	if ( '' === $media || $norm( $media ) === $norm( $file ) ) {
		return '' !== $id3 ? $id3 : $media;
	}
	return $media;
}

/**
 * Pistes d'une vignette de format « playlist » : fichiers du champ Pods « pistes » (médiathèque), dans l'ordre du champ.
 * Le titre est celui du média dans la médiathèque (sinon l'étiquette ID3 du MP3) ; l'artiste et la durée viennent des étiquettes ID3 du MP3 (métadonnées WordPress).
 * Retourne null si la vignette n'a aucune piste lisible.
 */
function on_vignettes_playlist_for( $vignette_id ) {
	$raw = get_post_meta( $vignette_id, 'pistes' );
	$ids = array();
	foreach ( $raw as $v ) {
		foreach ( (array) $v as $item ) {
			$id = is_array( $item ) ? (int) ( $item['ID'] ?? 0 ) : (int) $item;
			if ( $id ) {
				$ids[] = $id;
			}
		}
	}
	$ids    = array_values( array_unique( $ids ) );
	$audio  = array();
	$tracks = array();
	foreach ( $ids as $aid ) {
		$url = wp_get_attachment_url( $aid );
		if ( ! $url || 0 !== strpos( (string) get_post_mime_type( $aid ), 'audio' ) ) {
			continue;
		}
		$meta     = wp_get_attachment_metadata( $aid );
		$meta     = is_array( $meta ) ? $meta : array();
		$title    = on_vignettes_track_title( $aid, $meta );
		$title    = preg_replace( '/^\s*\d{1,2}\s*[-.–]\s*/u', '', (string) $title ); // la liste a sa propre numérotation : « 01- Van Soldt » → « Van Soldt ».
		$audio[]  = esc_url_raw( $url );
		$tracks[] = array(
			// Texte brut : le JavaScript l'affiche avec textContent, un « &amp; » resterait visible tel quel (get_the_title() encode « & »).
			'title'  => html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, 'UTF-8' ),
			'artist' => ! empty( $meta['artist'] ) ? html_entity_decode( wp_strip_all_tags( (string) $meta['artist'] ), ENT_QUOTES, 'UTF-8' ) : '',
			'length' => ! empty( $meta['length_formatted'] ) ? (string) $meta['length_formatted'] : '',
		);
	}
	if ( ! $audio ) {
		return null;
	}
	return array(
		'audio'    => $audio,
		'tracks'   => $tracks,
		'video'    => '',
		'kind'     => 'audio',
		'playlist' => 1,
		'visibles' => max( 1, (int) apply_filters( 'on_vignettes_playlist_visibles', 5 ) ),
	);
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
				'meta_key'         => '_on_source_type', // phpcs:ignore WordPress.DB.SlowDBQuery
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
		$playlists = get_posts(
			array(
				'post_type'        => 'vignette_accueil',
				'post_status'      => 'publish',
				'posts_per_page'   => 20,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => false,
				'meta_key'         => 'format', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => 'playlist', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $playlists as $id ) {
			$info = on_vignettes_playlist_for( $id );
			if ( $info ) {
				$map[ (int) $id ] = $info;
			}
		}
		if ( ! $map ) {
			return;
		}
		$main = dirname( __DIR__, 2 ) . '/orgues-nouvelles-plugin.php';
		wp_enqueue_script( 'on-vignettes-player', plugins_url( 'assets/js/vignettes-player.js', $main ), array(), (string) filemtime( dirname( __DIR__, 2 ) . '/assets/js/vignettes-player.js' ), true );
		// Textes du lecteur : écrits en français, traduits par le système de WordPress (languages/*.po), dans la langue de la page.
		$texts = array(
			'play'        => __( 'Lecture', 'orgues-nouvelles' ),
			'pause'       => __( 'Pause', 'orgues-nouvelles' ),
			'previous'    => __( 'Morceau précédent', 'orgues-nouvelles' ),
			'next'        => __( 'Morceau suivant', 'orgues-nouvelles' ),
			'video'       => __( 'Vidéo', 'orgues-nouvelles' ),
			'close_video' => __( 'Fermer la vidéo', 'orgues-nouvelles' ),
			/* translators: %d: number of tracks of the playlist. */
			'show_all'    => __( 'Voir les %d pistes', 'orgues-nouvelles' ),
			'show_fewer'  => __( 'Réduire', 'orgues-nouvelles' ),
		);
		wp_add_inline_script( 'on-vignettes-player', 'window.ON_I18N=' . wp_json_encode( $texts ) . ';window.ON_MEDIA=' . wp_json_encode( $map ) . ';', 'before' );
	}
);
