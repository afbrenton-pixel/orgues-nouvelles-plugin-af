<?php
/**
 * Élément Elementor « Choix d'achat du numéro » : les cases à cocher d'un numéro
 * (Papier avec numérique inclus, Numérique, Cahier de partitions seul).
 *
 * À placer dans le modèle d'une « Grille de boucle » dont la requête porte sur les
 * produits « Revues hors abonnement ». Il repère le numéro du produit en cours et
 * retrouve ses autres produits (voir revues-grille.php). Les prix viennent de
 * WooCommerce. Voir docs/revues-grille.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/revues-grille.php';

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class ON_Revues_Choix extends Widget_Base
{
    public function get_name()
    {
        return 'on-revues-choix';
    }

    public function get_title()
    {
        return __('Choix d\'achat du numéro', 'orgues-nouvelles');
    }

    public function get_icon()
    {
        return 'eicon-checkbox';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('revues', 'magazine', 'numéros', 'panier', 'papier', 'numérique', 'cahier', 'achat');
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
        $this->start_controls_section('section_labels', array('label' => __('Libellés', 'orgues-nouvelles')));
        $labels = array(
            'label_papier'      => array(__('Papier', 'orgues-nouvelles'), __('Papier', 'orgues-nouvelles')),
            'label_papier_note' => array(__('Précision sous « Papier »', 'orgues-nouvelles'), __('numérique inclus', 'orgues-nouvelles')),
            'label_numerique'   => array(__('Numérique', 'orgues-nouvelles'), __('Numérique', 'orgues-nouvelles')),
            'label_soldout'     => array(__('Papier épuisé', 'orgues-nouvelles'), __('épuisé', 'orgues-nouvelles')),
            'label_title'       => array(__('Titre du numéro (avant le numéro)', 'orgues-nouvelles'), __('Orgues Nouvelles n°', 'orgues-nouvelles')),
            'label_cahier'      => array(__('Cahier de partitions seul', 'orgues-nouvelles'), __('Cahier de partitions seul', 'orgues-nouvelles')),
        );
        foreach ($labels as $key => $def) {
            $this->add_control($key, array('label' => $def[0], 'type' => Controls_Manager::TEXT, 'default' => $def[1], 'label_block' => true));
        }
        $this->end_controls_section();

        $this->start_controls_section('section_title', array('label' => __('Titre', 'orgues-nouvelles')));
        $this->add_control('show_title', array('label' => __('Afficher le titre du numéro', 'orgues-nouvelles'), 'type' => Controls_Manager::SWITCHER, 'return_value' => 'yes', 'default' => 'yes'));
        $this->end_controls_section();

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

        $this->start_controls_section('style_title', array('label' => __('Titre', 'orgues-nouvelles'), 'tab' => Controls_Manager::TAB_STYLE));
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
        $this->add_control('opt_gap', array(
            'label' => __('Espace entre les choix', 'orgues-nouvelles'),
            'type' => Controls_Manager::SLIDER,
            'size_units' => array('px'),
            'range' => array('px' => array('min' => 0, 'max' => 30)),
            'default' => array('unit' => 'px', 'size' => 5),
            'selectors' => array('{{WRAPPER}} .onr-o' => 'margin: {{SIZE}}{{UNIT}} 0;'),
        ));
        $this->end_controls_section();
    }

    protected function render()
    {
        $s = $this->get_settings_for_display();

        $options = array(
            'papier'    => array('pn', $s['label_papier'], $s['label_papier_note']),
            'numerique' => array('n', $s['label_numerique'], ''),
            'cahier'    => array('c', $s['label_cahier'], ''),
        );

        $edit = \Elementor\Plugin::$instance->editor->is_edit_mode();
        $numero = 0;
        $entry = null;

        $current = get_the_ID();
        foreach (on_revues_grille_numeros(array('papier' => $s['prefix_papier'], 'numerique' => $s['prefix_numerique'], 'cahier' => $s['prefix_cahier'])) as $n => $p) {
            foreach ($p as $prod) {
                if ($prod && (int) $prod->get_id() === (int) $current) {
                    $numero = (int) $n;
                    $entry = $p;
                    break 2;
                }
            }
        }

        // Un seul bloc par numéro : celui de son produit principal (papier, sinon numérique, sinon cahier).
        if ($entry) {
            $principal = on_revues_grille_principal($entry);
            if ((int) $principal->get_id() !== (int) $current) {
                return;
            }
        }

        // Dans l'éditeur, hors boucle : un exemple pour pouvoir régler le style.
        $demo = false;
        if (!$entry) {
            if (!$edit) {
                return;
            }
            $demo = true;
        }

        echo '<div class="onr-choice">';
        if ($s['show_title'] === 'yes') {
            echo '<div class="onr-t">' . esc_html(trim($s['label_title']) . ($demo ? '74' : $numero)) . '</div>';
        }
        foreach ($options as $slot => $o) {
            $prod = $demo ? null : $entry[$slot];
            if (!$demo && !$prod) {
                // Papier plus disponible alors que d'autres versions existent : on l'indique.
                if ($slot === 'papier' && $s['label_soldout'] !== '') {
                    echo '<div class="onr-o onr-off"><span><b>' . esc_html($o[1]) . '</b></span><span class="onr-p">' . esc_html($s['label_soldout']) . '</span></div>';
                }
                continue;
            }
            $prix = $demo ? 22 : (float) $prod->get_regular_price();
            echo '<label class="onr-o"><input type="checkbox" data-g="' . (int) $numero . '" data-k="' . esc_attr($o[0]) . '" data-id="' . (int) ($demo ? 0 : $prod->get_id()) . '" data-p="' . esc_attr($prix) . '">';
            echo '<span><b>' . esc_html($o[1]) . '</b>';
            if ($o[2] !== '') {
                echo ' <small>' . esc_html($o[2]) . '</small>';
            }
            echo '</span><span class="onr-p">' . wp_kses_post(wc_price($prix, array('decimals' => ($prix == floor($prix)) ? 0 : 2))) . '</span></label>';
        }
        echo '</div>';
    }
}
