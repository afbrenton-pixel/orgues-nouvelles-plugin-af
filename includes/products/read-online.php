<?php
/**
 * Lecture en ligne des PDF (cahiers de partitions, magazines numériques).
 *
 * Fournit au lecteur en ligne (PDF.js) le PDF d'un produit téléchargeable, en
 * appliquant EXACTEMENT la même règle d'accès que les téléchargements et que
 * « Mon compte > Mes magazines » :
 *
 *   le produit est gratuit pour la personne connectée, c'est-à-dire que son
 *   numéro figure dans on_liste_numeros() pour les plans du produit
 *   (voir product-free-for-plans.php).
 *
 * Adresse : /?on_action=read_pdf&product=cahpart73   (slug du produit)
 *      ou : /?on_action=read_pdf&product_id=47598    (identifiant du produit)
 *
 * Pas de nonce : la requête ne modifie rien, l'accès dépend du cookie de
 * connexion et de la règle ci-dessus. (Un nonce serait mis en cache avec la
 * page par Elementor et expirerait.)
 *
 * Documentation : docs/lecture-en-ligne.md
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Retourne le produit demandé (par slug ou identifiant), ou null.
 */
function on_read_online_get_product()
{
    $product = null;
    if (!empty($_GET['product_id'])) {
        $product = wc_get_product(absint($_GET['product_id']));
    } elseif (!empty($_GET['product'])) {
        $slug = sanitize_title(wp_unslash($_GET['product']));
        $post = get_page_by_path($slug, OBJECT, 'product');
        if ($post) {
            $product = wc_get_product($post->ID);
        }
    }
    return ($product && is_a($product, 'WC_Product')) ? $product : null;
}

/**
 * Règle d'accès : la même que les téléchargements (produit gratuit pour
 * l'utilisateur courant). Filtrable : on_read_online_can_access.
 */
function on_read_online_user_can_read($product)
{
    $can = is_user_logged_in()
        && $product->is_downloadable()
        && (float) $product->get_price() <= 0;

    // Un produit non publié ou protégé par mot de passe n'est lisible que par
    // quelqu'un qui peut le modifier (liste de contrôle, point 1).
    if ($can && ($product->get_status() !== 'publish' || post_password_required($product->get_id()))
        && !current_user_can('edit_post', $product->get_id())) {
        $can = false;
    }

    return (bool) apply_filters('on_read_online_can_access', $can, $product, get_current_user_id());
}

/**
 * Chemin local du premier fichier téléchargeable du produit, ou '' si absent
 * ou distant.
 */
function on_read_online_file_path($product)
{
    $downloads = $product->get_downloads();
    if (empty($downloads)) {
        return '';
    }
    $keys = array_keys($downloads);
    $file = $product->get_file_download_path($keys[0]);
    if (empty($file)) {
        return '';
    }

    if (class_exists('WC_Download_Handler') && is_callable(array('WC_Download_Handler', 'parse_file_path'))) {
        $parsed = WC_Download_Handler::parse_file_path($file);
        if (!empty($parsed['remote_file']) || empty($parsed['file_path'])) {
            return '';
        }
        $path = $parsed['file_path'];
    } else {
        $path = str_replace(content_url(), WP_CONTENT_DIR, $file);
    }

    return (is_string($path) && is_readable($path) && is_file($path)) ? $path : '';
}

function on_read_online_deny($code, $message)
{
    status_header($code);
    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

/**
 * Envoie le fichier avec prise en charge des requêtes partielles (Range),
 * dont PDF.js a besoin.
 */
function on_read_online_stream($path, $product = null)
{
    $size = filesize($path);
    $start = 0;
    $end = $size - 1;
    $status = 200;

    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/^bytes=(\d*)-(\d*)$/', trim($_SERVER['HTTP_RANGE']), $m)) {
        if ($m[1] === '' && $m[2] !== '') { // derniers N octets
            $start = max(0, $size - (int) $m[2]);
        } else {
            $start = (int) $m[1];
            if ($m[2] !== '') {
                $end = min($end, (int) $m[2]);
            }
        }
        if ($start > $end || $start >= $size) {
            status_header(416);
            header('Content-Range: bytes */' . $size);
            exit;
        }
        $status = 206;
    }

    // === COMPTAGE-USAGE (début) ===
    // Une lecture compte à l'ouverture du PDF : la requête qui débute au début
    // du fichier. Les morceaux suivants demandés par le lecteur (Range) ne
    // comptent pas, ni les requêtes HEAD. Voir docs/comptage-usage.md.
    if ($start === 0 && $product && ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD' && function_exists('on_usage_record')) {
        // PDF.js peut demander deux fois le début du fichier pour une seule
        // ouverture : une même lecture (même personne, même produit) n'est
        // comptée qu'une fois par minute.
        $key = 'on_read_' . get_current_user_id() . '_' . $product->get_id();
        if (get_transient($key) === false) {
            set_transient($key, 1, 60);
            on_usage_record(get_current_user_id(), $product->get_id(), 'read');
        }
    }
    // === COMPTAGE-USAGE (fin) ===

    if (function_exists('ini_set')) {
        @ini_set('zlib.output_compression', 'Off');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    status_header($status);
    header('Content-Type: application/pdf');
    // Nom neutre (identifiant du produit) : le vrai nom du fichier, renommé au
    // hasard par WooCommerce, ne doit jamais sortir (il rendrait l'adresse
    // réelle du PDF devinable).
    $public_name = $product ? sanitize_file_name($product->get_slug()) : 'document';
    header('Content-Disposition: inline; filename="' . $public_name . '.pdf"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . ($end - $start + 1));
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    $fh = fopen($path, 'rb');
    if ($fh === false) {
        exit;
    }
    fseek($fh, $start);
    $left = $end - $start + 1;
    while ($left > 0 && !feof($fh)) {
        $chunk = fread($fh, min(8192, $left));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        flush();
    }
    fclose($fh);
    exit;
}

function on_read_online_handle_request()
{
    if (!isset($_GET['on_action']) || $_GET['on_action'] !== 'read_pdf') {
        return;
    }

    if (!is_user_logged_in()) {
        on_read_online_deny(401, 'Connexion requise.');
    }

    $product = on_read_online_get_product();
    if (!$product) {
        on_read_online_deny(404, 'Produit introuvable.');
    }

    if (!on_read_online_user_can_read($product)) {
        on_read_online_deny(403, 'Accès non autorisé à ce numéro.');
    }

    $path = on_read_online_file_path($product);
    if ($path === '' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf') {
        on_read_online_deny(404, 'Fichier PDF indisponible.');
    }

    on_read_online_stream($path, $product);
}
add_action('template_redirect', 'on_read_online_handle_request', 1);
