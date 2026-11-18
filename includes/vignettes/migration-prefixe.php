<?php
/**
 * Vignettes d'accueil : passage du préfixe « onv » (reliquat du premier plugin « Orgues Nouvelles Vignettes »)
 * au préfixe « on » du reste du plugin.
 *
 * Une seule fois par site (un réglage garde le numéro de version de la migration), elle :
 * 1. renomme les champs `_onv_…` en `_on_…` (liens vignette ↔ source, verrouillage, brouillons automatiques…) ;
 * 2. renomme les classes CSS `onv-…` dans les données Elementor (modèles de boucle, pages, CSS personnalisé) ;
 * 3. vide le cache Elementor pour que les pages reprennent les nouvelles classes.
 *
 * Ce fichier peut être supprimé quand tous les sites (test, production) ont été migrés.
 *
 * @package Orgues-Nouvelles Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Numéro de version de la migration : le changer relance la migration (elle est sans danger si elle tourne deux fois). */
const ON_VIGNETTES_PREFIX_MIGRATION = '1';

/**
 * Remplace les classes CSS « onv-… » par « on-… » dans un texte ou, récursivement, dans un tableau (clés et valeurs).
 * Seuls les noms qui commencent par « onv- » sont touchés (un mot comme « conv-x » reste identique).
 * « onv-on » (élément en cours de lecture) devient « on-current » : « on-on » serait illisible.
 *
 * @param string|array $value Texte ou tableau.
 * @return string|array Même type que l'entrée.
 */
function on_vignettes_rename_prefix( $value ) {
	if ( is_array( $value ) ) {
		$out = array();
		foreach ( $value as $k => $v ) {
			$out[ is_string( $k ) ? on_vignettes_rename_prefix( $k ) : $k ] = on_vignettes_rename_prefix( $v );
		}
		return $out;
	}
	if ( ! is_string( $value ) ) {
		return $value;
	}
	$value = preg_replace( '/(?<![A-Za-z0-9])onv-on(?![A-Za-z0-9_-])/', 'on-current', $value );
	return preg_replace( '/(?<![A-Za-z0-9])onv-/', 'on-', $value );
}

/**
 * Lance la migration si elle n'a pas encore été faite sur ce site.
 */
function on_vignettes_migrate_prefix() {
	global $wpdb;
	$done = get_option( 'on_vignettes_prefix_migration' );
	if ( is_array( $done ) && ( $done['version'] ?? '' ) === ON_VIGNETTES_PREFIX_MIGRATION ) {
		return;
	}

	$report = array(
		'version'   => ON_VIGNETTES_PREFIX_MIGRATION,
		'date'      => gmdate( 'c' ),
		'champs'    => 0,
		'elementor' => 0,
	);

	// 1. Champs : _onv_xxx → _on_xxx (y compris les noms avec un numéro de vidéo à la fin).
	$like   = $wpdb->esc_like( '_onv_' ) . '%';
	$champs = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_key = CONCAT('_on_', SUBSTRING(meta_key, 6)) WHERE meta_key LIKE %s", $like ) );
	$report['champs'] = (int) $champs;

	// 2. Classes dans les données Elementor et dans le CSS additionnel du thème.
	$rows = $wpdb->get_results( "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('_elementor_data', '_elementor_page_settings') AND meta_value LIKE '%onv-%'" );
	foreach ( (array) $rows as $row ) {
		if ( '_elementor_page_settings' === $row->meta_key ) {
			// Donnée « sérialisée » : on la lit et on la réécrit (un simple remplacement fausserait les longueurs).
			$data = maybe_unserialize( $row->meta_value );
			$new  = is_array( $data ) ? maybe_serialize( on_vignettes_rename_prefix( $data ) ) : $row->meta_value;
		} else {
			$new = on_vignettes_rename_prefix( $row->meta_value );
		}
		if ( $new !== $row->meta_value ) {
			$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new ), array( 'meta_id' => $row->meta_id ) );
			++$report['elementor'];
		}
	}
	$css_posts = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type = 'custom_css' AND post_content LIKE '%onv-%'" );
	foreach ( (array) $css_posts as $p ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => on_vignettes_rename_prefix( $p->post_content ) ), array( 'ID' => $p->ID ) );
		++$report['elementor'];
	}

	// 3. Caches : éléments d'Elementor déjà calculés et fichiers CSS (régénérés au prochain affichage).
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_element_cache'" );
	if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	wp_cache_flush();

	update_option( 'on_vignettes_prefix_migration', $report, false );
}
add_action( 'init', 'on_vignettes_migrate_prefix', 99 );
