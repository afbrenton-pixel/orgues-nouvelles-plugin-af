<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('pods_field_pick_data_ajax_items', 'on_custom_pods_labels_in_pick_field_data', 1, 6);
add_filter('pods_field_pick_data', 'on_custom_pods_labels_in_pick_field_data', 1, 6);

function on_custom_pods_labels_in_pick_field_data($items, $name, $value, $options, $pod, $id)
{
    if (!empty($items) && is_array($items)) {
        // pods_meta_ prefix for Pods backend, pods_field_ prefix for front-facing Pods form.
        $pods_types = array(
            'magazine' => array('magazines', 'magazine', 'page_magazine', 'journals'),
            'cd' => array('cd', 'cds', 'page_cd'),
            'partition' => array('partitions', 'partition', 'page_partition', 'page_partitions'),
        );

        $found_type = null;
        foreach ($pods_types as $type => $names) {
            if (in_array($name, $names, true)) {
                $found_type = $type;
                break;
            }
        }

        if ($found_type) {
            foreach ($items as $key => &$data) {
                if (isset($data['id'])) {
                    $data['text'] = on_custom_pods_select_field_label($data['id'], $found_type);
                    $data['name'] = $data['text'];
                } elseif (is_numeric($key) && !is_array($data)) {
                    $items[$key] = on_custom_pods_select_field_label($key, $found_type);
                }
            }
            unset($data);
        } else {
            foreach ($items as $key => &$data) {
                if (isset($data['id']) && isset($data['text'])) {
                    $data['text'] = on_add_language_to_pods_label($data['id'], $data['text']);
                    $data['name'] = $data['text'];
                }
            }
            unset($data);
        }
    }

    return $items;
}

function on_add_language_to_pods_label($id, $label)
{
    $lang_code = '';

    if (function_exists('pll_get_post_language')) {
        $lang_code = pll_get_post_language($id, 'slug');
    }

    if ($lang_code) {
        $label .= ('fr' === $lang_code) ? ' 🇫🇷' : ' 🇬🇧';
    }

    return $label;
}

function on_custom_pods_select_field_label($id, $pods_name)
{
    $pod = pods($pods_name, $id);
    $numero = $pod->field('numero');
    $date = $pod->field('date');
    $date = date_i18n('F Y', strtotime($date));

    $prefixes = array(
        'magazine' => '📖 Orgues Nouvelles',
        'cd' => '💿 CD',
        'partitions' => '🎼 Cahier de partitions',
    );

    $prefix = isset($prefixes[$pods_name]) ? $prefixes[$pods_name] : '';

    $label = trim($prefix);
    if ('' !== $label) {
        $label .= ' ';
    }
    $label .= 'n°' . $numero . ' - ' . $date;

    return on_add_language_to_pods_label($id, $label);
}
