<?php
/**
 * Page « Revues hors abonnement » : choix papier / numérique / cahier par numéro
 * et un seul bouton « Ajouter au panier ».
 *
 * La page se compose dans Elementor avec trois éléments :
 *   - une « Grille de boucle » (Loop Grid) sur les produits « Revues hors abonnement »,
 *     dont le modèle (image, titre…) se dessine librement ;
 *   - dans ce modèle, l'élément « Choix d'achat du numéro » (ce fichier + revues-choix.php) :
 *     les cases à cocher du numéro, avec leurs prix lus dans WooCommerce ;
 *   - sous la grille, l'élément « Total du choix » (revues-total.php) et un bouton
 *     Elementor ordinaire à qui l'on donne la classe CSS « onr-add » : un clic ajoute
 *     tous les choix au panier, puis ouvre le panier (assets/js/revues-grille.js).
 *
 * Le plugin ne gère que la logique : quels produits vont avec quel numéro, les prix
 * (jamais écrits à la main) et l'ajout au panier. Tout ce qui se voit (libellés,
 * couleurs, polices, tailles, bouton, barre collante) se règle dans Elementor.
 *
 * Un numéro est reconnu par le NOM de ses produits : début du nom (réglable,
 * onglet « Produits ») suivi du numéro. Par défaut :
 *   - papier    : « Orgues Nouvelles n°74 »
 *   - numérique : « ONED74 »  (un produit « … (en téléchargement) » compte aussi)
 *   - cahier    : « CahPart74 »
 * Pour la version anglaise, on règle ces trois débuts de nom dans l'élément
 * de la page anglaise (ex. « Orgues-Nouvelles », « Orgues Nouvelles Music
 * Booklet »). Le filtre `on_revues_grille_numeros` permet d'ajuster la liste.
 */

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Numéro trouvé dans un nom de produit, ou 0.
 *
 * @param string $name   Nom du produit.
 * @param string $prefix Début du nom attendu (insensible à la casse).
 * @return int
 */
function on_revues_grille_numero_du_nom($name, $prefix)
{
    $prefix = trim((string) $prefix);
    if ($prefix === '') {
        return 0;
    }
    $re = '/^\s*' . preg_quote($prefix, '/') . '\s*0*(\d+)(?!\d)/iu';
    return preg_match($re, (string) $name, $m) ? (int) $m[1] : 0;
}

/**
 * Regroupe les produits publiés par numéro.
 *
 * @param array $prefixes Clés : papier, numerique, cahier (débuts de nom).
 * @return array<int,array{papier:?WC_Product,numerique:?WC_Product,cahier:?WC_Product}> triés du plus récent au plus ancien
 */
function on_revues_grille_numeros($prefixes)
{
    static $cache = array();
    if (!function_exists('wc_get_products')) {
        return array();
    }
    $key = md5(wp_json_encode($prefixes));
    if (isset($cache[$key])) {
        return $cache[$key];
    }
    // Noms lus directement en base : on ne charge en objets WooCommerce que les produits retenus.
    global $wpdb;
    $lignes = $wpdb->get_results("SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish' ORDER BY ID DESC");
    $produits = array();
    foreach ((array) $lignes as $l) {
        $retenu = false;
        foreach (array('cahier', 'numerique', 'papier') as $k) {
            if (on_revues_grille_numero_du_nom($l->post_title, $prefixes[$k])) {
                $retenu = true;
                break;
            }
        }
        if ($retenu && ($p = wc_get_product((int) $l->ID))) {
            $produits[] = $p;
        }
    }

    $numeros = array();
    foreach ($produits as $p) {
        $nom = $p->get_name();
        $slot = '';
        $n = on_revues_grille_numero_du_nom($nom, $prefixes['cahier']);
        if ($n) {
            $slot = 'cahier';
        } else {
            $n = on_revues_grille_numero_du_nom($nom, $prefixes['numerique']);
            if ($n) {
                $slot = 'numerique';
            } else {
                $n = on_revues_grille_numero_du_nom($nom, $prefixes['papier']);
                if ($n) {
                    // « Orgues Nouvelles n°69 (en téléchargement) » : c'est le numérique.
                    $slot = preg_match('/t[ée]l[ée]charg|download/iu', $nom) ? 'numerique' : 'papier';
                }
            }
        }
        if (!$n || $slot === '' || !$p->is_purchasable()) {
            continue;
        }
        if (!isset($numeros[$n])) {
            $numeros[$n] = array('papier' => null, 'numerique' => null, 'cahier' => null);
        }
        if ($numeros[$n][$slot] === null) {
            $numeros[$n][$slot] = $p;
        }
    }
    krsort($numeros);

    $cache[$key] = apply_filters('on_revues_grille_numeros', $numeros, $prefixes);
    return $cache[$key];
}


/**
 * Produit « principal » d'un numéro : papier, sinon numérique, sinon cahier.
 */
function on_revues_grille_principal($entry)
{
    return $entry['papier'] ?: ($entry['numerique'] ?: $entry['cahier']);
}

/**
 * Début de nom par défaut des produits, par langue du site.
 */
function on_revues_grille_prefixes_par_defaut($lang = 'fr')
{
    if ($lang === 'en') {
        return array('papier' => 'Orgues Nouvelles n°', 'numerique' => 'Orgues-Nouvelles', 'cahier' => 'Orgues Nouvelles Music Booklet');
    }
    return array('papier' => 'Orgues Nouvelles n°', 'numerique' => 'ONED', 'cahier' => 'CahPart');
}

/**
 * Requête de la « Grille de boucle » : un seul produit par numéro, du plus récent au plus ancien.
 * Dans la grille, mettre l'« ID de requête » : revues_numeros (français) ou revues_numeros_en (anglais).
 */
function on_revues_grille_requete($lang)
{
    return function ($query) use ($lang) {
        // Charger les produits lance d'autres requêtes WordPress : sans cette garde, elles
        // rappellent ce filtre sans fin.
        static $en_cours = false;
        if ($en_cours) {
            return;
        }
        $en_cours = true;
        $ids = array();
        foreach (on_revues_grille_numeros(on_revues_grille_prefixes_par_defaut($lang)) as $entry) {
            $ids[] = (int) on_revues_grille_principal($entry)->get_id();
        }
        $en_cours = false;
        $query->set('post__in', $ids ? $ids : array(0));
        $query->set('orderby', 'post__in');
        $query->set('posts_per_page', -1);
        $query->set('nopaging', true);
        $query->set('tax_query', array());
    };
}
if (function_exists('add_action')) {
    add_action('elementor/query/revues_numeros', on_revues_grille_requete('fr'));
    add_action('elementor/query/revues_numeros_en', on_revues_grille_requete('en'));
}
