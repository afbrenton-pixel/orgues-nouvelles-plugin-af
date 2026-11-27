<?php
/**
 * Vignettes d'accueil : bouton « Dupliquer » dans la liste des vignettes.
 *
 * Crée une copie en brouillon avec tout ce que l'équipe a eu du mal à saisir : titre, texte, image à la une,
 * étiquettes, zone, et tous les champs Pods (dont les pistes d'une playlist, dans le même ordre).
 *
 * Ce qui ne se copie pas :
 * - le lien avec le contenu d'origine (`_on_source_id`) et les valeurs internes de l'import automatique : la copie
 *   est une vignette « faite à la main », qu'un réimport ne remplace pas ;
 * - la traduction : la copie n'a pas de jumelle (on la crée avec le « + » de Polylang). Elle garde la langue de l'original.
 *
 * @package Orgues-Nouvelles Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Valeurs internes (WordPress, import automatique) qui ne se copient pas d'une vignette à sa copie. */
function on_vignettes_duplicate_skips_meta( $key ) {
	$skip = array(
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_trash_meta_status',
		'_wp_trash_meta_time',
		'_wp_desired_post_slug',
		'_on_source_id',
		'_on_source_type',
		'_on_locked',
		'_on_autodraft',
		'_on_sans_image',
	);
	return in_array( (string) $key, $skip, true );
}

/** Taxonomies qui ne se copient pas : celles de Polylang (langue et lien avec les traductions). */
function on_vignettes_duplicate_skips_taxonomy( $taxonomy ) {
	return in_array( (string) $taxonomy, array( 'language', 'post_translations' ), true );
}

/** Titre de la copie : « Titre (copie) ». */
function on_vignettes_duplicate_title( $title ) {
	/* translators: %s: titre de la vignette d'origine. */
	return sprintf( __( '%s (copie)', 'orgues-nouvelles' ), (string) $title );
}

/**
 * Copie les champs d'une vignette vers une autre, un par un et dans l'ordre : un champ à plusieurs valeurs
 * (les pistes d'une playlist) garde l'ordre de ses valeurs.
 */
function on_vignettes_duplicate_meta( $from_id, $to_id ) {
	$all = get_post_meta( $from_id );
	if ( ! is_array( $all ) ) {
		return;
	}
	foreach ( $all as $key => $values ) {
		if ( on_vignettes_duplicate_skips_meta( $key ) ) {
			continue;
		}
		foreach ( (array) $values as $value ) {
			add_post_meta( $to_id, $key, wp_slash( maybe_unserialize( $value ) ) );
		}
	}
}

/** Liste d'identifiants (entiers > 0, sans doublon, dans l'ordre) à partir de ce que renvoie Pods pour un champ fichier. */
function on_vignettes_duplicate_ids( $value ) {
	$ids = array();
	foreach ( (array) $value as $item ) {
		$id = is_array( $item ) ? ( $item['ID'] ?? 0 ) : $item;
		$id = (int) $id;
		if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
			$ids[] = $id;
		}
	}
	return $ids;
}

/**
 * Copie les champs fichier de Pods (les pistes d'une playlist) par l'API de Pods : ils sont aussi enregistrés
 * dans la table de relations de Pods, que la simple copie des champs ne remplit pas.
 */
function on_vignettes_duplicate_files( $from_id, $to_id ) {
	if ( ! function_exists( 'pods' ) ) {
		return;
	}
	$source = pods( ON_VIGNETTES_CPT, $from_id );
	$copy   = pods( ON_VIGNETTES_CPT, $to_id );
	if ( ! $source || ! $copy || empty( $source->fields ) ) {
		return;
	}
	foreach ( $source->fields as $name => $field ) {
		if ( 'file' !== ( $field['type'] ?? '' ) ) {
			continue;
		}
		$ids = on_vignettes_duplicate_ids( $source->field( $name . '.ID', false ) );
		if ( $ids ) {
			$copy->save( $name, $ids );
		}
	}
}

/** Copie les étiquettes, la zone et autres taxonomies (sauf celles de Polylang). */
function on_vignettes_duplicate_terms( $from_id, $to_id ) {
	foreach ( get_object_taxonomies( ON_VIGNETTES_CPT ) as $taxonomy ) {
		if ( on_vignettes_duplicate_skips_taxonomy( $taxonomy ) ) {
			continue;
		}
		$ids = wp_get_object_terms( $from_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( is_wp_error( $ids ) || ! $ids ) {
			continue;
		}
		wp_set_object_terms( $to_id, array_map( 'intval', $ids ), $taxonomy );
	}
}

/**
 * Crée la copie d'une vignette. Retourne l'identifiant de la copie (brouillon), ou 0 si la vignette n'existe pas.
 */
function on_vignettes_duplicate( $id ) {
	$post = $id ? get_post( (int) $id ) : null;
	if ( ! $post || ON_VIGNETTES_CPT !== $post->post_type || 'trash' === $post->post_status ) {
		return 0;
	}
	on_vignettes_busy( true );
	$new = wp_insert_post(
		array(
			'post_type'    => ON_VIGNETTES_CPT,
			'post_status'  => 'draft',
			'post_title'   => on_vignettes_duplicate_title( $post->post_title ),
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'menu_order'   => (int) $post->menu_order,
			'post_author'  => get_current_user_id(),
		),
		true
	);
	if ( is_wp_error( $new ) || ! $new ) {
		on_vignettes_busy( false );
		return 0;
	}
	if ( function_exists( 'pll_get_post_language' ) && function_exists( 'pll_set_post_language' ) ) {
		$lang = pll_get_post_language( $post->ID );
		if ( $lang ) {
			pll_set_post_language( $new, $lang ); // avant les étiquettes : elles sont propres à chaque langue.
		}
	}
	on_vignettes_duplicate_meta( $post->ID, $new );
	on_vignettes_duplicate_files( $post->ID, $new );
	on_vignettes_duplicate_terms( $post->ID, $new );
	on_vignettes_busy( false );
	return (int) $new;
}

/** Lien « Dupliquer » sous le titre d'une vignette dans la liste. */
function on_vignettes_duplicate_row_actions( $actions, $post ) {
	if ( ON_VIGNETTES_CPT !== $post->post_type || 'trash' === $post->post_status || ! on_vignettes_duplicate_can( $post->ID ) ) {
		return $actions;
	}
	$url                      = wp_nonce_url( admin_url( 'admin-post.php?action=on_vignettes_dupliquer&post_id=' . (int) $post->ID ), 'on_vignettes_dupliquer_' . (int) $post->ID );
	$actions['on_dupliquer'] = sprintf(
		'<a href="%s" title="%s">%s</a>',
		esc_url( $url ),
		esc_attr__( 'Créer une copie de cette vignette (en brouillon), avec ses pistes, son image et ses étiquettes.', 'orgues-nouvelles' ),
		esc_html__( 'Dupliquer', 'orgues-nouvelles' )
	);
	return $actions;
}

/** Droit de dupliquer : créer des vignettes et modifier celle-ci. */
function on_vignettes_duplicate_can( $id ) {
	$type = get_post_type_object( ON_VIGNETTES_CPT );
	return $type && current_user_can( $type->cap->create_posts ) && current_user_can( 'edit_post', (int) $id );
}

/** Clic sur « Dupliquer » : crée la copie et ouvre son écran de modification. */
function on_vignettes_duplicate_handle() {
	$id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- contrôlé juste après.
	check_admin_referer( 'on_vignettes_dupliquer_' . $id );
	if ( ! $id || ! on_vignettes_duplicate_can( $id ) ) {
		wp_die( esc_html__( 'Accès refusé.', 'orgues-nouvelles' ), '', array( 'response' => 403 ) );
	}
	$new = on_vignettes_duplicate( $id );
	if ( ! $new ) {
		wp_die( esc_html__( 'Vignette introuvable.', 'orgues-nouvelles' ), '', array( 'response' => 404 ) );
	}
	wp_safe_redirect( get_edit_post_link( $new, 'url' ) );
	exit;
}

add_filter( 'post_row_actions', 'on_vignettes_duplicate_row_actions', 10, 2 );
add_action( 'admin_post_on_vignettes_dupliquer', 'on_vignettes_duplicate_handle' );
