<?php

include_once dirname(WC_PLUGIN_FILE) . '/includes/emails/class-wc-email.php';
if (!class_exists('WC_Email_Justificatif_Etudiant')):
    class WC_Email_Justificatif_Etudiant extends WC_Email
    {

        public function __construct()
        {
            $this->id = 'justificatif_etudiant';
            $this->title = __('Demande de justificatif étudiants', 'orgues-nouvelles');
            $this->recipient = $this->get_option('recipient', get_option('admin_email', 'info@orgues-nouvelles.org'));
            $this->description = __('Cet email est envoyé lorsqu\'un abonné choisit le tarif étudiant pour lui demander d\'envoyer un justificatif.', 'orgues-nouvelles');
            $this->template_html = 'emails/justificatif_etudiant.php';
            $this->template_plain = 'emails/plain/justificatif_etudiant.php';
            $this->template_base = ORGUES_NOUVELLES_PLUGIN_DIR . 'templates/';
            $this->placeholders = array(
                '{order_date}' => '',
                '{order_number}' => '',
            );

            // Call parent constructor
            parent::__construct();
        }

        function trigger($order)
        {
            if (!$this->is_enabled() || !on_student_proof_is_waiting($order) || $order->get_meta('_on_student_proof_request_sent', true)) {
                return;
            }
            $this->setup_locale();
            try {
                $this->object = $order;
                $customer_email = $order->get_billing_email();
                $this->recipient = $customer_email;
                $this->placeholders['{order_date}'] = wc_format_datetime($this->object->get_date_created());
                $this->placeholders['{order_number}'] = $this->object->get_order_number();
                if ($this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments())) {
                    $order->update_meta_data('_on_student_proof_request_sent', gmdate('Y-m-d H:i:s'));
                    $order->save();
                }
            } finally {
                $this->restore_locale();
            }
        }

        public function get_headers()
        {
            $headers = "Content-Type: " . $this->get_content_type() . "\r\n";
            $headers .= "From: " . $this->get_admin_recipient() . "\r\n";
            $headers .= "Reply-To: " . $this->get_admin_recipient() . "\r\n";
            $headers .= "Cc: " . $this->get_admin_recipient() . "\r\n";
            return apply_filters('woocommerce_email_headers', $headers, $this->id, $this->object);
        }

        public function get_admin_recipient()
        {
            return $this->get_option('admin_recipient', get_option('admin_email', ''));
        }

        public function get_default_subject()
        {
            return __('[{site_title}]: Demande de justificatif pour votre commande', 'orgues-nouvelles');
        }

        /**
         * Get email heading.
         *
         * @since  3.1.0
         * @return string
         */
        public function get_default_heading()
        {
            return __('Demande de justificatif pour votre Abonnement', 'orgues-nouvelles');
        }

        public function init_form_fields() {
            parent::init_form_fields();
            $form_fields = $this->form_fields;
            $this->form_fields = array();
            foreach ($form_fields as $key => $value) {
                if ($key === 'subject') {
                    $this->form_fields['admin_recipient'] = array(
                        'title' => __('Copy email', 'woocommerce'),
                        'type' => 'email',
                        'desc_tip' => true,
                        'description' => __('Email address to send a copy of this email to.', 'woocommerce'),
                        'placeholder' => $this->recipient,
                        'default' => '',
                    );
                }
                if ($key === 'additional_content') {
                    $this->form_fields['body'] = array(
                        'title' => __('Contenu de l\'email', 'orgues-nouvelles'),
                        'type' => 'textarea',
                        'css' => 'width:600px; height: 150px;',
                        'description' => __('Texte de l\'email envoyé aux clients lorsqu\'ils choisissent le tarif étudiant pour leur demander un justificatif.', 'orgues-nouvelles'),
                        'default' => $this->get_default_body(),
                        'desc_tip' => true,
                    );
                }
                $this->form_fields[$key] = $value;
            }
        }

        public function get_default_body()
        {
            return __('Nous vous demandons de bien vouloir nous fournir un justificatif de votre statut étudiant pour valider votre abonnement.', 'orgues-nouvelles');
        }

        /**
         * Returns the email body content.
         *
         * @since 1.7.0
         *
         * @return string HTML
         */
        public function get_body()
        {

            $email_id = strtolower($this->id);

            /**
             * Filters the membership email body.
             *
             * @since 1.7.0
             *
             * @param string $body email body content
             * @param \WC_Email_Justificatif_Etudiant current email instance
             */
            $body = (string) apply_filters("{$email_id}_email_body", $this->format_string($this->get_option('body')), $this->object);

            if (empty($body) || !is_string($body) || '' === trim($body)) {
                $body = $this->get_default_body();
            }

            // convert relative URLs to absolute for links href and images src attributes
            $domain = get_home_url();
            $replace = array();
            $replace['/href="(?!https?:\/\/)(?!data:)(?!#)/'] = 'href="' . $domain;
            $replace['/src="(?!https?:\/\/)(?!data:)(?!#)/'] = 'src="' . $domain;

            $body = preg_replace(array_keys($replace), array_values($replace), $body);

            return $body;
        }

        public function is_customer_email()
        {
            return true;
        }

        /**
         * Returns the arguments that should be passed to an email template.
         *
         * @since 1.12.0
         *
         * @param array<string, mixed> $args default args
         * @return array<string, mixed> associative array
         */
        protected function get_template_args($args = []): array
        {

            return [
                'order' => $this->object,
                'email' => $this,
                'email_heading' => $this->get_heading(),
                'email_body' => $this->get_body(),
                'additional_content' => $this->get_additional_content(),
            ];
        }


        /**
         * Returns the email HTML content.
         *
         * @since 1.7.0
         *
         * @return string HTML
         */
        public function get_content_html()
        {

            $args = array('plain_text' => false);

            ob_start();

            wc_get_template($this->template_html, array_merge($args, $this->get_template_args($args)), '', $this->template_base);

            return ob_get_clean();
        }


        /**
         * Returns the email plain text content.
         *
         * @since 1.7.0
         *
         * @return string plain text
         */
        public function get_content_plain()
        {

            $args = array('plain_text' => true);

            ob_start();

            wc_get_template($this->template_plain, array_merge($args, $this->get_template_args($args)), '', $this->template_base);

            return ob_get_clean();
        }

        
    }

    add_action('woocommerce_email_classes', 'add_justificatif_etudiant_email');
    function add_justificatif_etudiant_email($email_classes)
    {
        $email_classes['WC_Email_Justificatif_Etudiant'] = new WC_Email_Justificatif_Etudiant();
        return $email_classes;
    }

endif;

// Ajoute le champ à l'interface d'administration des variations
add_action('woocommerce_product_after_variable_attributes', 'ajouter_champ_justificatif', 10, 3);
function ajouter_champ_justificatif($loop, $variation_data, $variation)
{
    woocommerce_wp_checkbox(array(
        'id' => 'demande_justificatif[' . $loop . ']',
        'label' => __('Demande de justificatif', 'orgues-nouvelles'),
        'description' => __('Cochez cette case pour demander un justificatif pour cette variation.', 'orgues-nouvelles'),
        'desc_tip' => true,
        'value' => get_post_meta($variation->ID, '_demande_justificatif', true),
    ));
}

// Enregistre la valeur du champ
add_action('woocommerce_save_product_variation', 'enregistrer_champ_justificatif', 10, 2);
function enregistrer_champ_justificatif($variation_id, $i)
{
    if (isset($_POST['demande_justificatif'][$i])) {
        update_post_meta($variation_id, '_demande_justificatif', 'yes');
    } else {
        update_post_meta($variation_id, '_demande_justificatif', 'no');
    }
}

