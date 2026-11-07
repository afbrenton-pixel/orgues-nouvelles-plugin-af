<?php
/**
 * Enregistre l'intégration AcyMailing "WCS Subscription Triggers" auprès d'AcyMailing.
 *
 * Le hook `acym_load_installed_integrations` est déclenché par AcyMailing lors
 * du chargement de ses plugins tiers. Notre classe plgAcymOnwcssubscriptions
 * sera alors instanciée et ses méthodes (déclaration de triggers, hooks WP…)
 * seront appelées automatiquement.
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action(
    'acym_load_installed_integrations',
    'on_register_acymailing_wcs_subscription_triggers',
    10,
    2
);

function on_register_acymailing_wcs_subscription_triggers(array &$integrations, string $acyVersion): void
{
    // Requiert AcyMailing >= 10.4.0 (cohérent avec l'intégration WooCommerce officielle).
    if (!version_compare($acyVersion, '10.4.0', '>=')) {
        return;
    }

    $integrations[] = [
        'path'      => __DIR__ . '/on-wcs-subscription-triggers',
        'className' => 'plgAcymOnwcssubscriptions',
    ];
}
