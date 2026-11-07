<?php
/**
 * Cycle de vie des abonnements.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('on_get_subscription_renewal_info')) {
    function on_get_subscription_renewal_info($subscription)
    {
        $paid_renewals = 0;
        $pending_renewals = 0;
        $issues_per_renewal = 0;

        if (!$subscription instanceof WC_Subscription) {
            return array(
                'paid_renewals' => $paid_renewals,
                'pending_renewals' => $pending_renewals,
                'issues_per_renewal' => $issues_per_renewal,
            );
        }

        $issues_per_renewal = max(0, (int) on_get_subscription_issue_count($subscription));

        $related_orders = $subscription->get_related_orders('renewal');
        if (is_array($related_orders)) {
            foreach ($related_orders as $related_order) {
                $order = $related_order instanceof WC_Order ? $related_order : wc_get_order($related_order);
                if (!$order || !is_a($order, 'WC_Order')) {
                    continue;
                }

                if ($order->is_paid()) {
                    $paid_renewals++;
                } elseif ($order->has_status('pending')) {
                    $pending_renewals++;
                }
            }
        }

        return array(
            'paid_renewals' => $paid_renewals,
            'pending_renewals' => $pending_renewals,
            'issues_per_renewal' => $issues_per_renewal,
        );
    }
}

if (!function_exists('on_initialize_subscription_number_bounds')) {
    function on_initialize_subscription_number_bounds($subscription)
    {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        $start_date = $subscription->get_date('start');
        if (empty($start_date)) {
            return;
        }

        $has_changes = false;

        $existing_start = $subscription->get_meta('number-start', true);
        $existing_end   = $subscription->get_meta('number-end', true);
        if ($existing_start === '' || $existing_start === null || $existing_end === '' || $existing_end === null) {
            $info         = on_get_subscription_info($start_date, $start_date);
            $numero_start = isset($info['numero_debut']) ? (int) $info['numero_debut'] : null;

            if ($numero_start !== null) {
                $issue_count = max(1, (int) on_get_subscription_issue_count($subscription));
                $numero_end  = $numero_start + max(0, $issue_count - 1);

                $subscription->update_meta_data('number-start', $numero_start);
                $subscription->update_meta_data('number-end', $numero_end);
                $has_changes = true;
            }
        }

        $existing_formule = $subscription->get_meta('on_formule', true);
        if ($existing_formule === '' || $existing_formule === null) {
            $formule = on_guess_subscription_formule_from_items($subscription);
            if ('' !== $formule) {
                $subscription->update_meta_data('on_formule', $formule);
                $has_changes = true;
            }
        }

        if ($has_changes) {
            $subscription->save();
        }
    }

    add_action('woocommerce_checkout_subscription_created', 'on_initialize_subscription_number_bounds', 20, 1);
    add_action('woocommerce_admin_created_subscription', 'on_initialize_subscription_number_bounds', 20, 1);
    add_action('woocommerce_subscription_payment_complete', 'on_initialize_subscription_number_bounds', 20, 1);
}

if (!function_exists('on_get_subscription_billing_schedule_from_items')) {
    /**
     * Returns the billing schedule declared by the first subscription product item.
     *
     * @param object $subscription Subscription object.
     * @return array{billing_period:string,billing_interval:int}
     */
    function on_get_subscription_billing_schedule_from_items($subscription)
    {
        if (!is_object($subscription) || !is_callable(array($subscription, 'get_items'))) {
            return array();
        }

        foreach ($subscription->get_items() as $item) {
            if (!is_object($item) || !is_callable(array($item, 'get_product'))) {
                continue;
            }

            $product = $item->get_product();
            if (!$product) {
                continue;
            }

            $schedule = apply_filters(
                'on_subscription_billing_schedule_from_product',
                null,
                $product,
                $subscription,
                $item
            );

            if (!is_array($schedule)) {
                $schedule = array(
                    'billing_period'   => '',
                    'billing_interval' => 0,
                );

                if (class_exists('WC_Subscriptions_Product') && is_a($product, 'WC_Product')) {
                    $schedule['billing_period'] = (string) WC_Subscriptions_Product::get_period($product);
                    $schedule['billing_interval'] = (int) WC_Subscriptions_Product::get_interval($product);
                }
            }

            $schedule['billing_period'] = isset($schedule['billing_period']) ? strtolower(sanitize_key((string) $schedule['billing_period'])) : '';
            $schedule['billing_interval'] = isset($schedule['billing_interval']) ? max(0, (int) $schedule['billing_interval']) : 0;

            if ('' !== $schedule['billing_period'] && $schedule['billing_interval'] > 0) {
                return $schedule;
            }
        }

        return array();
    }
}

if (!function_exists('on_calculate_subscription_next_payment_after_billing_change')) {
    /**
     * Computes the next payment date after a billing schedule change.
     *
     * @param object $subscription Subscription object.
     * @return string MySQL date string or empty string.
     */
    function on_calculate_subscription_next_payment_after_billing_change($subscription)
    {
        if (!is_object($subscription) || !is_callable(array($subscription, 'get_time'))) {
            return '';
        }

        $payment_count = is_callable(array($subscription, 'get_payment_count')) ? (int) $subscription->get_payment_count() : 0;

        if ($payment_count > 0 && is_callable(array($subscription, 'calculate_date'))) {
            $calculated_next_payment = $subscription->calculate_date('next_payment');
            if (!empty($calculated_next_payment)) {
                return $calculated_next_payment;
            }
        }

        $trial_end_time = (int) $subscription->get_time('trial_end');
        if ($trial_end_time > current_time('timestamp', true)) {
            return gmdate('Y-m-d H:i:s', $trial_end_time);
        }

        if (!is_callable(array($subscription, 'get_billing_interval')) || !is_callable(array($subscription, 'get_billing_period'))) {
            return '';
        }

        $start_time = (int) $subscription->get_time('start');
        $billing_interval = max(0, (int) $subscription->get_billing_interval());
        $billing_period = strtolower(sanitize_key((string) $subscription->get_billing_period()));

        if ($start_time <= 0 || $billing_interval <= 0 || '' === $billing_period) {
            return '';
        }

        $next_payment_timestamp = wcs_add_time($billing_interval, $billing_period, $start_time, 'offset_site_time');

        if ($next_payment_timestamp <= 0) {
            return '';
        }

        return gmdate('Y-m-d H:i:s', $next_payment_timestamp);
    }
}

if (!function_exists('on_sync_subscription_billing_schedule_from_items')) {
    /**
     * Syncs the subscription billing schedule from the first subscription product item.
     *
     * @param object $subscription Subscription object.
     * @return bool True when the schedule was updated.
     */
    function on_sync_subscription_billing_schedule_from_items($subscription)
    {
        if (!is_object($subscription) || !is_callable(array($subscription, 'get_items'))) {
            return false;
        }

        $schedule = on_get_subscription_billing_schedule_from_items($subscription);
        if (empty($schedule)) {
            return false;
        }

        $current_billing_period = is_callable(array($subscription, 'get_billing_period')) ? strtolower(sanitize_key((string) $subscription->get_billing_period())) : '';
        $current_billing_interval = is_callable(array($subscription, 'get_billing_interval')) ? (int) $subscription->get_billing_interval() : 0;

        if ($current_billing_period === $schedule['billing_period'] && $current_billing_interval === (int) $schedule['billing_interval']) {
            return false;
        }

        if (is_callable(array($subscription, 'set_billing_period'))) {
            $subscription->set_billing_period($schedule['billing_period']);
        }

        if (is_callable(array($subscription, 'set_billing_interval'))) {
            $subscription->set_billing_interval((int) $schedule['billing_interval']);
        }

        $next_payment_date = on_calculate_subscription_next_payment_after_billing_change($subscription);
        if ('' !== $next_payment_date && is_callable(array($subscription, 'update_dates'))) {
            $subscription->update_dates(
                array(
                    'next_payment' => $next_payment_date,
                ),
                'gmt'
            );
        }

        if (is_callable(array($subscription, 'save'))) {
            $subscription->save();
        }

        return true;
    }
}

if (!function_exists('on_extend_subscription_number_bounds_on_renewal')) {
    function on_extend_subscription_number_bounds_on_renewal($subscription)
    {
        if (!$subscription instanceof \WC_Subscription) {
            return;
        }

        $issue_count = (int) on_get_subscription_issue_count($subscription);
        if ($issue_count <= 0) {
            return;
        }

        $current_end = $subscription->get_meta('number-end', true);
        if ($current_end === '' || $current_end === null) {
            on_initialize_subscription_number_bounds($subscription);
            $current_end = $subscription->get_meta('number-end', true);
            if ($current_end === '' || $current_end === null) {
                return;
            }
        }

        $new_end = max(0, (int) $current_end) + $issue_count;
        $subscription->update_meta_data('number-end', $new_end);

        $subscription->add_order_note(
            sprintf(
                /* translators: 1: previous end number, 2: new end number, 3: issue count added. */
                __('Numéro de fin d\'abonnement étendu de ON-%1$d à ON-%2$d (+%3$d numéro(s)).', 'orgues-nouvelles'),
                (int) $current_end,
                (int) $new_end,
                (int) $issue_count
            )
        );

        $subscription->save();
    }

    add_action('woocommerce_checkout_subscription_created', 'on_sync_subscription_billing_schedule_from_items', 20, 1);
    add_action('woocommerce_admin_created_subscription', 'on_sync_subscription_billing_schedule_from_items', 20, 1);
    add_action('woocommerce_subscription_payment_complete', 'on_sync_subscription_billing_schedule_from_items', 20, 1);
    add_action('woocommerce_subscription_renewal_payment_complete', 'on_extend_subscription_number_bounds_on_renewal', 20, 2);
}

if (!function_exists('on_exclude_custom_meta_from_renewal_order_copy')) {
    /**
     * Empêche la copie de meta personnalisées abonnement vers les commandes de renouvellement.
     *
     * @param array $order_meta Liste des meta à copier.
     * @return array
     */
    function on_exclude_custom_meta_from_renewal_order_copy($order_meta)
    {
        if (!is_array($order_meta)) {
            return $order_meta;
        }

        $excluded_keys = array(
            'numero-start',
            'numero-end',
            'formule',
            'number-start',
            'number-end',
            'on_formule',
        );

        foreach ($order_meta as $index => $meta_item) {
            if (!is_array($meta_item)) {
                continue;
            }

            $meta_key = isset($meta_item['meta_key']) ? (string) $meta_item['meta_key'] : '';
            if ('' !== $meta_key && (in_array($meta_key, $excluded_keys, true) || 0 === strpos($meta_key, '_on_student_proof_'))) {
                unset($order_meta[$index]);
            }
        }

        return array_values($order_meta);
    }

    add_filter('wcs_renewal_order_meta', 'on_exclude_custom_meta_from_renewal_order_copy', 20, 1);
}

if (!function_exists('on_allow_admin_status_change_from_expired_subscription')) {
    /**
     * Autorise des transitions de statut manuelles en admin depuis "expired".
     *
     * @param bool            $can_be_updated Résultat courant de la validation.
     * @param WC_Subscription $subscription   Abonnement concerné.
     * @return bool
     */
    function on_allow_admin_status_change_from_expired_subscription($can_be_updated, $subscription)
    {
        if (true === $can_be_updated) {
            return true;
        }

        if (!is_admin() || !$subscription instanceof WC_Subscription) {
            return $can_be_updated;
        }

        if (!$subscription->has_status('expired')) {
            return $can_be_updated;
        }

        $post_type_object = get_post_type_object($subscription->get_type());
        if (!$post_type_object || !isset($post_type_object->cap->edit_post)) {
            return $can_be_updated;
        }

        if (!current_user_can($post_type_object->cap->edit_post, $subscription->get_id())) {
            return $can_be_updated;
        }

        return true;
    }

    add_filter('woocommerce_can_subscription_be_updated_to_active', 'on_allow_admin_status_change_from_expired_subscription', 20, 2);
    add_filter('woocommerce_can_subscription_be_updated_to_on-hold', 'on_allow_admin_status_change_from_expired_subscription', 20, 2);
}