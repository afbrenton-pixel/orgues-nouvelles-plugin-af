<?php

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	eval( 'namespace Elementor { class Widget_Base {} class Controls_Manager {} class Group_Control_Border {} class Group_Control_Typography {} }' );
}
require_once dirname( __DIR__ ) . '/includes/elementor/widgets/revues-grille.php';

/**
 * Grille des revues : reconnaissance du numéro dans le nom d'un produit.
 */
class RevuesGrilleTest extends TestCase {
	public function names() {
		return array(
			'papier'                  => array( 'Orgues Nouvelles n°74', 'Orgues Nouvelles n°', 74 ),
			'numéro à un chiffre'     => array( 'Orgues Nouvelles n°7', 'Orgues Nouvelles n°', 7 ),
			'numérique'               => array( 'ONED74', 'ONED', 74 ),
			'zéros devant'            => array( 'ONED074', 'ONED', 74 ),
			'casse ignorée'           => array( 'oned9', 'ONED', 9 ),
			'cahier'                  => array( 'CahPart73', 'CahPart', 73 ),
			'anglais : numérique'     => array( 'Orgues-Nouvelles 74 Download', 'Orgues-Nouvelles', 74 ),
			'anglais : cahier'        => array( 'Orgues Nouvelles Music Booklet 74', 'Orgues Nouvelles Music Booklet', 74 ),
			'ONEDA n\'est pas ONED'   => array( 'ONEDA74', 'ONED', 0 ),
			'autre produit'           => array( 'Calendrier 2027', 'ONED', 0 ),
			'début de nom vide'       => array( 'ONED74', '', 0 ),
		);
	}

	/**
	 * @dataProvider names
	 */
	public function test_numero_du_nom( $name, $prefix, $expected ) {
		$this->assertSame( $expected, on_revues_grille_numero_du_nom( $name, $prefix ) );
	}
}
