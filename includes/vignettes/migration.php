<?php
/**
 * Vignettes d'accueil : migration des vignettes de l'ancien plugin de test (`on_vignette`, champs `_onv_*`)
 * vers le type de contenu Pods `vignette_accueil`.
 *
 * Outil dans « Vignettes accueil > Migration » : une simulation (rien n'est écrit) puis la migration.
 * Les anciennes vignettes ne sont jamais modifiées ni supprimées : la page actuelle reste intacte.
 * Relancer l'outil est sans danger : une vignette déjà migrée est ignorée. Les brouillons ne sont pas migrés.
 *
 * @package Orgues-Nouvelles Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const ON_VIGNETTES_OLD_CPT = 'on_vignette';

add_action( 'admin_menu', 'on_vignettes_migration_menu' );

function on_vignettes_migration_menu() {
	add_submenu_page(
		'edit.php?post_type=' . ON_VIGNETTES_CPT,
		'Migration des vignettes',
		'Migration',
		'manage_options',
		'on-vignettes-migration',
		'on_vignettes_migration_page'
	);
}

/* ----------------------------------------------------------------------
 * Règles de correspondance
 * -------------------------------------------------------------------- */

/** Zone (terme de `zone_vignette`) correspondant à une ancienne vignette. */
function on_vignettes_migration_zone( $old_id, $label_lc, $source_id, $source_type ) {
	$zone  = (string) get_post_meta( $old_id, '_onv_zone', true );
	$zone  = $zone ? $zone : 'centre';
	$fixe  = (bool) get_post_meta( $old_id, '_onv_fixe', true );
	$notes = array();

	if ( 'centre' === $zone && $fixe ) {
		return array( 'gauche-fixe', $notes );
	}
	if ( 'droite' === $zone && $fixe ) {
		return array( 'droite-fixe', $notes );
	}
	if ( 'centre' === $zone ) {
		$is_resource = false;
		if ( 'ressource' === $source_type && $source_id ) {
			$types = get_the_terms( $source_id, 'type_de_ressource' );
			if ( $types && ! is_wp_error( $types ) ) {
				foreach ( $types as $t ) {
					if ( in_array( sanitize_title( $t->name ), array( 'audio', 'textes', 'partitions' ), true ) ) {
						$is_resource = true;
					}
				}
			}
		} elseif ( in_array( $label_lc, array( 'audio', 'textes', 'partitions', 'ressources' ), true ) ) {
			$is_resource = true;
		}
		return array( $is_resource ? 'gauche-ressources' : 'centre-actus', $notes );
	}
	if ( 'droite' === $zone ) {
		return array( 'centre-publications', $notes );
	}
	if ( 'gauche' === $zone ) {
		if ( $fixe ) {
			$notes[] = 'zone gauche « fixe » : rangée avec les pubs (à vérifier)';
		}
		return array( 'droite-pubs', $notes );
	}
	$notes[] = 'zone inconnue « ' . $zone . ' » : rangée au centre';
	return array( 'centre-actus', $notes );
}

/** Table nom d'étiquette (minuscules) => identifiant de terme, pour les deux langues. */
function on_vignettes_migration_etiquette_map() {
	$map   = array();
	$terms = get_terms(
		array(
			'taxonomy'   => 'etiquette_vignette',
			'hide_empty' => false,
			'lang'       => '',
		)
	);
	if ( is_wp_error( $terms ) ) {
		return $map;
	}
	foreach ( $terms as $t ) {
		$map[ mb_strtolower( $t->name ) ] = (int) $t->term_id;
	}
	return $map;
}

/**
 * Étiquette d'une ancienne vignette.
 *
 * @return array [ term_id (0 si aucun), note ]
 */
function on_vignettes_migration_etiquette( $label, $lang, $map ) {
	$lc = mb_strtolower( trim( (string) $label ) );
	if ( '' === $lc ) {
		return array( 0, 'étiquette vide' );
	}
	$aliases = array(
		'uncategorized' => 'actus',
		'audio'         => 'ressources',
		'textes'        => 'ressources',
		'partitions'    => 'ressources',
		'echoes'        => 'echoes',
	);
	if ( isset( $aliases[ $lc ] ) ) {
		$lc = $aliases[ $lc ];
	}
	if ( ! isset( $map[ $lc ] ) ) {
		return array( 0, 'étiquette inconnue « ' . $label . ' »' );
	}
	$term_id = $map[ $lc ];
	if ( $lang && function_exists( 'pll_get_term' ) ) {
		$tr = pll_get_term( $term_id, $lang );
		if ( $tr ) {
			$term_id = (int) $tr;
		}
	}
	return array( $term_id, '' );
}

/* ----------------------------------------------------------------------
 * Migration
 * -------------------------------------------------------------------- */

/** Identifiant de la vignette migrée à partir d'une ancienne, 0 si aucune. */
function on_vignettes_migration_find_new( $old_id ) {
	$ids = get_posts(
		array(
			'post_type'        => ON_VIGNETTES_CPT,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'numberposts'      => 1,
			'fields'           => 'ids',
			'meta_key'         => '_onv_old_id', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => (int) $old_id, // phpcs:ignore WordPress.DB.SlowDBQuery
			'lang'             => '',
			'suppress_filters' => false,
		)
	);
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Migre (ou simule la migration de) toutes les anciennes vignettes.
 *
 * @param bool $dry Vrai : ne rien écrire.
 * @return array [ 'rows' => lignes du rapport, 'created' => nombre, 'map' => ancien => nouveau ]
 */
function on_vignettes_migrate( $dry ) {
	$old = get_posts(
		array(
			'post_type'        => ON_VIGNETTES_OLD_CPT,
			'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'numberposts'      => -1,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'lang'             => '',
			'suppress_filters' => false,
		)
	);

	$etiq_map = on_vignettes_migration_etiquette_map();
	$rows     = array();
	$created  = 0;
	$id_map   = array();
	$has_pll  = function_exists( 'pll_get_post_language' ) && function_exists( 'pll_set_post_language' );

	foreach ( $old as $o ) {
		$oid         = (int) $o->ID;
		$source_id   = (int) get_post_meta( $oid, '_onv_source_id', true );
		$source_type = (string) get_post_meta( $oid, '_onv_source_type', true );
		$label       = (string) get_post_meta( $oid, '_onv_etiquette', true );
		$lang        = $has_pll ? (string) pll_get_post_language( $oid, 'slug' ) : '';

		$row = array(
			'old_id' => $oid,
			'title'  => $o->post_title,
			'lang'   => $lang,
			'status' => $o->post_status,
			'zone'   => '',
			'etiq'   => '',
			'action' => '',
			'notes'  => array(),
		);

		// Les brouillons ne sont pas migrés (vignettes de test ou de contenus dépubliés) : ils seront recréés
		// automatiquement si le contenu d'origine est publié.
		if ( 'draft' === $o->post_status ) {
			$row['action'] = 'ignorée : brouillon';
			$rows[]        = $row;
			continue;
		}

		$already = on_vignettes_migration_find_new( $oid );
		if ( $already ) {
			$row['action']   = 'déjà migrée';
			$id_map[ $oid ] = $already;
			$rows[]          = $row;
			continue;
		}
		if ( $source_id ) {
			$auto = on_vignettes_find( $source_id );
			if ( $auto ) {
				$row['action']   = 'ignorée : déjà créée automatiquement';
				$id_map[ $oid ] = $auto;
				$rows[]          = $row;
				continue;
			}
		}

		list( $zone_slug, $zone_notes ) = on_vignettes_migration_zone( $oid, mb_strtolower( trim( $label ) ), $source_id, $source_type );
		list( $etiq_id, $etiq_note )    = on_vignettes_migration_etiquette( $label, $lang, $etiq_map );
		$row['zone']                    = $zone_slug;
		$row['etiq']                    = $label;
		$row['notes']                   = $zone_notes;
		if ( $etiq_note ) {
			$row['notes'][] = $etiq_note;
		}
		if ( ! get_post_thumbnail_id( $oid ) && ! in_array( $zone_slug, array( 'gauche-ressources', 'gauche-fixe', 'droite-fixe' ), true ) && 'publish' === $o->post_status ) {
			$row['notes'][] = 'publiée sans image';
		}

		if ( $dry ) {
			$row['action'] = 'à créer';
			$rows[]        = $row;
			continue;
		}

		on_vignettes_busy( true );
		$new_id = wp_insert_post(
			array(
				'post_type'     => ON_VIGNETTES_CPT,
				'post_title'    => $o->post_title,
				'post_excerpt'  => $o->post_excerpt,
				'post_status'   => $o->post_status,
				'post_date'     => $o->post_date,
				'post_date_gmt' => $o->post_date_gmt,
			),
			true
		);
		on_vignettes_busy( false );
		if ( is_wp_error( $new_id ) || ! $new_id ) {
			$row['action']  = 'ERREUR à la création';
			$row['notes'][] = is_wp_error( $new_id ) ? $new_id->get_error_message() : '';
			$rows[]         = $row;
			continue;
		}

		if ( $has_pll && $lang ) {
			pll_set_post_language( $new_id, $lang );
		}
		$thumb = get_post_thumbnail_id( $oid );
		if ( $thumb ) {
			set_post_thumbnail( $new_id, $thumb );
		}

		$layout = (string) get_post_meta( $oid, '_onv_layout', true );
		update_post_meta( $new_id, 'format', 'large' === $layout ? 'large' : 'standard' );
		$cote = (string) get_post_meta( $oid, '_onv_image_cote', true );
		update_post_meta( $new_id, 'image_cote', 'droite' === $cote ? 'droite' : 'gauche' );
		update_post_meta( $new_id, 'cadrage', 'original' );
		update_post_meta( $new_id, 'sous_titre', (string) get_post_meta( $oid, '_onv_soustitre', true ) );
		update_post_meta( $new_id, 'lien', (string) get_post_meta( $oid, '_onv_url', true ) );
		$rang = (int) get_post_meta( $oid, '_onv_rang', true );
		if ( $rang > 0 ) {
			update_post_meta( $new_id, 'rang', $rang );
		}
		update_post_meta( $new_id, 'masquer_titre', get_post_meta( $oid, '_onv_masquer', true ) ? '1' : '0' );
		if ( 'ressource' === $source_type && $source_id ) {
			$numero = get_post_meta( $source_id, 'numero', true );
			if ( '' !== $numero ) {
				update_post_meta( $new_id, 'numero', (int) $numero );
			}
			$types = get_the_terms( $source_id, 'type_de_ressource' );
			if ( $types && ! is_wp_error( $types ) ) {
				wp_set_object_terms( $new_id, wp_list_pluck( $types, 'term_id' ), 'type_de_ressource' );
			}
		}

		// Valeurs internes : le lien avec le contenu d'origine et l'ancienne vignette.
		if ( $source_id ) {
			update_post_meta( $new_id, '_onv_source_id', $source_id );
			update_post_meta( $new_id, '_onv_source_type', $source_type );
		}
		if ( get_post_meta( $oid, '_onv_locked', true ) ) {
			update_post_meta( $new_id, '_onv_locked', 1 );
		}
		update_post_meta( $new_id, '_onv_old_id', $oid );
		$old_cadre = (string) get_post_meta( $oid, '_onv_cadre', true );
		if ( '' !== $old_cadre ) {
			update_post_meta( $new_id, '_onv_old_cadre', $old_cadre ); // ancien recadrage (centre, haut, bas) : conservé pour mémoire.
		}

		$zone = get_term_by( 'slug', $zone_slug, 'zone_vignette' );
		if ( $zone ) {
			wp_set_object_terms( $new_id, array( (int) $zone->term_id ), 'zone_vignette' );
		}
		if ( $etiq_id ) {
			wp_set_object_terms( $new_id, array( $etiq_id ), 'etiquette_vignette' );
		}

		$row['action']  = 'créée (n° ' . $new_id . ')';
		$id_map[ $oid ] = $new_id;
		++$created;
		$rows[] = $row;
	}

	// Traductions : on relie les nouvelles vignettes comme l'étaient les anciennes.
	if ( ! $dry && function_exists( 'pll_get_post_translations' ) && function_exists( 'pll_save_post_translations' ) ) {
		foreach ( $id_map as $oid => $new_id ) {
			$group = pll_get_post_translations( $oid );
			if ( count( $group ) < 2 ) {
				continue;
			}
			$new_group = array();
			foreach ( $group as $l => $translated_old ) {
				if ( isset( $id_map[ $translated_old ] ) ) {
					$new_group[ $l ] = $id_map[ $translated_old ];
				}
			}
			if ( count( $new_group ) > 1 ) {
				pll_save_post_translations( $new_group );
			}
		}
	}

	return array(
		'rows'    => $rows,
		'created' => $created,
		'map'     => $id_map,
	);
}

/* ----------------------------------------------------------------------
 * Page d'administration
 * -------------------------------------------------------------------- */

function on_vignettes_migration_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$result = null;
	$mode   = '';
	if ( isset( $_POST['on_vignettes_migration'] ) ) {
		check_admin_referer( 'on_vignettes_migration' );
		$mode = 'run' === sanitize_key( wp_unslash( $_POST['on_vignettes_migration'] ) ) ? 'run' : 'simulate';
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@set_time_limit( 300 );
		}
		$result = on_vignettes_migrate( 'run' !== $mode );
	}
	?>
	<div class="wrap">
		<h1>Migration des vignettes</h1>
		<p>Cet outil copie les vignettes de l'ancien plugin (« Vignettes ») vers le nouveau type (« Vignettes accueil »). Les anciennes vignettes ne sont ni modifiées ni supprimées : la page d'accueil actuelle reste intacte.</p>
		<p>La <strong>simulation</strong> n'écrit rien : elle affiche seulement ce qui serait fait. On peut relancer l'outil sans danger : une vignette déjà migrée est ignorée.</p>
		<form method="post">
			<?php wp_nonce_field( 'on_vignettes_migration' ); ?>
			<button type="submit" name="on_vignettes_migration" value="simulate" class="button button-secondary">Simulation (rien n'est écrit)</button>
			<button type="submit" name="on_vignettes_migration" value="run" class="button button-primary" onclick="return confirm('Créer les nouvelles vignettes maintenant ?');">Migrer les vignettes</button>
		</form>
		<?php if ( $result ) : ?>
			<?php
			$rows   = $result['rows'];
			$counts = array();
			foreach ( $rows as $r ) {
				$k            = preg_replace( '/ \(n° \d+\)/', '', $r['action'] );
				$counts[ $k ] = isset( $counts[ $k ] ) ? $counts[ $k ] + 1 : 1;
			}
			$zones = array();
			foreach ( $rows as $r ) {
				if ( $r['zone'] ) {
					$zones[ $r['zone'] ] = isset( $zones[ $r['zone'] ] ) ? $zones[ $r['zone'] ] + 1 : 1;
				}
			}
			?>
			<h2><?php echo 'run' === $mode ? 'Résultat de la migration' : 'Résultat de la simulation (rien n\'a été écrit)'; ?></h2>
			<p>
				<?php echo esc_html( count( $rows ) ); ?> anciennes vignettes examinées.
				<?php foreach ( $counts as $k => $n ) : ?>
					<br><strong><?php echo esc_html( $k ); ?></strong> : <?php echo esc_html( $n ); ?>
				<?php endforeach; ?>
			</p>
			<p>
				<strong>Par zone :</strong>
				<?php foreach ( $zones as $z => $n ) : ?>
					<?php echo esc_html( $z . ' (' . $n . ')' ); ?> &nbsp;
				<?php endforeach; ?>
			</p>
			<table class="widefat striped">
				<thead><tr><th>Ancienne</th><th>Titre</th><th>Langue</th><th>Statut</th><th>Étiquette</th><th>Zone</th><th>Action</th><th>Remarques</th></tr></thead>
				<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr>
						<td><?php echo esc_html( $r['old_id'] ); ?></td>
						<td><?php echo esc_html( $r['title'] ); ?></td>
						<td><?php echo esc_html( $r['lang'] ); ?></td>
						<td><?php echo esc_html( $r['status'] ); ?></td>
						<td><?php echo esc_html( $r['etiq'] ); ?></td>
						<td><?php echo esc_html( $r['zone'] ); ?></td>
						<td><?php echo esc_html( $r['action'] ); ?></td>
						<td><?php echo esc_html( implode( ' ; ', $r['notes'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}
