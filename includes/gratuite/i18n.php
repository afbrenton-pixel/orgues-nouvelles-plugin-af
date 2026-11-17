<?php
/**
 * Free student subscription: language of a request.
 *
 * Every visible text of the module is written once, in French (the default language of the plugin), inside
 * the translation function of WordPress (double underscore, text domain orgues-nouvelles). The English text lives in languages/orgues-nouvelles-en_GB.po (compiled
 * into the .mo file). A request keeps the language of the person (field "langue" = fr or en), which is not
 * always the language of the page or of the administrator who clicks "Accepter": on_gratuite_in_lang() runs a
 * piece of code with the translations of that language.
 */
defined( 'ABSPATH' ) || exit;

/**
 * Locale switched to for a request language. English requests use en_GB, the locale declared by Polylang
 * (installed on the site: switch_to_locale() refuses a locale that is not installed, which then leaves the
 * texts in the language of the administrator).
 * The texts of the plugin do NOT depend on this switch: tested on 17 November 2026, WordPress does not
 * read languages/orgues-nouvelles-en_GB.mo by itself after switch_to_locale( 'en_GB' ) (the e-mails stayed in
 * French), so on_gratuite_in_lang() loads the file explicitly (see below).
 */
function on_gratuite_locale( $lang ) {
    return 'en' === $lang ? 'en_GB' : 'fr_FR';
}

/** TEMPORARY (debug 17 Nov 2026, test-integration only): log what WordPress does with the text domain. */
function on_gratuite_debug_i18n( $step ) {
    global $wp_textdomain_registry, $l10n;
    $domain = 'orgues-nouvelles';
    $info   = array(
        'step'            => $step,
        'determine_locale' => function_exists( 'determine_locale' ) ? determine_locale() : '?',
        'get_locale'      => get_locale(),
        'loaded'          => function_exists( 'is_textdomain_loaded' ) ? ( is_textdomain_loaded( $domain ) ? 'yes' : 'no' ) : '?',
        'in_l10n'         => isset( $l10n[ $domain ] ) ? get_class( $l10n[ $domain ] ) : 'absent',
        'test_text'       => __( 'Bonjour %s,', $domain ),
        'plugin_dir'      => dirname( __DIR__, 2 ),
        'mo_en_GB_exists' => file_exists( dirname( __DIR__, 2 ) . '/languages/' . $domain . '-en_GB.mo' ) ? 'yes' : 'no',
        'mo_fr_FR_exists' => file_exists( dirname( __DIR__, 2 ) . '/languages/' . $domain . '-fr_FR.mo' ) ? 'yes' : 'no',
        'wp_version'      => $GLOBALS['wp_version'] ?? '?',
    );
    if ( is_object( $wp_textdomain_registry ) ) {
        foreach ( array( 'fr_FR', 'en_GB' ) as $loc ) {
            try {
                $info[ 'registry_' . $loc ] = var_export( $wp_textdomain_registry->get( $domain, $loc ), true );
            } catch ( \Throwable $e ) {
                $info[ 'registry_' . $loc ] = 'err ' . $e->getMessage();
            }
        }
        try {
            $r = new ReflectionObject( $wp_textdomain_registry );
            foreach ( array( 'custom_paths', 'cached_mo_files', 'current' ) as $prop ) {
                if ( $r->hasProperty( $prop ) ) {
                    $pp = $r->getProperty( $prop );
                    $pp->setAccessible( true );
                    $v = $pp->getValue( $wp_textdomain_registry );
                    $info[ 'registry_prop_' . $prop ] = is_array( $v ) ? ( 'custom_paths' === $prop ? wp_json_encode( array_intersect_key( $v, array( $domain => 1 ) ) ) : count( $v ) . ' items' ) : var_export( $v, true );
                }
            }
        } catch ( \Throwable $e ) {
            $info['registry_reflect'] = 'err ' . $e->getMessage();
        }
    }
    error_log( 'ON-I18N-DEBUG ' . wp_json_encode( $info ) );
}

/**
 * Run $callback with the texts of $lang, then put the previous language back.
 *
 * @param string   $lang     'fr' or 'en'.
 * @param callable $callback Function returning the value (texts, dates...).
 * @return mixed
 */
function on_gratuite_in_lang( $lang, callable $callback ) {
    $domain   = 'orgues-nouvelles';
    if ( 'en' === $lang ) { on_gratuite_debug_i18n( 'A avant le changement de langue' ); }
    $switched = function_exists( 'switch_to_locale' ) ? switch_to_locale( on_gratuite_locale( $lang ) ) : false;
    if ( 'en' === $lang ) { on_gratuite_debug_i18n( 'B apres switch_to_locale(' . var_export( $switched, true ) . '), avant chargement explicite' ); }
    $reload   = false;
    if ( function_exists( 'load_textdomain' ) ) {
        // The plugin text domain was loaded at start-up, in the language of the page: drop it and read the
        // language file of the request explicitly, whatever the current locale is (French is the language of
        // the source texts: no file).
        $reload = true;
        unload_textdomain( $domain );
        if ( 'en' === $lang ) {
            load_textdomain( $domain, dirname( __DIR__, 2 ) . '/languages/' . $domain . '-en_GB.mo', function_exists( 'determine_locale' ) ? determine_locale() : null );
        }
    }
    try {
        return $callback();
    } finally {
        if ( $switched ) {
            restore_previous_locale();
        }
        if ( $reload ) {
            // Back to the translations of the language of the page.
            unload_textdomain( $domain );
            load_plugin_textdomain( $domain, false, dirname( plugin_basename( dirname( __DIR__, 2 ) . '/orgues-nouvelles-plugin.php' ) ) . '/languages/' );
        }
    }
}
