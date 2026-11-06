<?php
/**
 * Intégration AcyMailing : déclencheurs et variables des abonnements WooCommerce Subscriptions.
 *
 * Expose :
 *   - Un déclencheur "wcs_subscription_status_change" pour les Automations/Scénarios.
 *   - Des variables email {onwcssubscriptions:PLAN_champ} pour chaque plan (ON, ONED, ONEDA).
 */

if (!defined('ABSPATH')) {
    exit;
}

use AcyMailing\Classes\AutomationClass;
use AcyMailing\Classes\UserClass;
use AcyMailing\Core\AcymPlugin;
use AcyMailing\Helpers\ScenarioHelper;

class plgAcymOnwcssubscriptions extends AcymPlugin
{
    const TRIGGER_KEY = 'wcs_subscription_status_change';

    /** Plans disponibles et leurs labels. */
    private const PLANS = [
        'ON'    => 'Magazine papier (ON)',
        'ONED'  => 'Magazine numérique (ONED)',
        'ONEDA' => 'English Digital (ONEDA)',
    ];

    /** Champs disponibles : clé interne => label affiché. */
    private const FIELDS = [
        'number_start' => 'Numéro de début',
        'number_end'   => 'Numéro de fin',
        'start_date'   => 'Date de début',
        'end_date'     => 'Date de fin',
        'next_renewal' => 'Prochain renouvellement',
        'status'       => 'Statut',
    ];

    /** Ordre de priorité pour choisir parmi plusieurs abonnements du même plan. */
    private const STATUS_PRIORITY = [
        'active'         => 0,
        'on-hold'        => 1,
        'pending'        => 2,
        'pending-cancel' => 3,
        'expired'        => 4,
        'cancelled'      => 5,
    ];

    public function __construct()
    {
        parent::__construct();

        $this->cms       = 'WordPress';
        $this->installed = acym_isExtensionActive('woocommerce-subscriptions/woocommerce-subscriptions.php');

        $this->pluginDescription->name        = 'Abonnements ON (WCS)';
        $this->pluginDescription->category    = 'E-commerce';
        $this->pluginDescription->description = 'Variables et déclencheurs pour les abonnements WooCommerce Subscriptions (ON / ONED / ONEDA).';
    }

    // -----------------------------------------------------------------------
    // Variables email (dynamicText / textPopup / replaceUserInformation)
    // -----------------------------------------------------------------------

    public function dynamicText(?int $mailId): ?object
    {
        return $this->pluginDescription;
    }

    public function textPopup(): void
    {
        ?>
        <script type="text/javascript">
            function changeOnSubTag(tagname, element) {
                if (!tagname) return;
                setTag('{<?php echo esc_js($this->name); ?>:' + tagname + '}', element);
            }
        </script>
        <div class="acym__popup__listing text-center grid-x">
            <?php foreach (self::PLANS as $planKey => $planLabel) : ?>
            <h1 class="acym__title acym__title__secondary text-center cell">
                <?php echo esc_html($planLabel); ?>
            </h1>
            <?php foreach (self::FIELDS as $fieldKey => $fieldLabel) :
                // Pour les champs de date, on suggère le type:date pour faciliter le formatage.
                $isDate = in_array($fieldKey, ['start_date', 'end_date', 'next_renewal'], true);
                $tagId  = $planKey . '_' . $fieldKey . ($isDate ? '|type:date' : '');
            ?>
            <div style="cursor:pointer"
                 class="grid-x medium-12 cell acym__row__no-listing acym__listing__row__popup text-left"
                 onclick="changeOnSubTag('<?php echo esc_js($tagId); ?>', jQuery(this));">
                <div class="cell medium-6 small-12 acym__listing__title acym__listing__title__dynamics">
                    <?php echo esc_html($fieldLabel); ?>
                </div>
                <div class="cell medium-6 small-12 acym__listing__title acym__listing__title__dynamics acym__color__grey">
                    <code>{<?php echo esc_html($this->name . ':' . $planKey . '_' . $fieldKey); ?>}</code>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    public function replaceUserInformation(object &$email, ?object &$user, bool $send = true): void
    {
        $extractedTags = $this->pluginHelper->extractTags($email, $this->name);
        if (empty($extractedTags)) {
            return;
        }

        $wpUserId = !empty($user->cms_id) ? intval($user->cms_id) : 0;

        // Construire l'index $byPlan uniquement si nécessaire.
        $byPlan = $wpUserId > 0 ? $this->on_wcs_get_subscriptions_by_plan($wpUserId) : [];

        $tags = [];
        foreach ($extractedTags as $tagCode => $tagObj) {
            // L'id de la balise est au format "PLAN_field" (ex: ON_number_start).
            $tagId  = $tagObj->id ?? '';
            $planKey = null;
            $fieldKey = null;

            foreach (array_keys(self::PLANS) as $plan) {
                $prefix = $plan . '_';
                if (strncmp($tagId, $prefix, strlen($prefix)) === 0) {
                    $planKey  = $plan;
                    $fieldKey = substr($tagId, strlen($prefix));
                    break;
                }
            }

            if ($planKey === null || !array_key_exists($fieldKey, self::FIELDS)) {
                $tags[$tagCode] = $tagObj->default ?? '';
                continue;
            }

            $subscription = $byPlan[$planKey] ?? null;
            if ($subscription === null) {
                $tags[$tagCode] = $tagObj->default ?? '';
                continue;
            }

            $value = $this->on_wcs_get_subscription_field_value($subscription, $fieldKey);
            $this->pluginHelper->formatString($value, $tagObj);
            $tags[$tagCode] = $value;
        }

        $this->pluginHelper->replaceTags($email, $tags);
    }

    // -----------------------------------------------------------------------
    // Helpers privés – données d'abonnement
    // -----------------------------------------------------------------------

    /**
     * Retourne un tableau indexé par plan (ON/ONED/ONEDA) contenant
     * l'abonnement WCS le plus prioritaire pour chaque plan.
     *
     * @param int $wpUserId
     * @return array<string, WC_Subscription>
     */
    private function on_wcs_get_subscriptions_by_plan(int $wpUserId): array
    {
        if (!function_exists('wcs_get_subscriptions')) {
            return [];
        }

        $subscriptions = wcs_get_subscriptions([
            'customer_id'         => $wpUserId,
            'subscription_status' => 'all',
            'subscriptions_per_page' => -1,
        ]);

        $byPlan = [];

        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof \WC_Subscription) {
                continue;
            }

            // Détecter la formule depuis la meta, sinon depuis les produits.
            $plan = '';
            if (function_exists('on_sanitize_subscription_formule')) {
                $plan = on_sanitize_subscription_formule($subscription->get_meta('on_formule', true));
            }
            if ('' === $plan && function_exists('on_guess_subscription_formule_from_items')) {
                $plan = on_guess_subscription_formule_from_items($subscription);
            }

            if ('' === $plan || !array_key_exists($plan, self::PLANS)) {
                continue;
            }

            // Conserver uniquement l'abonnement avec la meilleure priorité de statut.
            if (!isset($byPlan[$plan])) {
                $byPlan[$plan] = $subscription;
                continue;
            }

            $currentPriority  = self::STATUS_PRIORITY[$byPlan[$plan]->get_status()] ?? 99;
            $candidatePriority = self::STATUS_PRIORITY[$subscription->get_status()] ?? 99;

            if ($candidatePriority < $currentPriority) {
                $byPlan[$plan] = $subscription;
            } elseif ($candidatePriority === $currentPriority && $subscription->get_id() > $byPlan[$plan]->get_id()) {
                // Même priorité : garder le plus récent.
                $byPlan[$plan] = $subscription;
            }
        }

        return $byPlan;
    }

    /**
     * Extrait la valeur d'un champ d'un abonnement WCS.
     *
     * @param \WC_Subscription $subscription
     * @param string           $field  Clé interne (number_start, number_end, start_date, end_date, next_renewal, status)
     * @return string
     */
    private function on_wcs_get_subscription_field_value(\WC_Subscription $subscription, string $field): string
    {
        switch ($field) {
            case 'number_start':
                return (string) $subscription->get_meta('number-start', true);

            case 'number_end':
                return (string) $subscription->get_meta('number-end', true);

            case 'start_date':
                return (string) $subscription->get_date('start');

            case 'end_date':
                return (string) $subscription->get_date('end');

            case 'next_renewal':
                return (string) $subscription->get_date('next_payment');

            case 'status':
                $statusSlug = $subscription->get_status();
                if (function_exists('wcs_get_subscription_statuses')) {
                    $statuses = wcs_get_subscription_statuses();
                    $key      = 'wc-' . $statusSlug;
                    if (isset($statuses[$key])) {
                        return (string) $statuses[$key];
                    }
                }
                return $statusSlug;

            default:
                return '';
        }
    }

    // -----------------------------------------------------------------------
    // Statuts WooCommerce Subscriptions
    // -----------------------------------------------------------------------

    private function on_get_subscription_statuses(bool $addAny = false): array
    {
        $statuses = [];

        if ($addAny) {
            $statuses['0'] = acym_translation('ACYM_ANY');
        }

        if (function_exists('wcs_get_subscription_statuses')) {
            foreach (wcs_get_subscription_statuses() as $key => $label) {
                // Les clés WCS sont déjà préfixées "wc-" ; on les conserve telles quelles.
                $statuses[$key] = $label;
            }
        } else {
            // Fallback minimal si WCS n'est pas encore chargé.
            $statuses['wc-active']         = __('Active', 'woocommerce-subscriptions');
            $statuses['wc-on-hold']        = __('On hold', 'woocommerce-subscriptions');
            $statuses['wc-cancelled']      = __('Cancelled', 'woocommerce-subscriptions');
            $statuses['wc-expired']        = __('Expired', 'woocommerce-subscriptions');
            $statuses['wc-pending-cancel'] = __('Pending Cancellation', 'woocommerce-subscriptions');
            $statuses['wc-pending']        = __('Pending', 'woocommerce-subscriptions');
        }

        return $statuses;
    }

    // -----------------------------------------------------------------------
    // Déclaration des déclencheurs (Automations & Scénarios)
    // -----------------------------------------------------------------------

    public function onAcymDeclareTriggers(&$triggers, &$defaultValues): void
    {
        $statuses = $this->on_get_subscription_statuses(true);
        $plans    = array_merge(['0' => acym_translation('ACYM_ANY')], self::PLANS);

        $from = empty($defaultValues[self::TRIGGER_KEY]['from']) ? '0' : $defaultValues[self::TRIGGER_KEY]['from'];
        $to   = empty($defaultValues[self::TRIGGER_KEY]['to'])   ? 'wc-expired' : $defaultValues[self::TRIGGER_KEY]['to'];
        $plan = empty($defaultValues[self::TRIGGER_KEY]['plan']) ? '0' : $defaultValues[self::TRIGGER_KEY]['plan'];

        $triggers['user'][self::TRIGGER_KEY]         = new stdClass();
        $triggers['user'][self::TRIGGER_KEY]->name   = __('Changement de statut d\'abonnement WooCommerce', 'orgues-nouvelles');
        $triggers['user'][self::TRIGGER_KEY]->option  = '<div class="grid-x grid-margin-x" style="min-height: 40px; flex-wrap: wrap;">';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-shrink acym_vcenter">' . acym_translation('ACYM_FROM') . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-3">' . acym_select(
            $statuses,
            '[triggers][user][' . self::TRIGGER_KEY . '][from]',
            $from,
            ['data-class' => 'acym__select']
        ) . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-shrink acym_vcenter">' . acym_translation('ACYM_TO') . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-3">' . acym_select(
            $statuses,
            '[triggers][user][' . self::TRIGGER_KEY . '][to]',
            $to,
            ['data-class' => 'acym__select']
        ) . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-shrink acym_vcenter">' . __('Formule', 'orgues-nouvelles') . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '<div class="cell medium-3">' . acym_select(
            $plans,
            '[triggers][user][' . self::TRIGGER_KEY . '][plan]',
            $plan,
            ['data-class' => 'acym__select']
        ) . '</div>';
        $triggers['user'][self::TRIGGER_KEY]->option .= '</div>';
    }

    public function onAcymDeclareTriggersScenario(&$triggers, &$defaultValues): void
    {
        $this->onAcymDeclareTriggers($triggers, $defaultValues);
    }

    // -----------------------------------------------------------------------
    // Exécution du déclencheur (correspondance lors du traitement AcyMailing)
    // -----------------------------------------------------------------------

    public function onAcymExecuteTrigger(&$step, &$execute, &$data): void
    {
        $data     = is_array($data) ? $data : [];
        $triggers = $step->triggers;

        if (empty($triggers[self::TRIGGER_KEY]) || !is_array($triggers[self::TRIGGER_KEY])) {
            return;
        }

        $fromStatus  = 'wc-' . (isset($data['statusFrom']) ? $data['statusFrom'] : '');
        $toStatus    = 'wc-' . (isset($data['statusTo'])   ? $data['statusTo']   : '');
        $dataPlan    = isset($data['plan']) ? (string) $data['plan'] : '';

        $configFrom = $triggers[self::TRIGGER_KEY]['from'] ?? '0';
        $configTo   = $triggers[self::TRIGGER_KEY]['to']   ?? '0';
        $configPlan = $triggers[self::TRIGGER_KEY]['plan'] ?? '0';

        $fromMatch = ($configFrom === '0' || $fromStatus === $configFrom);
        $toMatch   = ($configTo   === '0' || $toStatus   === $configTo);
        $planMatch = ($configPlan === '0' || $dataPlan === $configPlan);

        if ($fromMatch && $toMatch && $planMatch) {
            $execute = true;
        }
    }

    // -----------------------------------------------------------------------
    // Résumé affiché dans l'interface AcyMailing
    // -----------------------------------------------------------------------

    public function onAcymDeclareSummary_triggers(object $automation): void
    {
        if (empty($automation->triggers[self::TRIGGER_KEY]['from'])) {
            return;
        }

        $statuses = $this->on_get_subscription_statuses(true);

        $fromLabel = $statuses[$automation->triggers[self::TRIGGER_KEY]['from']] ?? $automation->triggers[self::TRIGGER_KEY]['from'];
        $toLabel   = $statuses[$automation->triggers[self::TRIGGER_KEY]['to']]   ?? $automation->triggers[self::TRIGGER_KEY]['to'];

        $planKey   = $automation->triggers[self::TRIGGER_KEY]['plan'] ?? '0';
        $planLabel = ($planKey !== '0' && isset(self::PLANS[$planKey]))
            ? ' (' . self::PLANS[$planKey] . ')'
            : '';

        $automation->triggers[self::TRIGGER_KEY] = sprintf(
            __('Abonnement%s passe de « %s » à « %s »', 'orgues-nouvelles'),
            $planLabel,
            $fromLabel,
            $toLabel
        );
    }

    // -----------------------------------------------------------------------
    // Enregistrement des hooks WordPress
    // -----------------------------------------------------------------------

    public function onAcymInitWordpressAddons(): void
    {
        if (!$this->installed) {
            return;
        }

        // woocommerce_subscription_status_changed( $subscription_id, $old_status, $new_status, $subscription )
        add_action(
            'woocommerce_subscription_status_changed',
            [$this, 'on_subscription_status_changed'],
            20,
            4
        );
    }

    // -----------------------------------------------------------------------
    // Callback : changement de statut d'abonnement
    // -----------------------------------------------------------------------

    public function on_subscription_status_changed(int $subscription_id, string $old_status, string $new_status, $subscription): void
    {
        $userClass = new UserClass();
        $acyUser   = null;

        $wpUserId = method_exists($subscription, 'get_user_id') ? $subscription->get_user_id() : 0;

        if (!empty($wpUserId)) {
            $acyUser = $userClass->getOneByCMSId($wpUserId);
        }

        if (empty($acyUser)) {
            $billingEmail = method_exists($subscription, 'get_billing_email') ? $subscription->get_billing_email() : '';
            if (!empty($billingEmail)) {
                $acyUser = $userClass->getOneByEmail($billingEmail);
            }
        }

        if (empty($acyUser)) {
            return;
        }

        // Détecter la formule de l'abonnement concerné.
        $plan = '';
        if ($subscription instanceof \WC_Subscription) {
            if (function_exists('on_sanitize_subscription_formule')) {
                $plan = on_sanitize_subscription_formule($subscription->get_meta('on_formule', true));
            }
            if ('' === $plan && function_exists('on_guess_subscription_formule_from_items')) {
                $plan = on_guess_subscription_formule_from_items($subscription);
            }
        }

        $triggerData = [
            'userId'     => $acyUser->id,
            'statusFrom' => $old_status,
            'statusTo'   => $new_status,
            'plan'       => $plan,
        ];

        try {
            $automationClass = new AutomationClass();
            $automationClass->trigger(self::TRIGGER_KEY, $triggerData);
        } catch (\Throwable $e) {
            acym_logError('on_subscription_status_changed (automation) : ' . $e->getMessage());
        }

        try {
            $scenarioHelper = new ScenarioHelper();
            $scenarioHelper->trigger(self::TRIGGER_KEY, $triggerData);
        } catch (\Throwable $e) {
            acym_logError('on_subscription_status_changed (scenario) : ' . $e->getMessage());
        }
    }
}
