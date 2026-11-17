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
 * Locale switched to for a request language. The locales must be installed on the site (Settings > General >
 * Site language): switch_to_locale() refuses a locale that is not installed and the texts then stay in the
 * language of the administrator. The English texts are read by WordPress from
 * languages/orgues-nouvelles-en_GB.mo once the locale is en_GB.
 * Note: the site must hold a single copy of the plugin. A second (inactive) copy with the same text domain
 * makes WordPress register its own languages folder for the domain when the administration lists the plugins,
 * and the English texts are then no longer found.
 */
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
    $switched = function_exists( 'switch_to_locale' ) ? switch_to_locale( on_gratuite_locale( $lang ) ) : false;
    try {
        return $callback();
    } finally {
        if ( $switched ) {
            restore_previous_locale();
        }
    }
}
