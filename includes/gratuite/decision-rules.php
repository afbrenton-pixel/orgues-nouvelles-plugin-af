<?php
/**
 * Free student subscription, step 4: pure rules and texts of the decision (unit tested).
 */
defined( 'ABSPATH' ) || exit;

/** A decision can only be taken on a request confirmed by e-mail and still waiting. */
function on_gratuite_can_decide( $status ) {
    return 'en_attente' === $status;
}

/**
 * Texts of the acceptance and refusal e-mails.
 * Placeholders: {first_name} {end_date} {account_url} {form_url} {contact} {reason}
 * ({reason} is replaced by a "Motif :" paragraph, or by nothing).
 *
 * @param string $kind 'accepted' or 'refused'
 * @param string $lang 'fr' or 'en'
 * @return array { subject, heading, body }
 */
function on_gratuite_decision_texts( $kind, $lang ) {
    return on_gratuite_in_lang( $lang, static function () use ( $kind ) {
        if ( 'accepted' === $kind ) {
            return array(
                'subject' => __( 'Orgues Nouvelles : votre abonnement gratuit est accepté', 'orgues-nouvelles' ),
                'heading' => __( 'Votre demande est acceptée', 'orgues-nouvelles' ),
                'body'    => __( 'Bonne nouvelle : votre demande d\'abonnement numérique gratuit pour les jeunes jusqu\'à 26 ans est acceptée.', 'orgues-nouvelles' ) . "\n"
                    . __( 'Votre abonnement est actif jusqu\'au {end_date}. Vous pouvez lire les numéros en ligne depuis votre compte :', 'orgues-nouvelles' ) . "\n\n"
                    . '{account_url}' . "\n\n"
                    . __( 'Pour conserver la gratuité l\'année suivante, il suffira de refaire une demande avec un nouveau justificatif.', 'orgues-nouvelles' ) . "\n"
                    . __( 'Le document que vous avez envoyé a été supprimé de notre site.', 'orgues-nouvelles' ) . "\n\n"
                    . __( 'Bonne lecture !', 'orgues-nouvelles' ),
            );
        }
        return array(
            'subject' => __( 'Orgues Nouvelles : votre demande d\'abonnement gratuit', 'orgues-nouvelles' ),
            'heading' => __( 'Votre demande n\'a pas pu être acceptée', 'orgues-nouvelles' ),
            'body'    => __( 'Nous n\'avons pas pu accepter votre demande d\'abonnement numérique gratuit pour les jeunes jusqu\'à 26 ans.', 'orgues-nouvelles' ) . '{reason}' . "\n"
                . __( 'Le document que vous avez envoyé a été supprimé de notre site. Vous pouvez déposer une nouvelle demande dès maintenant, avec un justificatif valide :', 'orgues-nouvelles' ) . "\n\n"
                . '{form_url}' . "\n\n"
                . __( 'Pour toute question, répondez simplement à ce message ou écrivez à {contact}.', 'orgues-nouvelles' ),
        );
    } );
}

/** Fill the placeholders. $vars: first_name, end_date, account_url, form_url, contact, reason (free text, may be empty). */
function on_gratuite_fill_decision_text( $text, array $vars, $lang = 'fr' ) {
    $reason = trim( (string) ( $vars['reason'] ?? '' ) );
    $label  = on_gratuite_in_lang( $lang, static function () {
        return __( 'Motif : ', 'orgues-nouvelles' );
    } );
    return strtr( (string) $text, array(
        '{first_name}'  => (string) ( $vars['first_name'] ?? '' ),
        '{end_date}'    => (string) ( $vars['end_date'] ?? '' ),
        '{account_url}' => (string) ( $vars['account_url'] ?? '' ),
        '{form_url}'    => (string) ( $vars['form_url'] ?? '' ),
        '{contact}'     => (string) ( $vars['contact'] ?? '' ),
        '{reason}'      => '' === $reason ? '' : "\n" . $label . $reason,
    ) );
}

/**
 * Texts of the copy sent to the person who made the request on behalf of someone else.
 * Short on purpose (decision of 9 November 2026): the decision, who it is about, the end date (acceptance)
 * or the reason (refusal). Nothing about reading online, "My account" or the next renewal: it is not their subscription.
 * Placeholders: {name} {email} {end_date} {reason}
 *
 * @param string $kind 'accepted' or 'refused'
 * @param string $lang 'fr' or 'en'
 * @return array { subject, heading, body }
 */
function on_gratuite_copy_texts( $kind, $lang ) {
    return on_gratuite_in_lang( $lang, static function () use ( $kind ) {
        if ( 'accepted' === $kind ) {
            return array(
                'subject' => __( 'Orgues Nouvelles : abonnement gratuit accepté pour {name}', 'orgues-nouvelles' ),
                'heading' => __( 'La demande est acceptée', 'orgues-nouvelles' ),
                'body'    => __( 'Pour information : la demande d\'abonnement numérique gratuit que vous avez faite pour {name} ({email}) est acceptée.', 'orgues-nouvelles' ) . "\n"
                    . __( 'L\'abonnement est actif jusqu\'au {end_date}.', 'orgues-nouvelles' ),
            );
        }
        return array(
            'subject' => __( 'Orgues Nouvelles : demande d\'abonnement gratuit pour {name}', 'orgues-nouvelles' ),
            'heading' => __( 'La demande n\'a pas pu être acceptée', 'orgues-nouvelles' ),
            'body'    => __( 'Pour information : la demande d\'abonnement numérique gratuit que vous avez faite pour {name} ({email}) n\'a pas pu être acceptée.', 'orgues-nouvelles' ) . '{reason}',
        );
    } );
}

/** Fill the placeholders of a copy text. $vars: name, email, end_date, reason (free text, may be empty). */
function on_gratuite_fill_copy_text( $text, array $vars, $lang = 'fr' ) {
    $reason = trim( (string) ( $vars['reason'] ?? '' ) );
    $label  = on_gratuite_in_lang( $lang, static function () {
        return __( 'Motif : ', 'orgues-nouvelles' );
    } );
    return strtr( (string) $text, array(
        '{name}'     => (string) ( $vars['name'] ?? '' ),
        '{email}'    => (string) ( $vars['email'] ?? '' ),
        '{end_date}' => (string) ( $vars['end_date'] ?? '' ),
        '{reason}'   => '' === $reason ? '' : "\n" . $label . $reason,
    ) );
}

/** Whether a decision lock taken at $started is abandoned at $now (a request that died half-way). */
function on_gratuite_lock_is_stale( $started, $now, $ttl = 600 ) {
    return (int) $started > 0 && ( (int) $now - (int) $started ) > (int) $ttl;
}
