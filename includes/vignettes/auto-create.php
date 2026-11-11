<?php
/**
 * Vignettes d'accueil : création et mise à jour automatiques à partir d'un contenu.
 *
 * À la publication d'un article, d'une ressource, d'un produit ou d'un CD, la vignette
 * correspondante est créée (type de contenu Pods `vignette_accueil`).
 *
 * Règles :
 * - Sans image (image à la une, sinon première image du texte), la vignette reste en
 *   brouillon : l'équipe ajoute une image ou change le contenu. Seules les ressources
 *   (petites vignettes sans photo) en sont dispensées.
 * - Une vignette modifiée à la main n'est plus touchée par la mise à jour automatique.
 * - Un contenu dépublié met sa vignette en brouillon ; supprimé, la vignette est supprimée.
 *
 * Les valeurs internes `_onv_source_id`, `_onv_source_type`, `_onv_locked`, `_onv_autodraft`
 * et `_onv_sans_image` sont gérées par le code, hors Pods.
 *
 * @package Orgues-Nouvelles Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ON_VIGNETTES_CPT = 'vignette_accueil';

/** Types de contenu qui génèrent une vignette automatiquement. */
function on_vignettes_source_types() {
	return (array) apply_filters( 'on_vignettes_source_types', array( 'post', 'ressource', 'product', 'cd' ) );
}

/** Vrai pendant que le code écrit lui-même une vignette (évite de la verrouiller par erreur). */
function on_vignettes_busy( $set = null ) {
	static $busy = false;
	if ( null !== $set ) {
		$busy = (bool) $set;
	}
	return $busy;
}

/* ----------------------------------------------------------------------
 * Branchements
 * -------------------------------------------------------------------- */

add_action( 'save_post', 'on_vignettes_on_source_saved', 30, 2 );
add_action( 'rest_api_init', 'on_vignettes_hook_rest_saves' );
add_action( 'before_delete_post', 'on_vignettes_on_source_deleted' );
add_action( 'save_post_' . ON_VIGNETTES_CPT, 'on_vignettes_lock_if_edited_by_hand', 99, 2 );
add_action( 'admin_menu', 'on_vignettes_menu_badge', 99 );

function on_vignettes_on_source_saved( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! in_array( $post->post_type, on_vignettes_source_types(), true ) ) {
		return;
	}
	on_vignettes_upsert( $post );
}

/**
 * Éditeur de blocs : `save_post` part avant l'enregistrement de l'image à la une et des taxonomies par
 * l'API REST. On resynchronise donc la vignette une fois l'enregistrement complet (`rest_after_insert_{type}`).
 */
function on_vignettes_hook_rest_saves() {
	foreach ( on_vignettes_source_types() as $type ) {
		add_action( 'rest_after_insert_' . $type, 'on_vignettes_on_rest_saved', 30, 1 );
	}
}

function on_vignettes_on_rest_saved( $post ) {
	if ( $post instanceof WP_Post ) {
		on_vignettes_on_source_saved( $post->ID, $post );
	}
}

/** Un contenu est-il visible de tous ? Publié et sans mot de passe (les sources protégées ne donnent pas de vignette publique). */
function on_vignettes_source_is_public( $post ) {
	return $post && 'publish' === $post->post_status && '' === (string) $post->post_password;
}

function on_vignettes_on_source_deleted( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, on_vignettes_source_types(), true ) ) {
		return;
	}
	foreach ( on_vignettes_find_all( $post_id ) as $vid ) {
		on_vignettes_busy( true );
		wp_delete_post( $vid, true );
		on_vignettes_busy( false );
	}
}

/** Une vignette enregistrée depuis son écran d'édition devient « modifiée à la main ». */
function on_vignettes_lock_if_edited_by_hand( $post_id, $post ) {
	if ( on_vignettes_busy() || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! isset( $_POST['post_ID'] ) || (int) $_POST['post_ID'] !== (int) $post_id ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	if ( get_post_meta( $post_id, '_onv_source_id', true ) ) {
		update_post_meta( $post_id, '_onv_locked', 1 );
		delete_post_meta( $post_id, '_onv_sans_image' );
	}
}

/* ----------------------------------------------------------------------
 * Création / mise à jour
 * -------------------------------------------------------------------- */

/**
 * Identifiant de la vignette liée à un contenu (tous statuts), 0 si aucune.
 *
 * Une même source peut avoir plusieurs vignettes : la sienne, et une « jumelle » par langue secondaire
 * (voir on_vignettes_sync_twins). Sans langue donnée, on cherche la vignette dans la langue de la source.
 */
function on_vignettes_find( $source_id, $lang = null ) {
	if ( null === $lang ) {
		$lang = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $source_id ) : '';
	}
	$ids = get_posts(
		array(
			'post_type'        => ON_VIGNETTES_CPT,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'numberposts'      => 1,
			'fields'           => 'ids',
			'meta_key'         => '_onv_source_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => (int) $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			'lang'             => (string) $lang,
			'suppress_filters' => false,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/** Toutes les vignettes d'un contenu (la sienne et ses jumelles, toutes langues, tous statuts). */
function on_vignettes_find_all( $source_id ) {
	return array_map(
		'intval',
		get_posts(
			array(
				'post_type'        => ON_VIGNETTES_CPT,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'meta_key'         => '_onv_source_id', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => (int) $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery
				'lang'             => '',
				'suppress_filters' => false,
			)
		)
	);
}

/* ----------------------------------------------------------------------
 * Vignettes jumelles dans les autres langues
 * -------------------------------------------------------------------- */

/** Langues secondaires (Polylang) : toutes sauf la langue par défaut. */
function on_vignettes_twin_langs() {
	if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_default_language' ) ) {
		return array();
	}
	return array_values( array_diff( (array) pll_languages_list(), array( pll_default_language() ) ) );
}

/**
 * Une ressource audio, partition ou vidéo reçoit une vignette jumelle dans chaque autre langue
 * (contenus que l'on écoute ou regarde : la langue compte peu). Les textes n'en ont pas.
 * Filtre `on_vignettes_types_partages` : slugs de `type_de_ressource` concernés.
 */
function on_vignettes_is_shareable( $post ) {
	if ( ! $post || 'ressource' !== $post->post_type ) {
		return false;
	}
	if ( on_vignettes_is_video( $post ) ) {
		return true;
	}
	$types = (array) apply_filters( 'on_vignettes_types_partages', array( 'audio', 'partitions', 'videos' ) );
	return (bool) has_term( $types, 'type_de_ressource', $post->ID );
}

/**
 * Crée ou met à jour les vignettes jumelles d'une vignette de la langue par défaut.
 *
 * - La jumelle garde la même source (le lecteur lit donc le même MP3 ou la même vidéo), le même lien, la même
 *   image, le même numéro ; son étiquette et ses types sont ceux de la langue (ou, à défaut, les mêmes).
 * - Elle est créée publiée : pour ne pas l'afficher (vidéo très parlée en français, par exemple), il suffit de la
 *   passer en brouillon ou à la corbeille. Une jumelle modifiée à la main n'est plus touchée.
 * - Si la ressource a une vraie traduction dans la langue, celle-ci a sa propre vignette : pas de jumelle.
 */
function on_vignettes_sync_twins( $source, $vid ) {
	if ( ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_save_post_translations' ) || ! on_vignettes_is_shareable( $source ) ) {
		return;
	}
	$def = function_exists( 'pll_default_language' ) ? pll_default_language() : '';
	if ( ! $def || pll_get_post_language( $source->ID ) !== $def || pll_get_post_language( $vid ) !== $def ) {
		return;
	}
	$status = get_post_status( $vid );
	foreach ( on_vignettes_twin_langs() as $lang ) {
		if ( function_exists( 'pll_get_post' ) && pll_get_post( $source->ID, $lang ) ) {
			continue; // vraie traduction de la ressource : elle a sa propre vignette.
		}
		$twin = on_vignettes_find( $source->ID, $lang );
		if ( $twin && ( 'trash' === get_post_status( $twin ) || get_post_meta( $twin, '_onv_locked', true ) ) ) {
			continue; // masquée ou retouchée à la main.
		}
		$fr   = get_post( $vid );
		$args = array(
			'post_type'     => ON_VIGNETTES_CPT,
			'post_title'    => $fr->post_title,
			'post_excerpt'  => $fr->post_excerpt,
			'post_date'     => $fr->post_date,
			'post_date_gmt' => $fr->post_date_gmt,
		);
		on_vignettes_busy( true );
		if ( $twin ) {
			$args['ID']          = $twin;
			$args['post_status'] = $status ? $status : 'publish'; // une jumelle non retouchée à la main suit sa vignette française (une retouche la verrouille).
			wp_update_post( $args );
		} else {
			$args['post_status'] = $status ? $status : 'publish';
			$twin                = wp_insert_post( $args );
			if ( ! $twin || is_wp_error( $twin ) ) {
				on_vignettes_busy( false );
				continue;
			}
			pll_set_post_language( $twin, $lang );
		}
		on_vignettes_busy( false );
		foreach ( array( 'sous_titre', 'lien', 'format', 'image_cote', 'cadrage', 'rang', 'numero' ) as $key ) {
			$val = get_post_meta( $vid, $key, true );
			if ( '' === $val ) {
				delete_post_meta( $twin, $key );
			} else {
				update_post_meta( $twin, $key, $val );
			}
		}
		update_post_meta( $twin, '_onv_source_id', (int) $source->ID );
		update_post_meta( $twin, '_onv_source_type', $source->post_type );
		update_post_meta( $twin, '_onv_twin', 1 );
		$thumb = get_post_thumbnail_id( $vid );
		if ( $thumb ) {
			set_post_thumbnail( $twin, $thumb );
		} else {
			delete_post_thumbnail( $twin );
		}
		foreach ( array( 'zone_vignette', 'etiquette_vignette', 'type_de_ressource' ) as $tax ) {
			$ids = array();
			foreach ( (array) wp_get_object_terms( $vid, $tax, array( 'fields' => 'ids' ) ) as $tid ) {
				$ids[] = 'zone_vignette' === $tax ? (int) $tid : on_vignettes_term_in_lang( $tid, $lang );
			}
			wp_set_object_terms( $twin, $ids, $tax );
		}
		$links = (array) pll_get_post_translations( $vid );
		$links[ $def ]  = $vid;
		$links[ $lang ] = $twin;
		pll_save_post_translations( $links );
	}
}

/**
 * Image de la vignette : image à la une, sinon première image du texte.
 *
 * @return int Identifiant de la pièce jointe, 0 si aucune.
 */
function on_vignettes_image_id( $post ) {
	// Ressource vidéo : la miniature Vimeo est le premier choix (sinon l'image de la ressource).
	if ( 'ressource' === $post->post_type ) {
		$vimeo = on_vignettes_vimeo_image_id( $post );
		if ( $vimeo ) {
			return $vimeo;
		}
	}
	$thumb = (int) get_post_thumbnail_id( $post->ID );
	if ( $thumb ) {
		return $thumb;
	}
	$content = (string) $post->post_content;
	if ( preg_match( '/wp-image-(\d+)/', $content, $m ) && get_post_type( (int) $m[1] ) === 'attachment' ) {
		return (int) $m[1];
	}
	if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m ) ) {
		$id = (int) attachment_url_to_postid( $m[1] );
		if ( $id ) {
			return $id;
		}
	}
	return 0;
}

/**
 * Miniature Vimeo d'une ressource vidéo, importée dans la médiathèque (une seule fois).
 * Retourne l'identifiant de l'image, ou 0 s'il n'y a pas de vidéo Vimeo ou si Vimeo ne répond pas.
 */
function on_vignettes_vimeo_image_id( $post ) {
	$c = (string) $post->post_content;
	if ( preg_match( '#player\.vimeo\.com/video/(\d+)#i', $c, $v ) || preg_match( '#vimeo\.com/(\d+)#i', $c, $v ) ) {
		$vid = $v[1];
	} else {
		return 0;
	}
	if ( preg_match( '#https?://[^"\'\s<>]+?\.mp3#i', $c ) ) {
		return 0; // une ressource audio garde son image.
	}
	$cached = (int) get_post_meta( $post->ID, '_onv_vimeo_thumb_' . $vid, true );
	if ( $cached && 'attachment' === get_post_type( $cached ) ) {
		return $cached;
	}
	if ( get_transient( 'onv_vimeo_fail_' . $vid ) ) {
		return 0;
	}
	$hash = '';
	if ( preg_match( '#vimeo\.com/(?:video/)?' . $vid . '(?:/|\?h=)([0-9a-f]+)#i', $c, $h ) ) {
		$hash = $h[1];
	}
	$url = 'https://vimeo.com/' . $vid . ( $hash ? '/' . $hash : '' );
	$res = wp_remote_get( 'https://vimeo.com/api/oembed.json?width=1280&url=' . rawurlencode( $url ), array( 'timeout' => 8 ) );
	$thumb = '';
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$data  = json_decode( wp_remote_retrieve_body( $res ), true );
		$thumb = is_array( $data ) && ! empty( $data['thumbnail_url'] ) ? $data['thumbnail_url'] : '';
	}
	if ( ! $thumb ) {
		set_transient( 'onv_vimeo_fail_' . $vid, 1, HOUR_IN_SECONDS );
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$att = media_sideload_image( preg_replace( '#_\d+x\d+(\.\w+)?$#', '_1280$1', $thumb ), 0, get_the_title( $post ) . ' (miniature Vimeo)', 'id' );
	if ( is_wp_error( $att ) || ! $att ) {
		$att = media_sideload_image( $thumb, 0, get_the_title( $post ) . ' (miniature Vimeo)', 'id' );
	}
	if ( is_wp_error( $att ) || ! $att ) {
		set_transient( 'onv_vimeo_fail_' . $vid, 1, HOUR_IN_SECONDS );
		return 0;
	}
	update_post_meta( $post->ID, '_onv_vimeo_thumb_' . $vid, (int) $att );
	return (int) $att;
}

/** Récupérer la miniature Vimeo de cette ressource demandera-t-il un appel réseau ? (vidéo Vimeo, pas déjà importée, pas d'échec récent) */
function on_vignettes_vimeo_will_fetch( $post ) {
	$c = (string) $post->post_content;
	if ( ! preg_match( '#player\.vimeo\.com/video/(\d+)#i', $c, $v ) && ! preg_match( '#vimeo\.com/(\d+)#i', $c, $v ) ) {
		return false;
	}
	if ( preg_match( '#https?://[^"\'\s<>]+?\.mp3#i', $c ) ) {
		return false;
	}
	$cached = (int) get_post_meta( $post->ID, '_onv_vimeo_thumb_' . $v[1], true );
	if ( $cached && 'attachment' === get_post_type( $cached ) ) {
		return false;
	}
	return ! get_transient( 'onv_vimeo_fail_' . $v[1] );
}

/**
 * Contrôle qualité d'une image de vignette : retourne '' si elle est correcte,
 * sinon la raison ('noire' ou 'blanche') quand l'image est quasi uniforme.
 * Seuils volontairement stricts pour ne pas écarter une pochette sombre mais réelle.
 */
function on_vignettes_image_problem( $image_id ) {
	$file = $image_id ? get_attached_file( $image_id ) : '';
	if ( ! $file || ! file_exists( $file ) || ! function_exists( 'imagecreatefromstring' ) ) {
		return '';
	}
	$data = @file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	$im   = $data ? @imagecreatefromstring( $data ) : false;
	if ( ! $im ) {
		return '';
	}
	$small = imagecreatetruecolor( 24, 24 );
	imagecopyresampled( $small, $im, 0, 0, 0, 0, 24, 24, imagesx( $im ), imagesy( $im ) );
	$vals = array();
	for ( $x = 0; $x < 24; $x++ ) {
		for ( $y = 0; $y < 24; $y++ ) {
			$c      = imagecolorat( $small, $x, $y );
			$vals[] = ( ( ( $c >> 16 ) & 255 ) + ( ( $c >> 8 ) & 255 ) + ( $c & 255 ) ) / 3;
		}
	}
	imagedestroy( $im );
	imagedestroy( $small );
	$mean = array_sum( $vals ) / count( $vals );
	$var  = 0;
	foreach ( $vals as $v ) {
		$var += ( $v - $mean ) * ( $v - $mean );
	}
	$std = sqrt( $var / count( $vals ) );
	if ( $mean < 12 && $std < 12 ) {
		return 'noire';
	}
	if ( $mean > 245 && $std < 8 ) {
		return 'blanche';
	}
	return '';
}

/** Cette source a-t-elle besoin d'une image de qualité ? (tout sauf les ressources audio/textes/partitions) */
function on_vignettes_needs_image( $post ) {
	if ( 'ressource' !== $post->post_type ) {
		return true;
	}
	return (bool) preg_match( '#vimeo\.com/#i', (string) $post->post_content ) && ! preg_match( '#\.mp3#i', (string) $post->post_content );
}

/** Ressource dont le contenu est une vidéo Vimeo (et pas des MP3). */
function on_vignettes_is_video( $post ) {
	if ( 'ressource' !== $post->post_type ) {
		return false;
	}
	$c = (string) $post->post_content;
	return (bool) preg_match( '#vimeo\.com/#i', $c ) && ! preg_match( '#\.mp3#i', $c );
}

/** Zone (terme de `zone_vignette`) d'un contenu. */
function on_vignettes_zone_for( $post ) {
	if ( on_vignettes_is_video( $post ) ) {
		return 'centre-videos'; // toutes les vidéos ont leur rubrique.
	}
	if ( 'product' === $post->post_type || 'cd' === $post->post_type ) {
		return 'centre-publications';
	}
	if ( 'ressource' === $post->post_type ) {
		$types = get_the_terms( $post->ID, 'type_de_ressource' );
		if ( $types && ! is_wp_error( $types ) ) {
			foreach ( $types as $t ) {
				if ( in_array( sanitize_title( $t->name ), array( 'audio', 'textes', 'partitions' ), true ) ) {
					return 'gauche-ressources';
				}
			}
		}
	}
	return 'centre-actus';
}

/** Étiquette (slug d'un terme de `etiquette_vignette`) d'un contenu. */
function on_vignettes_etiquette_for( $post ) {
	switch ( $post->post_type ) {
		case 'product':
			return 'publications';
		case 'cd':
			return 'cd';
		case 'ressource':
			return 'ressources';
		default:
			foreach ( (array) get_the_category( $post->ID ) as $cat ) {
				$slug = sanitize_title( $cat->name );
				if ( 'cd' === $slug ) {
					return 'sorties-cd';
				}
				if ( term_exists( $slug, 'etiquette_vignette' ) && 'uncategorized' !== $slug ) {
					return $slug;
				}
			}
			return 'actus';
	}
}

function on_vignettes_excerpt_for( $post ) {
	if ( has_excerpt( $post ) ) {
		return wp_strip_all_tags( $post->post_excerpt );
	}
	$text = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
	return wp_trim_words( $text, 28, '…' );
}

function on_vignettes_soustitre_for( $post ) {
	if ( in_array( $post->post_type, array( 'product', 'cd' ), true ) ) {
		return '';
	}
	$date = get_the_date( 'j F Y', $post );
	if ( 'post' === $post->post_type ) {
		$author = get_the_author_meta( 'display_name', $post->post_author );
		return trim( $author . ' – ' . $date, ' –' );
	}
	return $date;
}

/** Terme de la bonne langue (Polylang), sinon le terme donné. */
function on_vignettes_term_in_lang( $term_id, $lang ) {
	if ( $lang && function_exists( 'pll_get_term' ) ) {
		$translated = pll_get_term( $term_id, $lang );
		if ( $translated ) {
			return (int) $translated;
		}
	}
	return (int) $term_id;
}

/** Crée ou met à jour la vignette liée à un contenu. */
function on_vignettes_upsert( $post ) {
	if ( ! post_type_exists( ON_VIGNETTES_CPT ) ) {
		return; // Pods pas encore chargé ou type absent.
	}
	$existing = on_vignettes_find( $post->ID );

	// Contenu dépublié ou protégé par mot de passe : la vignette passe en brouillon, même verrouillée
	// (sinon elle resterait publique avec l'extrait d'un contenu qui ne l'est plus).
	if ( ! on_vignettes_source_is_public( $post ) ) {
		foreach ( on_vignettes_find_all( $post->ID ) as $vid_all ) {
			if ( 'publish' === get_post_status( $vid_all ) ) {
				on_vignettes_busy( true );
				wp_update_post(
					array(
						'ID'          => $vid_all,
						'post_status' => 'draft',
					)
				);
				on_vignettes_busy( false );
				update_post_meta( $vid_all, '_onv_autodraft', 1 );
			}
		}
		return;
	}

	$image_id   = on_vignettes_image_id( $post );
	$needs_img  = on_vignettes_needs_image( $post );
	$problem    = ( $needs_img && $image_id ) ? on_vignettes_image_problem( $image_id ) : '';
	if ( $problem ) {
		$image_id = 0; // image inutilisable : on la traite comme une image absente.
	}
	$new_status = ( $needs_img && ! $image_id ) ? 'draft' : 'publish';

	$args = array(
		'post_type'     => ON_VIGNETTES_CPT,
		'post_title'    => wp_strip_all_tags( get_the_title( $post ) ),
		'post_excerpt'  => on_vignettes_excerpt_for( $post ),
		'post_date'     => $post->post_date,
		'post_date_gmt' => $post->post_date_gmt,
	);

	on_vignettes_busy( true );
	if ( $existing ) {
		$status = get_post_status( $existing );
		if ( 'trash' === $status || get_post_meta( $existing, '_onv_locked', true ) ) {
			on_vignettes_busy( false );
			return; // supprimée ou modifiée à la main : on n'y touche pas.
		}
		$args['ID']          = $existing;
		$args['post_status'] = $new_status;
		wp_update_post( $args );
		$vid = $existing;
	} else {
		$args['post_status'] = $new_status;
		$vid                 = wp_insert_post( $args );
		if ( ! $vid || is_wp_error( $vid ) ) {
			on_vignettes_busy( false );
			return;
		}
		// Valeurs de départ des champs Pods (modifiables ensuite à la main).
		update_post_meta( $vid, 'format', 'standard' );
		update_post_meta( $vid, 'image_cote', 'gauche' );
		update_post_meta( $vid, 'cadrage', 'original' );
		if ( function_exists( 'pll_get_post_language' ) && function_exists( 'pll_set_post_language' ) ) {
			$lang = pll_get_post_language( $post->ID );
			if ( $lang ) {
				pll_set_post_language( $vid, $lang );
			}
		}
	}
	on_vignettes_busy( false );

	// Brouillon faute d'image : drapeau pour la pastille rouge ; il disparaît dès que l'image est là.
	if ( $problem ) {
		update_post_meta( $vid, '_onv_image_probleme', $problem );
	} else {
		delete_post_meta( $vid, '_onv_image_probleme' );
	}
	if ( 'draft' === $new_status ) {
		update_post_meta( $vid, '_onv_sans_image', 1 );
	} else {
		delete_post_meta( $vid, '_onv_sans_image' );
		delete_post_meta( $vid, '_onv_autodraft' );
	}

	update_post_meta( $vid, '_onv_source_id', (int) $post->ID );
	update_post_meta( $vid, '_onv_source_type', $post->post_type );
	update_post_meta( $vid, 'sous_titre', on_vignettes_soustitre_for( $post ) );
	update_post_meta( $vid, 'lien', esc_url_raw( get_permalink( $post ) ) );
	if ( 'ressource' === $post->post_type ) {
		$numero = get_post_meta( $post->ID, 'numero', true );
		if ( '' !== $numero ) {
			update_post_meta( $vid, 'numero', (int) $numero );
		} else {
			delete_post_meta( $vid, 'numero' ); // numéro effacé dans la ressource : la vignette n'est plus classée dans un numéro.
		}
	}

	if ( $image_id ) {
		set_post_thumbnail( $vid, $image_id );
	} else {
		delete_post_thumbnail( $vid );
	}

	// Zone et étiquette (termes de la langue du contenu pour l'étiquette).
	$lang = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post->ID ) : '';
	$zone = get_term_by( 'slug', on_vignettes_zone_for( $post ), 'zone_vignette' );
	if ( $zone ) {
		wp_set_object_terms( $vid, array( (int) $zone->term_id ), 'zone_vignette' );
	}
	$etiq = get_term_by( 'slug', on_vignettes_etiquette_for( $post ), 'etiquette_vignette' );
	if ( $etiq ) {
		wp_set_object_terms( $vid, array( on_vignettes_term_in_lang( $etiq->term_id, $lang ) ), 'etiquette_vignette' );
	}
	if ( 'ressource' === $post->post_type ) {
		$types = get_the_terms( $post->ID, 'type_de_ressource' );
		if ( $types && ! is_wp_error( $types ) ) {
			wp_set_object_terms( $vid, wp_list_pluck( $types, 'term_id' ), 'type_de_ressource' );
		} elseif ( false === $types ) {
			wp_set_object_terms( $vid, array(), 'type_de_ressource' ); // tous les types retirés : la vignette n'en garde aucun (une erreur de lecture, elle, ne change rien).
		}
	}

	on_vignettes_sync_twins( $post, $vid );

	do_action( 'on_vignettes_changed', $vid );
}

/* ----------------------------------------------------------------------
 * Pastille rouge : vignettes en attente d'une image
 * -------------------------------------------------------------------- */

/** Nombre de vignettes en brouillon faute d'image. */
function on_vignettes_missing_image_count() {
	$ids = get_posts(
		array(
			'post_type'        => ON_VIGNETTES_CPT,
			'post_status'      => 'draft',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'meta_key'         => '_onv_sans_image', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			'lang'             => '',
			'suppress_filters' => false,
		)
	);
	return count( $ids );
}

function on_vignettes_menu_badge() {
	global $menu;
	if ( ! is_array( $menu ) || ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$count = on_vignettes_missing_image_count();
	if ( $count < 1 ) {
		return;
	}
	$slug = 'edit.php?post_type=' . ON_VIGNETTES_CPT;
	foreach ( $menu as $i => $item ) {
		if ( isset( $item[2] ) && $slug === $item[2] ) {
			$menu[ $i ][0] .= ' <span class="awaiting-mod count-' . (int) $count . '"><span class="pending-count">' . (int) $count . '</span></span>'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			return;
		}
	}
}


/**
 * Une seule fois : les vignettes sans image (hors colonne ressources) prennent la première
 * image du contenu d'origine (ex. galerie d'une ressource photo) ; s'il n'y en a pas,
 * elles passent en brouillon avec la pastille rouge.
 */
add_action(
	'init',
	function () {
		if ( '2' === get_option( 'on_vignettes_images_done' ) || ! post_type_exists( ON_VIGNETTES_CPT ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'        => ON_VIGNETTES_CPT,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $id ) {
			if ( has_post_thumbnail( $id ) || get_post_meta( $id, '_onv_locked', true ) ) {
				continue; // déjà illustrée, ou modifiée à la main.
			}
			if ( has_term( array( 'gauche-ressources', 'gauche-fixe', 'droite-fixe' ), 'zone_vignette', $id ) ) {
				continue;
			}
			$source = (int) get_post_meta( $id, '_onv_source_id', true );
			$image  = $source && get_post( $source ) ? on_vignettes_image_id( get_post( $source ) ) : 0;
			if ( $image ) {
				set_post_thumbnail( $id, $image );
			} else {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'draft',
					)
				);
				update_post_meta( $id, '_onv_sans_image', 1 );
			}
		}
		update_option( 'on_vignettes_images_done', '2' );
	},
	120
);

/**
 * Une seule fois : les vignettes de ressources vidéo non modifiées à la main prennent la miniature Vimeo
 * (quelques-unes à chaque affichage, pour ne pas ralentir le site). Une vignette dont on change l'image
 * à la main est verrouillée et n'est plus touchée.
 */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'on_vignettes_vimeo_done' ) || ! post_type_exists( ON_VIGNETTES_CPT ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'        => ON_VIGNETTES_CPT,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => '_onv_source_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => 'ressource', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$budget = 4;
		$left   = false;
		foreach ( $ids as $id ) {
			if ( get_post_meta( $id, '_onv_vimeo_checked', true ) || get_post_meta( $id, '_onv_locked', true ) ) {
				continue;
			}
			$source = get_post( (int) get_post_meta( $id, '_onv_source_id', true ) );
			if ( $source ) {
				if ( on_vignettes_vimeo_will_fetch( $source ) ) {
					if ( $budget <= 0 ) {
						$left = true;
						break;
					}
					--$budget; // chaque tentative compte, réussie ou non (Vimeo qui ne répond pas ne doit pas bloquer la page).
				}
				$image = on_vignettes_vimeo_image_id( $source );
				if ( $image ) {
					set_post_thumbnail( $id, $image );
				}
			}
			update_post_meta( $id, '_onv_vimeo_checked', 1 );
		}
		if ( ! $left ) {
			update_option( 'on_vignettes_vimeo_done', '1' );
		}
	},
	130
);

/**
 * Une seule fois : contrôle qualité des images des vignettes déjà en ligne (images noires ou blanches
 * → brouillon avec la pastille rouge). Les vignettes modifiées à la main ne sont pas touchées.
 */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'on_vignettes_quality_done' ) || ! post_type_exists( ON_VIGNETTES_CPT ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'        => ON_VIGNETTES_CPT,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $id ) {
			if ( get_post_meta( $id, '_onv_locked', true ) ) {
				continue;
			}
			$source = get_post( (int) get_post_meta( $id, '_onv_source_id', true ) );
			$thumb  = get_post_thumbnail_id( $id );
			if ( ! $source || ! $thumb || ! on_vignettes_needs_image( $source ) ) {
				continue;
			}
			$problem = on_vignettes_image_problem( $thumb );
			if ( $problem ) {
				on_vignettes_busy( true );
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'draft',
					)
				);
				on_vignettes_busy( false );
				update_post_meta( $id, '_onv_sans_image', 1 );
				update_post_meta( $id, '_onv_image_probleme', $problem );
			}
		}
		update_option( 'on_vignettes_quality_done', '1' );
	},
	140
);

/** Une seule fois : les vignettes de ressources vidéo existantes passent dans la zone « Centre – Vidéos ». */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'on_vignettes_videozone_done' ) || ! post_type_exists( ON_VIGNETTES_CPT ) || ! taxonomy_exists( 'zone_vignette' ) ) {
			return;
		}
		$zone = get_term_by( 'slug', 'centre-videos', 'zone_vignette' );
		if ( ! $zone ) {
			return; // le terme est créé au chargement suivant.
		}
		$ids = get_posts(
			array(
				'post_type'        => ON_VIGNETTES_CPT,
				'post_status'      => array( 'publish', 'draft' ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => '_onv_source_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => 'ressource', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			$source = get_post( (int) get_post_meta( $id, '_onv_source_id', true ) );
			if ( $source && on_vignettes_is_video( $source ) && ! get_post_meta( $id, '_onv_locked', true ) ) {
				wp_set_object_terms( $id, array( (int) $zone->term_id ), 'zone_vignette' );
			}
		}
		update_option( 'on_vignettes_videozone_done', '1' );
	},
	150
);


/**
 * Une seule fois : les vignettes françaises existantes des ressources audio, partitions et vidéos reçoivent leur
 * vignette jumelle dans les autres langues (voir on_vignettes_sync_twins). Chaque jumelle se retire ensuite
 * séparément (brouillon ou corbeille).
 */
add_action(
	'init',
	function () {
		if ( ON_VIGNETTES_TERMS_VERSION === get_option( 'on_vignettes_twins_done' ) || ! post_type_exists( ON_VIGNETTES_CPT ) || ! function_exists( 'pll_languages_list' ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'        => ON_VIGNETTES_CPT,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'lang'             => pll_default_language(),
				'suppress_filters' => false,
				'meta_key'         => '_onv_source_type', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => 'ressource', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			$source = get_post( (int) get_post_meta( $id, '_onv_source_id', true ) );
			if ( $source && on_vignettes_is_shareable( $source ) && on_vignettes_source_is_public( $source ) ) {
				on_vignettes_sync_twins( $source, $id );
			}
		}
		update_option( 'on_vignettes_twins_done', ON_VIGNETTES_TERMS_VERSION );
	},
	160
);
