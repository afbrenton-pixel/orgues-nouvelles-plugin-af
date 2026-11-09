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
    $en = 'en' === $lang;
    if ( 'accepted' === $kind ) {
        return $en ? array(
            'subject' => 'Orgues Nouvelles: your free subscription is accepted',
            'heading' => 'Your request is accepted',
            'body'    => 'Good news: your request for the free digital subscription for people aged 26 or under is accepted.' . "\n"
                . 'Your subscription is active until {end_date}. You can read the issues online from your account:' . "\n\n"
                . '{account_url}' . "\n\n"
                . 'To keep it free the following year, simply send a new request with a new proof.' . "\n"
                . 'The document you sent has been deleted from our site.' . "\n\n"
                . 'Happy reading!',
        ) : array(
            'subject' => 'Orgues Nouvelles : votre abonnement gratuit est accepté',
            'heading' => 'Votre demande est acceptée',
            'body'    => 'Bonne nouvelle : votre demande d\'abonnement numérique gratuit pour les jeunes jusqu\'à 26 ans est acceptée.' . "\n"
                . 'Votre abonnement est actif jusqu\'au {end_date}. Vous pouvez lire les numéros en ligne depuis votre compte :' . "\n\n"
                . '{account_url}' . "\n\n"
                . 'Pour conserver la gratuité l\'année suivante, il suffira de refaire une demande avec un nouveau justificatif.' . "\n"
                . 'Le document que vous avez envoyé a été supprimé de notre site.' . "\n\n"
                . 'Bonne lecture !',
        );
    }
    return $en ? array(
        'subject' => 'Orgues Nouvelles: your free subscription request',
        'heading' => 'We could not accept your request',
        'body'    => 'We were unable to accept your request for the free digital subscription for people aged 26 or under.{reason}' . "\n"
            . 'The document you sent has been deleted from our site. You can send a new request right away, with a valid proof:' . "\n\n"
            . '{form_url}' . "\n\n"
            . 'If you have any question, just reply to this message or write to {contact}.',
    ) : array(
        'subject' => 'Orgues Nouvelles : votre demande d\'abonnement gratuit',
        'heading' => 'Votre demande n\'a pas pu être acceptée',
        'body'    => 'Nous n\'avons pas pu accepter votre demande d\'abonnement numérique gratuit pour les jeunes jusqu\'à 26 ans.{reason}' . "\n"
            . 'Le document que vous avez envoyé a été supprimé de notre site. Vous pouvez déposer une nouvelle demande dès maintenant, avec un justificatif valide :' . "\n\n"
            . '{form_url}' . "\n\n"
            . 'Pour toute question, répondez simplement à ce message ou écrivez à {contact}.',
    );
}

/** Fill the placeholders. $vars: first_name, end_date, account_url, form_url, contact, reason (free text, may be empty). */
function on_gratuite_fill_decision_text( $text, array $vars, $lang = 'fr' ) {
    $reason = trim( (string) ( $vars['reason'] ?? '' ) );
    $label  = 'en' === $lang ? 'Reason: ' : 'Motif : ';
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
    $en = 'en' === $lang;
    if ( 'accepted' === $kind ) {
        return $en ? array(
            'subject' => 'Orgues Nouvelles: free subscription accepted for {name}',
            'heading' => 'The request is accepted',
            'body'    => 'For your information: the request for the free digital subscription that you made for {name} ({email}) is accepted.' . "\n"
                . 'The subscription is active until {end_date}.',
        ) : array(
            'subject' => 'Orgues Nouvelles : abonnement gratuit accepté pour {name}',
            'heading' => 'La demande est acceptée',
            'body'    => 'Pour information : la demande d\'abonnement numérique gratuit que vous avez faite pour {name} ({email}) est acceptée.' . "\n"
                . 'L\'abonnement est actif jusqu\'au {end_date}.',
        );
    }
    return $en ? array(
        'subject' => 'Orgues Nouvelles: free subscription request for {name}',
        'heading' => 'The request could not be accepted',
        'body'    => 'For your information: the request for the free digital subscription that you made for {name} ({email}) could not be accepted.{reason}',
    ) : array(
        'subject' => 'Orgues Nouvelles : demande d\'abonnement gratuit pour {name}',
        'heading' => 'La demande n\'a pas pu être acceptée',
        'body'    => 'Pour information : la demande d\'abonnement numérique gratuit que vous avez faite pour {name} ({email}) n\'a pas pu être acceptée.{reason}',
    );
}

/** Fill the placeholders of a copy text. $vars: name, email, end_date, reason (free text, may be empty). */
function on_gratuite_fill_copy_text( $text, array $vars, $lang = 'fr' ) {
    $reason = trim( (string) ( $vars['reason'] ?? '' ) );
    $label  = 'en' === $lang ? 'Reason: ' : 'Motif : ';
    return strtr( (string) $text, array(
        '{name}'     => (string) ( $vars['name'] ?? '' ),
        '{email}'    => (string) ( $vars['email'] ?? '' ),
        '{end_date}' => (string) ( $vars['end_date'] ?? '' ),
        '{reason}'   => '' === $reason ? '' : "\n" . $label . $reason,
    ) );
}
