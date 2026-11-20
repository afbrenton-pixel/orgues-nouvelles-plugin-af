<?php
/**
 * Vignettes d'accueil : page « Importer les vignettes » (menu « Vignettes accueil »).
 *
 * Crée les vignettes des contenus déjà publiés, une seule fois en production (le plugin, lui, ne crée une
 * vignette qu'à l'enregistrement d'un contenu). On choisit les types, une date de départ facultative et les
 * produits à écarter, on lance une simulation, puis la création par petits lots avec une barre de progression.
 *
 * La création appelle directement on_vignettes_upsert() : les contenus ne sont pas réenregistrés, leur date de
 * modification ne change donc pas. On peut relancer sans doublon ; les vignettes modifiées à la main ou mises
 * à la corbeille ne sont pas touchées (règle de on_vignettes_upsert()).
 *
 * @package Orgues-Nouvelles Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Nombre de contenus traités par requête (petit : chaque création peut importer des images, créer une jumelle…). */
const ON_VIGNETTES_BULK_BATCH = 5;

/** Temps maximal (secondes) d'une requête : on s'arrête dès qu'il est dépassé, la suite part dans la requête suivante. */
const ON_VIGNETTES_BULK_TIME = 20;

/* ----------------------------------------------------------------------
 * Fonctions de choix des contenus (sans appel à WordPress sauf indication)
 * -------------------------------------------------------------------- */

/** Libellés des types de contenu qui donnent une vignette (type => libellé), dans l'ordre de la page. */
function on_vignettes_bulk_labels() {
	$all   = array(
		'post'      => __( 'Actualités', 'orgues-nouvelles' ),
		'ressource' => __( 'Ressources', 'orgues-nouvelles' ),
		'cd'        => __( 'CD', 'orgues-nouvelles' ),
		'product'   => __( 'Produits de la boutique', 'orgues-nouvelles' ),
	);
	$types = on_vignettes_source_types();
	return array_intersect_key( $all, array_flip( $types ) );
}

/** Expression régulière complète (avec délimiteurs) pour un motif saisi à la main. */
function on_vignettes_bulk_regex( $pattern ) {
	return '#' . str_replace( '#', '\#', (string) $pattern ) . '#iu';
}

/** Le motif saisi est-il une expression régulière valide ? (vide = valide : rien n'est écarté). */
function on_vignettes_bulk_valid_pattern( $pattern ) {
	if ( '' === trim( (string) $pattern ) ) {
		return true;
	}
	return false !== @preg_match( on_vignettes_bulk_regex( $pattern ), '' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

/** Le titre correspond-il au motif ? (motif vide ou invalide : non). */
function on_vignettes_bulk_title_matches( $title, $pattern ) {
	if ( '' === trim( (string) $pattern ) || ! on_vignettes_bulk_valid_pattern( $pattern ) ) {
		return false;
	}
	return 1 === preg_match( on_vignettes_bulk_regex( $pattern ), html_entity_decode( on_vignettes_bulk_plain_title( $title ), ENT_QUOTES, 'UTF-8' ) );
}

/** Retire les balises d'un titre (équivalent simple de wp_strip_all_tags, utilisable sans WordPress). */
function on_vignettes_bulk_plain_title( $text ) {
	return trim( strip_tags( (string) $text ) );
}

/**
 * Nettoie les réglages envoyés par la page.
 *
 * @param array $raw Valeurs brutes (types[], since, exclude, only_missing).
 * @param array $allowed_types Types autorisés.
 * @return array { types: string[], since: string, exclude: string, only_missing: bool, error: string }
 */
function on_vignettes_bulk_clean_params( $raw, $allowed_types ) {
	$types = array();
	foreach ( (array) ( $raw['types'] ?? array() ) as $t ) {
		$t = (string) $t;
		if ( in_array( $t, $allowed_types, true ) && ! in_array( $t, $types, true ) ) {
			$types[] = $t;
		}
	}
	$since = trim( (string) ( $raw['since'] ?? '' ) );
	$error = '';
	if ( '' !== $since ) {
		$ok = 1 === preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $since, $m ) && checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
		if ( ! $ok ) {
			$since = '';
			$error = 'date';
		}
	}
	$exclude = trim( (string) ( $raw['exclude'] ?? '' ) );
	if ( ! on_vignettes_bulk_valid_pattern( $exclude ) ) {
		$exclude = '';
		$error   = 'motif';
	}
	if ( ! $types && '' === $error ) {
		$error = 'types';
	}
	return array(
		'types'        => $types,
		'since'        => $since,
		'exclude'      => $exclude,
		'only_missing' => ! empty( $raw['only_missing'] ) && '0' !== (string) $raw['only_missing'],
		'error'        => $error,
	);
}

/**
 * Sépare la file en tête (le prochain lot) du reste.
 *
 * @param int[] $queue File d'identifiants.
 * @param int   $size  Taille du lot.
 * @return array { batch: int[], rest: int[] }
 */
function on_vignettes_bulk_take( $queue, $size ) {
	$queue = array_values( (array) $queue );
	$size  = max( 1, (int) $size );
	return array(
		'batch' => array_slice( $queue, 0, $size ),
		'rest'  => array_slice( $queue, $size ),
	);
}

/* ----------------------------------------------------------------------
 * Recherche des contenus et traitement (appellent WordPress)
 * -------------------------------------------------------------------- */

/**
 * Contenus publiés concernés (toutes langues), avec la présence d'une vignette.
 *
 * @param array $params Réglages nettoyés.
 * @return array { rows: array[], counts: array } rows : id, type, title, has ; counts : type => { total, has, todo }
 */
function on_vignettes_bulk_collect( $params ) {
	$rows   = array();
	$counts = array();
	foreach ( $params['types'] as $type ) {
		$args = array(
			'post_type'        => $type,
			'post_status'      => 'publish',
			'has_password'     => false,
			'posts_per_page'   => -1,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'lang'             => '', // Polylang : toutes les langues.
			'no_found_rows'    => true,
			'suppress_filters' => false,
		);
		if ( '' !== $params['since'] ) {
			$args['date_query'] = array(
				array(
					'after'     => $params['since'],
					'inclusive' => true,
				),
			);
		}
		$counts[ $type ] = array(
			'total' => 0,
			'has'   => 0,
			'todo'  => 0,
		);
		foreach ( get_posts( $args ) as $id ) {
			$title = get_the_title( $id );
			if ( on_vignettes_bulk_title_matches( $title, $params['exclude'] ) ) {
				continue;
			}
			$existing = on_vignettes_find( $id );
			$has      = (bool) $existing;
			++$counts[ $type ]['total'];
			if ( $has ) {
				++$counts[ $type ]['has'];
			}
			// Déjà une vignette : ignorée en mode « manquantes seulement » ; en réimport, ignorée aussi si elle est protégée.
			if ( $has && ( $params['only_missing'] || on_vignettes_bulk_is_protected( $existing ) ) ) {
				continue;
			}
			++$counts[ $type ]['todo'];
			$rows[] = array(
				'id'    => (int) $id,
				'type'  => $type,
				'title' => html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' ),
				'has'   => $has,
			);
		}
	}
	return array(
		'rows'   => $rows,
		'counts' => $counts,
	);
}

/** Vignette à la corbeille ou modifiée à la main (verrouillée) : le plugin n'y touche jamais. */
function on_vignettes_bulk_is_protected( $vignette_id ) {
	return $vignette_id && ( 'trash' === get_post_status( $vignette_id ) || get_post_meta( $vignette_id, '_on_locked', true ) );
}

/**
 * Crée ou met à jour la vignette d'un contenu.
 *
 * @return string created | updated | draft | skipped | failed
 */
function on_vignettes_bulk_process( $id ) {
	$post = get_post( (int) $id );
	if ( ! $post || ! in_array( $post->post_type, on_vignettes_source_types(), true ) || ! on_vignettes_source_is_public( $post ) ) {
		return 'skipped';
	}
	$before = on_vignettes_find( $post->ID );
	if ( on_vignettes_bulk_is_protected( $before ) ) {
		return 'skipped'; // corbeille ou modifiée à la main : on_vignettes_upsert n'y touche pas, ce n'est pas une mise à jour.
	}
	on_vignettes_upsert( $post );
	$after = on_vignettes_find( $post->ID );
	if ( ! $after ) {
		return 'failed';
	}
	if ( ! $before && 'draft' === get_post_status( $after ) ) {
		return 'draft'; // créée, mais sans image utilisable : en brouillon avec la pastille rouge.
	}
	return $before ? 'updated' : 'created';
}

/* ----------------------------------------------------------------------
 * Requêtes AJAX
 * -------------------------------------------------------------------- */

add_action( 'wp_ajax_on_vignettes_bulk', 'on_vignettes_bulk_ajax' );

function on_vignettes_bulk_ajax() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'code' => 'forbidden' ), 403 );
	}
	check_ajax_referer( 'on_vignettes_bulk', 'nonce' );
	if ( ! post_type_exists( ON_VIGNETTES_CPT ) ) {
		wp_send_json_error( array( 'code' => 'nocpt' ) );
	}

	$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	$key  = 'on_vignettes_bulk_' . get_current_user_id();

	if ( 'step' === $step ) {
		$state = get_transient( $key );
		if ( ! is_array( $state ) ) {
			wp_send_json_error( array( 'code' => 'nostate' ) );
		}
		$stats = array(
			'created' => 0,
			'updated' => 0,
			'draft'   => 0,
			'skipped' => 0,
			'failed'  => 0,
		);
		$last  = array();
		$start = microtime( true );
		$take  = on_vignettes_bulk_take( $state['queue'], ON_VIGNETTES_BULK_BATCH );
		$queue = $state['queue'];
		foreach ( $take['batch'] as $id ) {
			if ( microtime( true ) - $start > ON_VIGNETTES_BULK_TIME ) {
				break;
			}
			$result = on_vignettes_bulk_process( $id );
			++$stats[ $result ];
			$last[] = array(
				'title'  => html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' ),
				'result' => $result,
			);
			array_shift( $queue );
		}
		$state['queue'] = $queue;
		$state['done']  = (int) $state['total'] - count( $queue );
		set_transient( $key, $state, HOUR_IN_SECONDS );
		wp_send_json_success(
			array(
				'done'  => $state['done'],
				'total' => (int) $state['total'],
				'stats' => $stats,
				'last'  => $last,
			)
		);
	}

	if ( 'finish' === $step ) {
		delete_transient( $key );
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
		wp_send_json_success( array( 'cache' => true ) );
	}

	// Simulation et démarrage : même recherche.
	$params = on_vignettes_bulk_clean_params( wp_unslash( $_POST ), array_keys( on_vignettes_bulk_labels() ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $params['error'] ) {
		wp_send_json_error( array( 'code' => $params['error'] ) );
	}
	$found  = on_vignettes_bulk_collect( $params );
	$table = array();
	foreach ( $found['counts'] as $type => $c ) {
		$table[] = array(
			'type'  => $type,
			'total' => $c['total'],
			'has'   => $c['has'],
			'todo'  => $c['todo'],
		);
	}
	if ( 'start' === $step ) {
		set_transient(
			$key,
			array(
				'queue' => wp_list_pluck( $found['rows'], 'id' ),
				'total' => count( $found['rows'] ),
				'done'  => 0,
			),
			HOUR_IN_SECONDS
		);
		wp_send_json_success( array( 'total' => count( $found['rows'] ) ) );
	}
	wp_send_json_success(
		array(
			'table'  => $table,
			'todo'   => count( $found['rows'] ),
			'sample' => array_map(
				function ( $r ) {
					return array(
						'type'  => $r['type'],
						'title' => $r['title'],
					);
				},
				array_slice( $found['rows'], 0, 30 )
			),
		)
	);
}

/* ----------------------------------------------------------------------
 * Page d'administration
 * -------------------------------------------------------------------- */

add_action(
	'admin_menu',
	function () {
		if ( ! post_type_exists( ON_VIGNETTES_CPT ) ) {
			return;
		}
		$hook = add_submenu_page(
			'edit.php?post_type=' . ON_VIGNETTES_CPT,
			__( 'Importer les vignettes', 'orgues-nouvelles' ),
			__( 'Importer les vignettes', 'orgues-nouvelles' ),
			'manage_options',
			'on-vignettes-creation',
			'on_vignettes_bulk_page'
		);
		add_action(
			'admin_enqueue_scripts',
			function ( $current ) use ( $hook ) {
				if ( $current !== $hook ) {
					return;
				}
				$file = dirname( __DIR__, 2 ) . '/assets/js/vignettes-creation.js';
				wp_enqueue_script( 'on-vignettes-creation', plugins_url( 'assets/js/vignettes-creation.js', dirname( __DIR__, 2 ) . '/orgues-nouvelles-plugin.php' ), array(), (string) filemtime( $file ), true );
				$texts = array(
					'nonce'        => wp_create_nonce( 'on_vignettes_bulk' ),
					'simulating'   => __( 'Simulation en cours…', 'orgues-nouvelles' ),
					'running'      => __( 'Création en cours : ne fermez pas cette page.', 'orgues-nouvelles' ),
					'types'        => on_vignettes_bulk_labels(),
					'error'        => __( 'Erreur : %s', 'orgues-nouvelles' ),
					'errors'       => array(
						'forbidden' => __( 'Action réservée aux administrateurs.', 'orgues-nouvelles' ),
						'nocpt'     => __( 'Le type de contenu « Vignettes accueil » est absent (Pods).', 'orgues-nouvelles' ),
						'nostate'   => __( 'Aucune création en cours : relancez la simulation.', 'orgues-nouvelles' ),
						'types'     => __( 'Cochez au moins un type de contenu.', 'orgues-nouvelles' ),
						'date'      => __( 'La date de départ n\'est pas valable.', 'orgues-nouvelles' ),
						'motif'     => __( 'Le motif des titres à écarter n\'est pas une expression régulière valable.', 'orgues-nouvelles' ),
					),
					'network'      => __( 'La connexion avec le site a échoué. Relancez la simulation puis la création : ce qui est déjà créé ne sera pas refait.', 'orgues-nouvelles' ),
					/* translators: 1: nombre de contenus traités, 2: nombre total. */
					'progress'     => __( '%1$d sur %2$d', 'orgues-nouvelles' ),
					'nothing'      => __( 'Rien à importer avec ces réglages.', 'orgues-nouvelles' ),
					'colType'      => __( 'Type', 'orgues-nouvelles' ),
					'colTotal'     => __( 'Contenus publiés retenus', 'orgues-nouvelles' ),
					'colHas'       => __( 'Ont déjà une vignette', 'orgues-nouvelles' ),
					'colTodo'      => __( 'À traiter', 'orgues-nouvelles' ),
					/* translators: %d: nombre de vignettes à traiter. */
					'toDo'         => __( '%d contenu(s) à traiter. Si cela vous convient, cliquez sur « Importer les vignettes ».', 'orgues-nouvelles' ),
					'sampleTitle'  => __( 'Les premiers contenus à traiter :', 'orgues-nouvelles' ),
					'created'      => __( 'créées', 'orgues-nouvelles' ),
					'updated'      => __( 'mises à jour', 'orgues-nouvelles' ),
					'draft'        => __( 'créées en brouillon (image manquante)', 'orgues-nouvelles' ),
					'skipped'      => __( 'ignorées', 'orgues-nouvelles' ),
					'failed'       => __( 'en échec', 'orgues-nouvelles' ),
					'finished'     => __( 'Terminé. Le cache d\'Elementor a été vidé.', 'orgues-nouvelles' ),
					'draftsNotice' => __( 'Les vignettes en brouillon attendent une image : voir la pastille rouge dans le menu « Vignettes accueil ».', 'orgues-nouvelles' ),
				);
				wp_add_inline_script( 'on-vignettes-creation', 'window.ON_BULK=' . wp_json_encode( $texts ) . ';', 'before' );
			}
		);
	},
	30
);

function on_vignettes_bulk_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap" id="on-bulk">
		<h1><?php esc_html_e( 'Importer les vignettes', 'orgues-nouvelles' ); ?></h1>
		<p><?php esc_html_e( 'Le plugin crée une vignette quand on enregistre un contenu. Cette page crée celles des contenus déjà publiés, avec leur jumelle anglaise quand il y en a une. Les contenus ne sont pas réenregistrés : leur date de modification ne change pas. Vous pouvez relancer sans risque : les vignettes modifiées à la main ou mises à la corbeille ne sont pas touchées.', 'orgues-nouvelles' ); ?></p>
		<form id="on-bulk-form" onsubmit="return false;">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Types de contenu', 'orgues-nouvelles' ); ?></th>
					<td>
						<?php foreach ( on_vignettes_bulk_labels() as $type => $label ) : ?>
							<label style="display:block;margin-bottom:4px">
								<input type="checkbox" name="types[]" value="<?php echo esc_attr( $type ); ?>" <?php checked( 'product' !== $type ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description"><?php esc_html_e( 'Produits : décochés par défaut.', 'orgues-nouvelles' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="on-bulk-since"><?php esc_html_e( 'Publiés depuis le', 'orgues-nouvelles' ); ?></label></th>
					<td>
						<input type="date" id="on-bulk-since" name="since">
						<p class="description"><?php esc_html_e( 'Facultatif. Vide : tous les contenus publiés, quelle que soit leur date.', 'orgues-nouvelles' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="on-bulk-exclude"><?php esc_html_e( 'Titres à écarter', 'orgues-nouvelles' ); ?></label></th>
					<td>
						<input type="text" id="on-bulk-exclude" name="exclude" class="large-text code" value="" placeholder="Calendrier|Calendar">
						<p class="description"><?php esc_html_e( 'Facultatif. Vide : aucun contenu n\'est écarté. Sinon, expression régulière appliquée au titre de tous les types cochés (majuscules et minuscules sans importance) : les contenus dont le titre correspond sont écartés. Elle marche mieux avec un seul type coché.', 'orgues-nouvelles' ); ?></p>
						<p class="description"><?php esc_html_e( 'Exemples : « Calendrier|Calendar » écarte les titres qui contiennent l\'un ou l\'autre mot ; « ^Test » écarte les titres qui commencent par Test ; « Download|Booklet|in English » écarte les fichiers à télécharger et les versions anglaises en double.', 'orgues-nouvelles' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Contenus déjà dotés d\'une vignette', 'orgues-nouvelles' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="only_missing" value="1" checked>
							<?php esc_html_e( 'Ne traiter que les contenus qui n\'ont pas encore de vignette', 'orgues-nouvelles' ); ?>
						</label>
					</td>
				</tr>
			</table>
			<p>
				<button type="button" class="button button-secondary" id="on-bulk-simulate"><?php esc_html_e( 'Simulation', 'orgues-nouvelles' ); ?></button>
				<button type="button" class="button button-primary" id="on-bulk-run" disabled><?php esc_html_e( 'Importer les vignettes', 'orgues-nouvelles' ); ?></button>
			</p>
		</form>
		<div id="on-bulk-result" aria-live="polite"></div>
		<div id="on-bulk-progress" style="display:none;max-width:480px">
			<div style="background:#dcdcde;border-radius:3px;height:14px;overflow:hidden"><div id="on-bulk-bar" style="background:#2271b1;height:14px;width:0"></div></div>
			<p id="on-bulk-count"></p>
		</div>
		<div id="on-bulk-log" style="max-height:240px;overflow:auto"></div>
	</div>
	<?php
}
