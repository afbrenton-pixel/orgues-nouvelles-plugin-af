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
    $produits = wc_get_products(array('status' => 'publish', 'limit' => -1, 'orderby' => 'ID', 'order' => 'DESC'));

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
