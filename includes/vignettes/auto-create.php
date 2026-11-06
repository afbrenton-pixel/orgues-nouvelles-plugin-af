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

function on_vignettes_on_source_deleted( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, on_vignettes_source_types(), true ) ) {
		return;
	}
	$vid = on_vignettes_find( $post_id );
	if ( $vid ) {
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

/** Identifiant de la vignette liée à un contenu (toutes langues et statuts), 0 si aucune. */
function on_vignettes_find( $source_id ) {
	$ids = get_posts(
		array(
			'post_type'        => ON_VIGNETTES_CPT,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'numberposts'      => 1,
			'fields'           => 'ids',
			'meta_key'         => '_onv_source_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => (int) $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			'lang'             => '',
			'suppress_filters' => false,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Image de la vignette : image à la une, sinon première image du texte.
 *
 * @return int Identifiant de la pièce jointe, 0 si aucune.
 */
function on_vignettes_image_id( $post ) {
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

/** Zone (terme de `zone_vignette`) d'un contenu. */
function on_vignettes_zone_for( $post ) {
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

	// Contenu dépublié : la vignette automatique passe en brouillon.
	if ( 'publish' !== $post->post_status ) {
		if ( $existing && 'publish' === get_post_status( $existing ) && ! get_post_meta( $existing, '_onv_locked', true ) ) {
			on_vignettes_busy( true );
			wp_update_post(
				array(
					'ID'          => $existing,
					'post_status' => 'draft',
				)
			);
			on_vignettes_busy( false );
			update_post_meta( $existing, '_onv_autodraft', 1 );
		}
		return;
	}

	$image_id   = on_vignettes_image_id( $post );
	if ( ! $image_id && 'ressource' === $post->post_type ) {
		// Ressource sans photo : couverture du numéro du magazine.
		$image_id = on_vignettes_cover_id( (int) get_post_meta( $post->ID, 'numero', true ), function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $post->ID ) : '' );
	}
	$needs_img  = 'ressource' !== $post->post_type;
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
		}
	}

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
 * Couverture du magazine d'un numéro (image à la une du magazine, même langue,
 * sinon version française).
 */
function on_vignettes_cover_id( $num, $lang = '' ) {
	static $covers = null;
	if ( $num < 1 ) {
		return 0;
	}
	if ( null === $covers ) {
		$covers = array();
		$mags   = get_posts(
			array(
				'post_type'        => 'magazine',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'suppress_filters' => true,
			)
		);
		foreach ( $mags as $mag ) {
			$l = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $mag->ID ) : '';
			$covers[ $l . '|' . (int) $mag->menu_order ] = (int) get_post_thumbnail_id( $mag->ID );
		}
	}
	if ( ! empty( $covers[ $lang . '|' . $num ] ) ) {
		return $covers[ $lang . '|' . $num ];
	}
	return ! empty( $covers[ 'fr|' . $num ] ) ? $covers['fr|' . $num] : 0;
}

/**
 * Une seule fois : les vignettes de ressources sans image prennent la couverture de leur numéro.
 */
add_action(
	'init',
	function () {
		if ( '1' === get_option( 'on_vignettes_covers_done' ) || ! post_type_exists( ON_VIGNETTES_CPT ) ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'        => ON_VIGNETTES_CPT,
				'post_status'      => array( 'publish', 'draft' ),
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		foreach ( $ids as $id ) {
			if ( has_post_thumbnail( $id ) ) {
				continue;
			}
			$num = (int) get_post_meta( $id, 'numero', true );
			if ( $num < 1 ) {
				continue;
			}
			$lang  = function_exists( 'pll_get_post_language' ) ? (string) pll_get_post_language( $id ) : '';
			$cover = on_vignettes_cover_id( $num, $lang );
			if ( $cover ) {
				set_post_thumbnail( $id, $cover );
			}
		}
		update_option( 'on_vignettes_covers_done', '1' );
	},
	120
);
