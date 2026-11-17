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
 * Locale switched to for a request language. English requests use en_US, which every WordPress has (the site
 * does not necessarily have en_GB installed, and switch_to_locale() refuses a locale that is not installed).
 * The texts of the plugin do not depend on it: they come from languages/orgues-nouvelles-en_GB.po (see below).
 */
function on_gratuite_locale( $lang ) {
    return 'en' === $lang ? 'en_US' : 'fr_FR';
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
