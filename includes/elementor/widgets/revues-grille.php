<?php
/**
 * Élément Elementor « Grille des revues » (page « Revues hors abonnement »).
 *
 * Affiche une couverture par numéro avec, dessous, des cases à cocher :
 * « Papier » (numérique inclus), « Numérique » et « Cahier de partitions seul ».
 * Un seul bouton « Ajouter au panier » ajoute tous les choix d'un coup, puis
 * ouvre le panier.
 *
 * Le plugin ne gère que la logique : quels produits vont avec quel numéro,
 * les prix (lus dans WooCommerce, jamais écrits à la main) et l'ajout au
 * panier. Tout ce qui se voit (libellés, couleurs, polices, tailles, bouton)
 * se règle dans Elementor, dans les onglets de l'élément.
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

use Elementor\Controls_Manager;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

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
    if (!function_exists('wc_get_products')) {
        return array();
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

    return apply_filters('on_revues_grille_numeros', $numeros, $prefixes);
}

class ON_Revues_Grille extends Widget_Base
{
    public function get_name()
    {
        return 'on-revues-grille';
    }

    public function get_title()
    {
        return __('Grille des revues', 'orgues-nouvelles');
    }

    public function get_icon()
    {
        return 'eicon-gallery-grid';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('revues', 'magazine', 'numéros', 'panier', 'papier', 'numérique', 'cahier');
    }

    public function get_style_depends()
    {
        return array('on-revues-grille');
    }

    public function get_script_depends()
    {
        return array('on-revues-grille');
    }

    protected function register_controls()
    {
        // ---------- Libellés ----------
        $this->start_controls_section('section_labels', array('label' => __('Libellés', 'orgues-nouvelles')));
        $labels = array(
            'label_papier'       => array(__('Papier', 'orgues-nouvelles'), __('Papier', 'orgues-nouvelles')),
            'label_papier_note'  => array(__('Précision sous « Papier »', 'orgues-nouvelles'), __('numérique inclus', 'orgues-nouvelles')),
            'label_numerique'    => array(__('Numérique', 'orgues-nouvelles'), __('Numérique', 'orgues-nouvelles')),
            'label_cahier'       => array(__('Cahier de partitions seul', 'orgues-nouvelles'), __('Cahier de partitions seul', 'orgues-nouvelles')),
            'title_prefix'       => array(__('Titre de chaque numéro (avant le numéro)', 'orgues-nouvelles'), __('Orgues Nouvelles n°', 'orgues-nouvelles')),
            'button_text'        => array(__('Bouton', 'orgues-nouvelles'), __('Ajouter au panier', 'orgues-nouvelles')),
            'text_empty'         => array(__('Barre : rien de choisi', 'orgues-nouvelles'), __('Aucun article choisi', 'orgues-nouvelles')),
            'text_one'           => array(__('Barre : « article » (singulier)', 'orgues-nouvelles'), __('article', 'orgues-nouvelles')),
            'text_many'          => array(__('Barre : « articles » (pluriel)', 'orgues-nouvelles'), __('articles', 'orgues-nouvelles')),
            'text_choose'        => array(__('Message : rien de choisi', 'orgues-nouvelles'), __('Choisissez au moins un article.', 'orgues-nouvelles')),
            'text_error'         => array(__('Message : erreur d\'ajout', 'orgues-nouvelles'), __('Impossible d\'ajouter un article. Réessayez.', 'orgues-nouvelles')),
        );
        foreach ($labels as $key => $def) {
            $this->add_control($key, array('label' => $def[0], 'type' => Controls_Manager::TEXT, 'default' => $def[1], 'label_block' => true));
        }
        $this->end_controls_section();

        // ---------- Produits ----------
        $this->start_controls_section('section_products', array('label' => __('Produits', 'orgues-nouvelles')));
        $this->add_control('products_note', array(
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => __('Un numéro est reconnu par le début du nom de ses produits, suivi du numéro (ex. « ONED74 »).', 'orgues-nouvelles'),
            'content_classes' => 'elementor-descriptor',
        ));
        $this->add_control('prefix_papier', array('label' => __('Début du nom : papier', 'orgues-nouvelles'), 'type' => Controls_Manager::TEXT, 'default' => 'Orgues Nouvelles n°', 'label_block' => true));
        $this->add_control('prefix_numerique', array('label' => __('Début du nom : numérique', 'orgues-nouvelles'), 'type' => Controls_Manager::TEXT, 'default' => 'ONED', 'label_block' => true));
        $this->add_control('prefix_cahier', array('label' => __('Début du nom : cahier de partitions', 'orgues-nouvelles'), 'type' => Controls_Manager::TEXT, 'default' => 'CahPart', 'label_block' => true));
        $this->end_controls_section();

        // ---------- Style : grille ----------
        $this->start_controls_section('style_grid', array('label' => __('Grille', 'orgues-nouvelles'), 'tab' => Controls_Manager::TAB_STYLE));
        $this->add_responsive_control('col_min', array(
            'label' => __('Largeur minimale d\'une colonne', 'orgues-nouvelles'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 120, 'max' => 400)),
            'default' => array('unit' => 'px', 'size' => 190),
            'selectors' => array('{{WRAPPER}} .onr-grid' => 'grid-template-columns: repeat(auto-fill, minmax({{SIZE}}{{UNIT}}, 1fr));'),
        ));
        $this->add_responsive_control('gap', array(
            'label' => __('Espace entre les numéros', 'orgues-nouvelles'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 0, 'max' => 80)),
            'default' => array('unit' => 'px', 'size' => 24),
            'selectors' => array('{{WRAPPER}} .onr-grid' => 'gap: {{SIZE}}{{UNIT}};'),
        ));
        $this->end_controls_section();

        // ---------- Style : titre ----------
        $this->start_controls_section('style_title', array('label' => __('Titre du numéro', 'orgues-nouvelles'), 'tab' => Controls_Manager::TAB_STYLE));
        $this->add_group_control(Group_Control_Typography::get_type(), array(
            'name' => 'title_typo',
            'selector' => '{{WRAPPER}} .onr-t',
            'fields_options' => array(
                'typography' => array('default' => 'custom'),
                'font_family' => array('default' => 'Roboto Condensed'),
                'font_size' => array('default' => array('unit' => 'px', 'size' => 17)),
                'font_weight' => array('default' => '500'),
            ),
        ));
        $this->add_control('title_color', array('label' => __('Couleur', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'selectors' => array('{{WRAPPER}} .onr-t' => 'color: {{VALUE}};')));
        $this->end_controls_section();

        // ---------- Style : choix ----------
        $this->start_controls_section('style_options', array('label' => __('Cases à cocher', 'orgues-nouvelles'), 'tab' => Controls_Manager::TAB_STYLE));
        $this->add_group_control(Group_Control_Typography::get_type(), array(
            'name' => 'opt_typo',
            'label' => __('Texte', 'orgues-nouvelles'),
            'selector' => '{{WRAPPER}} .onr-o',
            'fields_options' => array(
                'typography' => array('default' => 'custom'),
                'font_family' => array('default' => 'Roboto Condensed'),
                'font_size' => array('default' => array('unit' => 'px', 'size' => 14)),
            ),
        ));
        $this->add_control('opt_color', array('label' => __('Couleur du texte', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'selectors' => array('{{WRAPPER}} .onr-o' => 'color: {{VALUE}};')));
        $this->add_control('note_color', array('label' => __('Couleur de la précision', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'default' => '#666666', 'selectors' => array('{{WRAPPER}} .onr-o small' => 'color: {{VALUE}};')));
        $this->add_control('note_size', array(
            'label' => __('Taille de la précision', 'orgues-nouvelles'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 9, 'max' => 20)),
            'default' => array('unit' => 'px', 'size' => 11),
            'selectors' => array('{{WRAPPER}} .onr-o small' => 'font-size: {{SIZE}}{{UNIT}};'),
        ));
        $this->add_control('check_color', array('label' => __('Couleur de la case cochée', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'default' => '#CA008A', 'selectors' => array('{{WRAPPER}} .onr-o input' => 'accent-color: {{VALUE}};')));
        $this->end_controls_section();

        // ---------- Style : barre ----------
        $this->start_controls_section('style_bar', array('label' => __('Barre du bas', 'orgues-nouvelles'), 'tab' => Controls_Manager::TAB_STYLE));
        $this->add_control('bar_bg', array('label' => __('Fond', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'default' => '#FFFFFF', 'selectors' => array('{{WRAPPER}} .onr-bar' => 'background: {{VALUE}};')));
        $this->add_group_control(Group_Control_Border::get_type(), array(
            'name' => 'bar_border',
            'selector' => '{{WRAPPER}} .onr-bar',
            'fields_options' => array(
                'border' => array('default' => 'solid'),
                'width' => array('default' => array('top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true)),
                'color' => array('default' => '#D6DAE4'),
            ),
        ));
        $this->add_group_control(Group_Control_Typography::get_type(), array(
            'name' => 'bar_typo',
            'label' => __('Texte (nombre d\'articles et total)', 'orgues-nouvelles'),
            'selector' => '{{WRAPPER}} .onr-rec',
            'fields_options' => array(
                'typography' => array('default' => 'custom'),
                'font_family' => array('default' => 'Roboto Condensed'),
                'font_size' => array('default' => array('unit' => 'px', 'size' => 16)),
                'font_weight' => array('default' => '500'),
            ),
        ));
        $this->end_controls_section();

        // ---------- Style : bouton ----------
        $this->start_controls_section('style_button', array('label' => __('Bouton « Ajouter au panier »', 'orgues-nouvelles'), 'tab' => Controls_Manager::TAB_STYLE));
        $this->add_group_control(Group_Control_Typography::get_type(), array(
            'name' => 'btn_typo',
            'selector' => '{{WRAPPER}} .onr-btn',
            'fields_options' => array(
                'typography' => array('default' => 'custom'),
                'font_family' => array('default' => 'Roboto Condensed'),
                'font_size' => array('default' => array('unit' => 'px', 'size' => 16)),
                'font_weight' => array('default' => '500'),
            ),
        ));
        $this->start_controls_tabs('btn_tabs');
        $this->start_controls_tab('btn_normal', array('label' => __('Normal', 'orgues-nouvelles')));
        $this->add_control('btn_color', array('label' => __('Texte', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'default' => '#FFFFFF', 'selectors' => array('{{WRAPPER}} .onr-btn' => 'color: {{VALUE}};')));
        $this->add_control('btn_bg', array('label' => __('Fond', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'default' => '#CA008A', 'selectors' => array('{{WRAPPER}} .onr-btn' => 'background-color: {{VALUE}};')));
        $this->end_controls_tab();
        $this->start_controls_tab('btn_hover', array('label' => __('Survol / clic', 'orgues-nouvelles')));
        $this->add_control('btn_color_h', array('label' => __('Texte', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'default' => '#CA008A', 'selectors' => array('{{WRAPPER}} .onr-btn:hover, {{WRAPPER}} .onr-btn:focus, {{WRAPPER}} .onr-btn:active' => 'color: {{VALUE}};')));
        $this->add_control('btn_bg_h', array('label' => __('Fond', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'default' => 'rgba(255,255,255,0)', 'selectors' => array('{{WRAPPER}} .onr-btn:hover, {{WRAPPER}} .onr-btn:focus, {{WRAPPER}} .onr-btn:active' => 'background-color: {{VALUE}};')));
        $this->add_control('btn_border_h', array('label' => __('Trait', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'default' => '#CA008A', 'selectors' => array('{{WRAPPER}} .onr-btn:hover, {{WRAPPER}} .onr-btn:focus, {{WRAPPER}} .onr-btn:active' => 'border-color: {{VALUE}};')));
        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->add_group_control(Group_Control_Border::get_type(), array(
            'name' => 'btn_border',
            'selector' => '{{WRAPPER}} .onr-btn',
            'fields_options' => array(
                'border' => array('default' => 'solid'),
                'width' => array('default' => array('top' => '1', 'right' => '1', 'bottom' => '1', 'left' => '1', 'isLinked' => true)),
                'color' => array('default' => '#CA008A'),
            ),
        ));
        $this->add_control('btn_radius', array(
            'label' => __('Angles arrondis', 'orgues-nouvelles'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 0, 'max' => 40)),
            'default' => array('unit' => 'px', 'size' => 5),
            'selectors' => array('{{WRAPPER}} .onr-btn' => 'border-radius: {{SIZE}}{{UNIT}};'),
        ));
        $this->add_control('btn_padding', array(
            'label' => __('Marges intérieures', 'orgues-nouvelles'),
            'type' => Controls_Manager::DIMENSIONS,
            'size_units' => array('px'),
            'default' => array('top' => '12', 'right' => '22', 'bottom' => '12', 'left' => '22', 'unit' => 'px', 'isLinked' => false),
            'selectors' => array('{{WRAPPER}} .onr-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};'),
        ));
        $this->end_controls_section();
    }

    protected function render()
    {
        $s = $this->get_settings_for_display();

        if (!function_exists('wc_get_products')) {
            return;
        }

        $numeros = on_revues_grille_numeros(array(
            'papier'    => $s['prefix_papier'],
            'numerique' => $s['prefix_numerique'],
            'cahier'    => $s['prefix_cahier'],
        ));

        if (!$numeros) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p>' . esc_html__('Grille des revues : aucun produit trouvé. Vérifiez les débuts de nom dans l\'onglet « Produits ».', 'orgues-nouvelles') . '</p>';
            }
            return;
        }

        $ajax = class_exists('WC_AJAX') ? \WC_AJAX::get_endpoint('add_to_cart') : home_url('/?wc-ajax=add_to_cart');
        $cart = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/');

        $options = array(
            'papier'    => array('pn', $s['label_papier'], $s['label_papier_note']),
            'numerique' => array('n', $s['label_numerique'], ''),
            'cahier'    => array('c', $s['label_cahier'], ''),
        );

        echo '<div class="onr" data-ajax="' . esc_url($ajax) . '" data-cart="' . esc_url($cart) . '"'
            . ' data-empty="' . esc_attr($s['text_empty']) . '" data-one="' . esc_attr($s['text_one']) . '" data-many="' . esc_attr($s['text_many']) . '"'
            . ' data-choose="' . esc_attr($s['text_choose']) . '" data-error="' . esc_attr($s['text_error']) . '"'
            . ' data-currency="' . esc_attr(function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '€') . '">';
        echo '<div class="onr-grid">';

        foreach ($numeros as $n => $p) {
            $img_id = 0;
            foreach (array('papier', 'numerique', 'cahier') as $k) {
                if ($p[$k] && $p[$k]->get_image_id()) {
                    $img_id = $p[$k]->get_image_id();
                    break;
                }
            }
            $titre = trim($s['title_prefix']) . $n;

            echo '<div class="onr-c">';
            if ($img_id) {
                echo wp_get_attachment_image($img_id, 'medium_large', false, array('class' => 'onr-cv', 'loading' => 'lazy', 'alt' => $titre));
            }
            echo '<p class="onr-t">' . esc_html($titre) . '</p>';

            foreach ($options as $slot => $o) {
                $prod = $p[$slot];
                if (!$prod) {
                    continue;
                }
                $prix = (float) $prod->get_regular_price();
                echo '<label class="onr-o"><input type="checkbox" data-g="' . (int) $n . '" data-k="' . esc_attr($o[0]) . '" data-id="' . (int) $prod->get_id() . '" data-p="' . esc_attr($prix) . '">';
                echo '<span><b>' . esc_html($o[1]) . '</b>';
                if ($o[2] !== '') {
                    echo ' <small>' . esc_html($o[2]) . '</small>';
                }
                echo '</span><span class="onr-p">' . wp_kses_post(wc_price($prix, array('decimals' => ($prix == floor($prix)) ? 0 : 2))) . '</span></label>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '<div class="onr-bar"><span class="onr-rec">' . esc_html($s['text_empty']) . '</span><span class="onr-msg" role="status"></span>'
            . '<button type="button" class="onr-btn">' . esc_html($s['button_text']) . '</button></div>';
        echo '</div>';
    }
}
