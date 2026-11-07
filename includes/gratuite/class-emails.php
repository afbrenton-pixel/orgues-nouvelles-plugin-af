<?php
/**
 * Free student subscription: WooCommerce e-mail classes. Loaded by emails.php once
 * WooCommerce's e-mail system exists. Modelled on WC_Email_Justificatif_Etudiant.
 */
defined( 'ABSPATH' ) || exit;

include_once dirname( WC_PLUGIN_FILE ) . '/includes/emails/class-wc-email.php';

if ( ! class_exists( 'ON_Gratuite_Email_Confirmation' ) ) {

    /** Confirmation of the address, sent to the applicant. */
    class ON_Gratuite_Email_Confirmation extends WC_Email {
        /** @var string 'fr' or 'en'. */
        protected $lang = 'fr';
        /** @var bool Request made for another person. */
        protected $for_other = false;

        public function __construct() {
            $this->id             = 'on_gratuite_confirmation';
            $this->customer_email = true;
            $this->title          = 'Gratuité -26 ans : confirmation de la demande';
            $this->description    = 'Envoyé à la personne qui demande l\'abonnement gratuit pour qu\'elle confirme son adresse e-mail (français ou anglais selon la page du formulaire).';
            $this->template_html  = 'emails/gratuite-confirmation.php';
            $this->template_plain = 'emails/plain/gratuite-confirmation.php';
            $this->template_base  = ORGUES_NOUVELLES_PLUGIN_DIR . 'templates/';
            $this->placeholders   = array( '{first_name}' => '', '{link}' => '', '{days}' => '' );
            parent::__construct();
        }

        /** Send the e-mail. @return bool */
        public function trigger( $post_id, array $d, $url, $days ) {
            if ( ! $this->is_enabled() ) {
                return false;
            }
            $this->setup_locale();
            try {
                $this->object    = array( 'post_id' => $post_id, 'data' => $d, 'url' => $url, 'days' => (int) $days );
                $this->lang      = 'en' === $d['langue'] ? 'en' : 'fr';
                $this->for_other = ! empty( $d['pour_autre'] );
                $this->recipient = $d['email'];
                $this->placeholders['{first_name}'] = $d['prenom'];
                $this->placeholders['{link}']       = $url;
                $this->placeholders['{days}']       = (string) (int) $days;
                return (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
            } finally {
                $this->restore_locale();
            }
        }

        /** Option key for the current language (French options keep the standard names). */
        protected function on_gratuite_key( $name ) {
            return 'en' === $this->lang ? $name . '_en' : $name;
        }

        public function get_default_subject() {
            return on_gratuite_confirmation_texts( 'fr' )['subject'];
        }

        public function get_default_heading() {
            return on_gratuite_confirmation_texts( 'fr' )['heading'];
        }

        public function get_subject() {
            $texts   = on_gratuite_confirmation_texts( $this->lang, $this->for_other );
            $subject = $this->get_option( $this->on_gratuite_key( 'subject' ), $texts['subject'] ); // WooCommerce's own field has an empty default.
            return apply_filters( 'woocommerce_email_subject_' . $this->id, $this->format_string( $subject ), $this->object, $this );
        }

        public function get_heading() {
            $texts   = on_gratuite_confirmation_texts( $this->lang, $this->for_other );
            $heading = $this->get_option( $this->on_gratuite_key( 'heading' ), $texts['heading'] );
            return apply_filters( 'woocommerce_email_heading_' . $this->id, $this->format_string( $heading ), $this->object, $this );
        }

        public function init_form_fields() {
            parent::init_form_fields();
            $fr = on_gratuite_confirmation_texts( 'fr' );
            $en = on_gratuite_confirmation_texts( 'en' );
            $help = 'Espaces réservés : {first_name} (prénom), {link} (lien de confirmation), {days} (durée de validité). Une ligne vide sépare les paragraphes.';
            $extra = array(
                'subject_en'      => array( 'title' => 'Objet (anglais)', 'type' => 'text', 'default' => $en['subject'], 'desc_tip' => false ),
                'heading_en'      => array( 'title' => 'Titre (anglais)', 'type' => 'text', 'default' => $en['heading'], 'desc_tip' => false ),
                'body_fr'         => array( 'title' => 'Texte, français', 'type' => 'textarea', 'css' => 'width:600px; height: 200px;', 'description' => $help, 'default' => $fr['body'] ),
                'body_en'         => array( 'title' => 'Texte, anglais', 'type' => 'textarea', 'css' => 'width:600px; height: 200px;', 'description' => $help, 'default' => $en['body'] ),
                'body_fr_other'   => array( 'title' => 'Texte, français (demande pour une autre personne)', 'type' => 'textarea', 'css' => 'width:600px; height: 200px;', 'description' => $help, 'default' => on_gratuite_confirmation_texts( 'fr', true )['body'] ),
                'body_en_other'   => array( 'title' => 'Texte, anglais (demande pour une autre personne)', 'type' => 'textarea', 'css' => 'width:600px; height: 200px;', 'description' => $help, 'default' => on_gratuite_confirmation_texts( 'en', true )['body'] ),
            );
            $fields = array();
            foreach ( $this->form_fields as $key => $value ) {
                $fields[ $key ] = $value;
                if ( 'heading' === $key ) {
                    $fields = array_merge( $fields, $extra );
                }
            }
            $this->form_fields = $fields;
            if ( isset( $this->form_fields['subject'] ) ) {
                $this->form_fields['subject']['title'] = 'Objet (français)';
            }
            if ( isset( $this->form_fields['heading'] ) ) {
                $this->form_fields['heading']['title'] = 'Titre (français)';
            }
        }

        /** Paragraphs of the body for the current language and case, placeholders replaced. */
        public function on_gratuite_body_text( $plain ) {
            $name = 'body_' . $this->lang . ( $this->for_other ? '_other' : '' );
            $text = (string) $this->get_option( $name );
            if ( '' === trim( $text ) ) {
                $text = on_gratuite_confirmation_texts( $this->lang, $this->for_other )['body'];
            }
            $o    = $this->object;
            $link = $plain ? $o['url'] : '{{LINK}}';
            $text = on_gratuite_fill_confirmation_text( $text, $o['data']['prenom'], $link, $o['days'] );
            if ( $plain ) {
                return $text;
            }
            return str_replace( '{{LINK}}', '<a href="' . esc_url( $o['url'] ) . '">' . esc_html( $o['url'] ) . '</a>', wpautop( wp_kses( $text, array() ) ) );
        }

        protected function on_gratuite_template_args( $plain ) {
            return array(
                'email'              => $this,
                'email_heading'      => $this->get_heading(),
                'greeting'           => ( 'en' === $this->lang ? 'Hello ' : 'Bonjour ' ) . $this->object['data']['prenom'] . ',',
                'email_body'         => $this->on_gratuite_body_text( $plain ),
                'additional_content' => $this->get_additional_content(),
                'plain_text'         => $plain,
                'sent_to_admin'      => false,
            );
        }

        public function get_content_html() {
            ob_start();
            wc_get_template( $this->template_html, $this->on_gratuite_template_args( false ), '', $this->template_base );
            return ob_get_clean();
        }

        public function get_content_plain() {
            ob_start();
            wc_get_template( $this->template_plain, $this->on_gratuite_template_args( true ), '', $this->template_base );
            return ob_get_clean();
        }
    }
}

if ( ! class_exists( 'ON_Gratuite_Email_Notification' ) ) {

    /** New confirmed request, sent to the team. Never contains the proof or a link to it. */
    class ON_Gratuite_Email_Notification extends WC_Email {
        public function __construct() {
            $this->id             = 'on_gratuite_notification';
            $this->title          = 'Gratuité -26 ans : nouvelle demande';
            $this->description    = 'Envoyé à l\'équipe quand une demande de gratuité est confirmée par la personne (adresse vérifiée) et attend une décision.';
            $this->template_html  = 'emails/gratuite-notification.php';
            $this->template_plain = 'emails/plain/gratuite-notification.php';
            $this->template_base  = ORGUES_NOUVELLES_PLUGIN_DIR . 'templates/';
            $this->placeholders   = array( '{name}' => '' );
            parent::__construct();
            $this->recipient = $this->get_option( 'recipient', on_gratuite_settings()['notify_email'] );
        }

        public function trigger( $post_id, array $d ) {
            if ( ! $this->is_enabled() ) {
                return false;
            }
            $this->setup_locale();
            try {
                $this->object = array( 'post_id' => $post_id, 'data' => $d );
                $this->placeholders['{name}'] = mb_strtoupper( $d['nom'] ) . ' ' . $d['prenom'];
                return (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
            } finally {
                $this->restore_locale();
            }
        }

        public function get_default_subject() {
            return '[Gratuité -26 ans] Nouvelle demande : {name}';
        }

        public function get_default_heading() {
            return 'Nouvelle demande de gratuité';
        }

        protected function on_gratuite_template_args( $plain ) {
            return array(
                'email'              => $this,
                'email_heading'      => $this->get_heading(),
                'lines'              => on_gratuite_notification_lines( $this->object['post_id'], $this->object['data'] ),
                'additional_content' => $this->get_additional_content(),
                'plain_text'         => $plain,
                'sent_to_admin'      => true,
            );
        }

        public function get_content_html() {
            ob_start();
            wc_get_template( $this->template_html, $this->on_gratuite_template_args( false ), '', $this->template_base );
            return ob_get_clean();
        }

        public function get_content_plain() {
            ob_start();
            wc_get_template( $this->template_plain, $this->on_gratuite_template_args( true ), '', $this->template_base );
            return ob_get_clean();
        }
    }
}
