<?php

/**
 * API en lecture seule du compteur : GET /wp-json/on/v1/usage
 *
 * - Réservée aux administrateurs (cookie de connexion, ou mot de passe
 *   d'application WordPress en HTTPS pour un outil externe).
 * - Aucune route d'écriture n'est exposée.
 *
 * Voir docs/comptage-usage.md.
 */

add_action('rest_api_init', 'on_usage_register_rest_routes');

function on_usage_register_rest_routes()
{
    register_rest_route('on/v1', '/usage', array(
        'methods'             => WP_REST_Server::READABLE, // GET seulement
        'callback'            => 'on_usage_rest_get',
        'permission_callback' => 'on_usage_rest_permission',
        'args'                => array(
            'user_id'  => array('type' => 'integer', 'minimum' => 1, 'sanitize_callback' => 'absint'),
            'email'    => array('type' => 'string', 'sanitize_callback' => 'sanitize_email'),
            'numero'   => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            'magazine' => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            'type'     => array('type' => 'string', 'enum' => array('read', 'download')),
            'since'    => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            'page'     => array('type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint'),
            'per_page' => array('type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 500, 'sanitize_callback' => 'absint'),
        ),
    ));
}

/**
 * Administrateurs seulement.
 */
function on_usage_rest_permission()
{
    return current_user_can('manage_options');
}

/**
 * Lit le compteur. Aucune écriture.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function on_usage_rest_get($request)
{
    global $wpdb;
    $table = on_usage_table_name();

    $where = array('1=1');
    $args = array();

    // Un seul client : par identifiant ou par adresse e-mail.
    $user_id = (int) $request->get_param('user_id');
    $email = (string) $request->get_param('email');
    if ($email !== '') {
        $user = get_user_by('email', $email);
        $user_id = $user ? (int) $user->ID : -1; // -1 : aucun client, résultat vide
    }
    if ($user_id !== 0) {
        $where[] = 'u.user_id = %d';
        $args[] = $user_id;
    }

    $numero = (string) $request->get_param('numero');
    if ($numero !== '') {
        $where[] = 'u.numero = %s';
        $args[] = $numero;
    }

    $magazine = (string) $request->get_param('magazine');
    if ($magazine !== '') {
        $where[] = 'u.magazine = %s';
        $args[] = $magazine;
    }

    $type = (string) $request->get_param('type');
    if ($type !== '') {
        $where[] = 'u.type = %s';
        $args[] = $type;
    }

    // Usages dont la dernière date est postérieure à « since » (UTC).
    $since = (string) $request->get_param('since');
    if ($since !== '') {
        $ts = strtotime($since . ' UTC');
        if ($ts === false) {
            return new WP_Error('on_usage_bad_since', 'Paramètre since invalide (exemple : 2026-01-31).', array('status' => 400));
        }
        $where[] = 'u.last_at >= %s';
        $args[] = gmdate('Y-m-d H:i:s', $ts);
    }

    $per_page = max(1, min(500, (int) $request->get_param('per_page')));
    $page = max(1, (int) $request->get_param('page'));
    $offset = ($page - 1) * $per_page;
    $where_sql = implode(' AND ', $where);

    $count_sql = "SELECT COUNT(*) FROM {$table} u WHERE {$where_sql}";
    $total = (int) $wpdb->get_var($args ? $wpdb->prepare($count_sql, $args) : $count_sql);

    $rows_sql = "SELECT u.user_id, usr.user_email AS email, u.product_id, u.numero, u.magazine,
                        u.type, u.count, u.first_at, u.last_at
                 FROM {$table} u
                 LEFT JOIN {$wpdb->users} usr ON usr.ID = u.user_id
                 WHERE {$where_sql}
                 ORDER BY u.last_at DESC, u.id DESC
                 LIMIT %d OFFSET %d";
    $rows = $wpdb->get_results($wpdb->prepare($rows_sql, array_merge($args, array($per_page, $offset))), ARRAY_A);

    $items = array();
    foreach ((array) $rows as $row) {
        $items[] = array(
            'user_id'    => (int) $row['user_id'],
            'email'      => (string) $row['email'],
            'product_id' => (int) $row['product_id'],
            'numero'     => (string) $row['numero'],
            'magazine'   => (string) $row['magazine'],
            'type'       => (string) $row['type'],
            'count'      => (int) $row['count'],
            'first_at'   => $row['first_at'], // UTC
            'last_at'    => $row['last_at'],  // UTC
        );
    }

    $response = rest_ensure_response(array(
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per_page,
        'items'    => $items,
    ));
    $response->header('X-WP-Total', $total);
    $response->header('X-WP-TotalPages', (int) ceil($total / $per_page));
    return $response;
}
