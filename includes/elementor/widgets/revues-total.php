<?php
/**
 * Élément Elementor « Total du choix » : « 2 articles · 27 € » et les messages du
 * bouton d'ajout au panier. À placer sous la « Grille de boucle » des revues,
 * avec un bouton Elementor ordinaire portant la classe CSS « on-revues-add ».
 * Voir includes/elementor/widgets/revues-grille.php et docs/revues-grille.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class ON_Revues_Total extends Widget_Base
{
    public function get_name()
    {
        return 'on-revues-total';
    }

    public function get_title()
    {
        return __('Total du choix', 'orgues-nouvelles');
    }

    public function get_icon()
    {
        return 'eicon-product-price';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('revues', 'total', 'panier', 'articles');
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
        $this->start_controls_section('section_texts', array('label' => __('Textes', 'orgues-nouvelles')));
        $texts = array(
            'text_empty'  => array(__('Rien de choisi', 'orgues-nouvelles'), __('Aucun article choisi', 'orgues-nouvelles')),
            'text_one'    => array(__('« article » (singulier)', 'orgues-nouvelles'), __('article', 'orgues-nouvelles')),
            'text_many'   => array(__('« articles » (pluriel)', 'orgues-nouvelles'), __('articles', 'orgues-nouvelles')),
            'text_choose' => array(__('Message : rien de choisi', 'orgues-nouvelles'), __('Choisissez au moins un article.', 'orgues-nouvelles')),
            'text_error'  => array(__('Message : erreur d\'ajout', 'orgues-nouvelles'), __('Impossible d\'ajouter un article. Réessayez.', 'orgues-nouvelles')),
        );
        foreach ($texts as $key => $def) {
            $this->add_control($key, array('label' => $def[0], 'type' => Controls_Manager::TEXT, 'default' => $def[1], 'label_block' => true));
        }
        $this->add_control('texts_note', array(
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => __('Le bouton « Ajouter au panier » est un bouton Elementor ordinaire : donnez-lui la classe CSS « on-revues-add » (Avancé > Classes CSS).', 'orgues-nouvelles'),
            'content_classes' => 'elementor-descriptor',
        ));
        $this->end_controls_section();

        $this->start_controls_section('style_total', array('label' => __('Texte', 'orgues-nouvelles'), 'tab' => Controls_Manager::TAB_STYLE));
        $this->add_group_control(Group_Control_Typography::get_type(), array(
            'name' => 'total_typo',
            'selector' => '{{WRAPPER}} .on-revues-rec',
            'fields_options' => array(
                'typography' => array('default' => 'custom'),
                'font_family' => array('default' => 'Roboto Condensed'),
                'font_size' => array('default' => array('unit' => 'px', 'size' => 16)),
                'font_weight' => array('default' => '500'),
            ),
        ));
        $this->add_control('total_color', array('label' => __('Couleur', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'selectors' => array('{{WRAPPER}} .on-revues-rec' => 'color: {{VALUE}};')));
        $this->add_control('msg_color', array('label' => __('Couleur des messages', 'orgues-nouvelles'), 'type' => Controls_Manager::COLOR, 'default' => '#AA0000', 'selectors' => array('{{WRAPPER}} .on-revues-msg' => 'color: {{VALUE}};')));
        $this->end_controls_section();
    }

    protected function render()
    {
        $s = $this->get_settings_for_display();
        $ajax = class_exists('WC_AJAX') ? \WC_AJAX::get_endpoint('add_to_cart') : home_url('/?wc-ajax=add_to_cart');
        $cart = function_exists('wc_get_cart_url') ? wc_get_cart_url() : home_url('/');

        echo '<div class="on-revues-total" data-ajax="' . esc_url($ajax) . '" data-cart="' . esc_url($cart) . '"'
            . ' data-empty="' . esc_attr($s['text_empty']) . '" data-one="' . esc_attr($s['text_one']) . '" data-many="' . esc_attr($s['text_many']) . '"'
            . ' data-choose="' . esc_attr($s['text_choose']) . '" data-error="' . esc_attr($s['text_error']) . '"'
            . ' data-currency="' . esc_attr(function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol()) : '€') . '">'
            . '<span class="on-revues-rec">' . esc_html($s['text_empty']) . '</span> <span class="on-revues-msg" role="status"></span></div>';
    }
}
