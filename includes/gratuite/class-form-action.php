<?php
/**
 * Elementor Pro form action "Gratuité -26 ans (autorisation)".
 * Loaded only when Elementor Pro registers its form actions (the parent class exists then).
 */
defined( 'ABSPATH' ) || exit;

use Elementor\Controls_Manager;
use ElementorPro\Modules\Forms\Classes\Action_Base;

class ON_Gratuite_Form_Action extends Action_Base {

    public function get_name() {
        return 'on_gratuite';
    }

    public function get_label() {
        return esc_html__( 'Gratuité -26 ans (autorisation)', 'orgues-nouvelles' );
    }

    public function register_settings_section( $widget ) {
        $widget->start_controls_section( 'section_on_gratuite', array(
            'label'     => esc_html__( 'Gratuité -26 ans', 'orgues-nouvelles' ),
            'condition' => array( 'submit_actions' => $this->get_name() ),
        ) );
        $widget->add_control( 'on_gratuite_note', array(
            'type' => Controls_Manager::RAW_HTML,
            'raw'  => esc_html__( 'Identifiants des champs : prenom, nom, email, date_naissance, justificatif (envoi de fichier, 1 fichier), newsletter (facultatif), consentement (acceptation obligatoire), langue (champ caché : fr ou en). Choisir uniquement cette action (et Redirection). Voir docs/gratuite.md.', 'orgues-nouvelles' ),
        ) );
        $widget->end_controls_section();
    }

    public function on_export( $element ) {
        return $element;
    }

    public function run( $record, $ajax_handler ) {
        on_gratuite_process_submission( $record, $ajax_handler );
    }
}
