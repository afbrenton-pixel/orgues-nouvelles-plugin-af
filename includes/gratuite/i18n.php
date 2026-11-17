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

/** Locale used for a request language. */
function on_gratuite_locale( $lang ) {
    return 'en' === $lang ? 'en_GB' : 'fr_FR';
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
    $switched = function_exists( 'switch_to_locale' ) ? switch_to_locale( on_gratuite_locale( $lang ) ) : false;
    if ( $switched ) {
        // The plugin text domain was loaded at start-up, in the language of the page: drop it, and read the
        // language file of the request explicitly (French is the language of the source texts: no file).
        unload_textdomain( $domain );
        if ( 'en' === $lang ) {
            load_textdomain( $domain, dirname( __DIR__, 2 ) . '/languages/' . $domain . '-' . on_gratuite_locale( $lang ) . '.mo' );
        }
    }
    try {
        return $callback();
    } finally {
        if ( $switched ) {
            restore_previous_locale();
            // Back to the translations of the previous language.
            unload_textdomain( $domain );
            load_plugin_textdomain( $domain, false, dirname( plugin_basename( dirname( __DIR__, 2 ) . '/orgues-nouvelles-plugin.php' ) ) . '/languages/' );
        }
    }
}
