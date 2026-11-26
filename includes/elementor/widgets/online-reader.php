<?php
/**
 * Élément Elementor « Lecteur PDF en ligne ».
 *
 * Affiche un bouton « Lire en ligne » qui ouvre, sous le bouton, un lecteur de
 * PDF avec, quand le PDF contient des liens .mp3, la lecture des enregistrements.
 *
 * Le PDF est donné par le champ « Adresse du PDF », qui accepte une balise
 * dynamique : « Adresse de lecture » pour un magazine ou un cahier (le produit du
 * champ « Téléchargement » de la page, voir includes/usage/dynamic-tags.php ;
 * le PDF est alors servi par /?on_action=read_pdf&product_id=… : le visiteur ne
 * voit jamais l'adresse réelle du fichier, et la lecture est comptée), ou
 * n'importe quelle adresse de PDF (une ressource, un article…).
 * Champ vide : l'élément reprend le produit du champ « Téléchargement » de la
 * page (comme la balise « Adresse de lecture »).
 *
 * Sans droit de lecture (adresse vide), l'élément n'affiche rien (en édition :
 * une note).
 * Voir docs/lecture-en-ligne.md.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Controls_Manager;
use Elementor\Widget_Base;

class ON_Online_Reader extends Widget_Base
{
    public function get_name()
    {
        return 'online-reader';
    }

    public function get_title()
    {
        return __('Lecteur PDF en ligne', 'orgues-nouvelles');
    }

    public function get_icon()
    {
        return 'eicon-document-file';
    }

    public function get_categories()
    {
        return array('general');
    }

    public function get_keywords()
    {
        return array('pdf', 'lecteur', 'lecture', 'cahier', 'magazine', 'mp3');
    }

    // Dans l'éditeur Elementor seulement : sur le site, les fichiers (dont PDF.js)
    // ne sont chargés que si le lecteur est réellement affiché (voir render()).
    public function get_script_depends()
    {
        return \Elementor\Plugin::$instance->editor->is_edit_mode() ? array('on-online-reader') : array();
    }

    public function get_style_depends()
    {
        return \Elementor\Plugin::$instance->editor->is_edit_mode() ? array('on-online-reader') : array();
    }

    protected function register_controls()
    {
        $this->start_controls_section('on_reader_content', array('label' => __('Lecteur', 'orgues-nouvelles')));

        $this->add_control('pdf_url', array(
            'label'       => __('Adresse du PDF', 'orgues-nouvelles'),
            'type'        => Controls_Manager::URL,
            'dynamic'     => array('active' => true),
            'placeholder' => 'https://…/document.pdf',
            'description' => __('Choisir la balise « Adresse de lecture » (icône de balise dynamique) pour un magazine ou un cahier de partitions, ou saisir l\'adresse d\'un PDF. Laisser vide : le PDF du produit « Téléchargement » de la page.', 'orgues-nouvelles'),
        ));

        $this->add_control('type', array(
            'label'   => __('Titre par défaut', 'orgues-nouvelles'),
            'type'    => Controls_Manager::SELECT,
            'default' => 'cahier',
            'options' => array(
                'cahier'   => __('Cahier de partitions', 'orgues-nouvelles'),
                'magazine' => __('Magazine', 'orgues-nouvelles'),
                'document' => __('Document', 'orgues-nouvelles'),
            ),
        ));

        $this->add_control('button_label', array(
            'label'       => __('Texte du bouton', 'orgues-nouvelles'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'description' => __('Laisser vide : « Lire en ligne », dans la langue de la page.', 'orgues-nouvelles'),
        ));

        $this->add_control('title', array(
            'label'       => __('Titre dans le lecteur', 'orgues-nouvelles'),
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'description' => __('Laisser vide : « Cahier de partitions » ou « Magazine », dans la langue de la page.', 'orgues-nouvelles'),
        ));

        $this->end_controls_section();

        $this->start_controls_section('on_reader_style', array(
            'label' => __('Bouton', 'orgues-nouvelles'),
            'tab'   => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('button_color', array(
            'label'     => __('Couleur du fond', 'orgues-nouvelles'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .on-rd-open' => 'background-color: {{VALUE}};'),
        ));
        $this->add_control('button_text_color', array(
            'label'     => __('Couleur du texte', 'orgues-nouvelles'),
            'type'      => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .on-rd-open' => 'color: {{VALUE}};'),
        ));
        $this->add_control('reader_height', array(
            'label'      => __('Hauteur du lecteur', 'orgues-nouvelles'),
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px', 'vh'),
            'range'      => array('px' => array('min' => 400, 'max' => 1400), 'vh' => array('min' => 40, 'max' => 100)),
            'selectors'  => array('{{WRAPPER}} .on-rd' => 'height: {{SIZE}}{{UNIT}};'),
        ));

        $this->end_controls_section();
    }

    /**
     * Textes du lecteur : écrits une fois, en français, avec le système de
     * traduction de WordPress (l'anglais est dans languages/orgues-nouvelles-en_GB.po).
     * Ils sont aussi transmis au JavaScript (attribut data-labels).
     */
    private static function labels()
    {
        return array(
            'open' => __('Lire en ligne', 'orgues-nouvelles'),
            'cahier' => __('Cahier de partitions', 'orgues-nouvelles'),
            'magazine' => __('Magazine', 'orgues-nouvelles'),
            'document' => __('Document', 'orgues-nouvelles'),
            'toc' => __('Enregistrements', 'orgues-nouvelles'),
            'tocHint' => __('Touchez le titre pour ouvrir la partition, ou le bouton pour écouter. Le son continue quand vous tournez les pages.', 'orgues-nouvelles'),
            'close' => __('Fermer', 'orgues-nouvelles'),
            'fs' => __('Plein écran', 'orgues-nouvelles'),
            'prev' => __('Pages précédentes', 'orgues-nouvelles'),
            'next' => __('Pages suivantes', 'orgues-nouvelles'),
            'zoomOut' => _x('Réduire', 'zoom du lecteur', 'orgues-nouvelles'),
            'zoomIn' => _x('Agrandir', 'zoom du lecteur', 'orgues-nouvelles'),
            'loading' => __('Chargement…', 'orgues-nouvelles'),
            'player' => __('Lecteur audio', 'orgues-nouvelles'),
            'none' => __('Aucune pièce en cours', 'orgues-nouvelles'),
            'hint' => __('Touchez le haut-parleur d\'une partition pour l\'écouter.', 'orgues-nouvelles'),
            'showPage' => __('Voir la page', 'orgues-nouvelles'),
            'seek' => __('Position dans l\'enregistrement', 'orgues-nouvelles'),
            'play' => __('Lecture', 'orgues-nouvelles'),
            'pause' => __('Pause', 'orgues-nouvelles'),
            'listen' => __('Écouter', 'orgues-nouvelles'),
            /* translators: %s: titre de l'enregistrement */
            'listenTitle' => __('Écouter « %s »', 'orgues-nouvelles'),
            'audioError' => __('Cet enregistrement n\'a pas pu être lu. Vérifiez la connexion, puis réessayez.', 'orgues-nouvelles'),
            'openLink' => __('Ouvrir le lien', 'orgues-nouvelles'),
            'pageFail' => __('Cette page n\'a pas pu être affichée.', 'orgues-nouvelles'),
            'piece' => __('Pièce', 'orgues-nouvelles'),
            'noRecordings' => __('Aucun enregistrement dans ce document.', 'orgues-nouvelles'),
            'pageWord' => __('page', 'orgues-nouvelles'),
            'libFail' => __('La visionneuse n\'a pas pu se charger.', 'orgues-nouvelles'),
            'openFail' => __('Le document n\'a pas pu être ouvert.', 'orgues-nouvelles'),
            'noAccess' => __('La lecture en ligne n\'est pas disponible pour ce numéro avec votre compte.', 'orgues-nouvelles'),
        );
    }

    protected function render()
    {
        $s    = $this->get_settings_for_display();
        $type = in_array($s['type'], array('magazine', 'document'), true) ? $s['type'] : 'cahier';
        $L    = self::labels();

        // Adresse du PDF : le champ (souvent la balise « Adresse de lecture »), sinon
        // le produit du champ « Téléchargement » de la page.
        $url = (is_array($s['pdf_url']) && !empty($s['pdf_url']['url'])) ? trim((string) $s['pdf_url']['url']) : '';
        $dyn = $this->get_settings('__dynamic__');
        // Champ vide ET pas de balise : on reprend le produit de la page. Avec une balise qui
        // ne rend rien (pas de droit), on n'affiche rien.
        if ($url === '' && empty($dyn['pdf_url'])) {
            $product = function_exists('on_usage_page_product') ? on_usage_page_product('', true) : null;
            if ($product) {
                $url = add_query_arg(array(
                    'on_action'  => 'read_pdf',
                    'product_id' => $product->get_id(),
                ), home_url('/'));
            }
        }
        if ($url === '') {
            // Pas de droit (ou pas de PDF) : rien pour le visiteur.
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p style="padding:8px;border:1px dashed #999;font:14px sans-serif">' . esc_html__('Lecteur PDF en ligne : aucun PDF lisible pour cette page et ce compte (rien ne s\'affiche aux visiteurs sans droit).', 'orgues-nouvelles') . '</p>';
            }
            return;
        }

        wp_enqueue_style('on-online-reader');
        wp_enqueue_script('on-online-reader');

        $numero = function_exists('pods_field') ? pods_field(get_post_type(), get_the_ID(), 'numero', true) : '';
        $numero = is_scalar($numero) ? (string) $numero : '';
        $sub    = ($numero !== '')
            /* translators: %s: numéro du magazine ou du cahier */
            ? sprintf(__('Orgues Nouvelles n° %s', 'orgues-nouvelles'), $numero)
            : get_the_title();
        $title  = trim((string) $s['title']) !== '' ? $s['title'] : $L[$type];
        $btn    = trim((string) $s['button_label']) !== '' ? $s['button_label'] : $L['open'];
        $uid    = 'on-rd-' . $this->get_id();

        $chev_l = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 5l-7 7 7 7"/></svg>';
        $chev_r = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 5l7 7-7 7"/></svg>';
        ?>
        <div class="on-reader">
            <button type="button" class="on-rd-open" data-r="Open" aria-expanded="false" aria-controls="<?php echo esc_attr($uid); ?>"><?php echo esc_html($btn); ?> &gt;</button>
            <div class="on-rd" id="<?php echo esc_attr($uid); ?>" data-r="Panel" hidden
                 data-pdf="<?php echo esc_url($url); ?>"
                 data-sub="<?php echo esc_attr($sub); ?>"
                 data-labels="<?php echo esc_attr(wp_json_encode($L)); ?>">
                <header class="top">
                    <div><h2 data-r="Title"><?php echo esc_html($title); ?></h2><p class="sub" data-r="Sub"><?php echo esc_html($sub); ?></p></div>
                    <div class="tools">
                        <button class="btn" data-r="Fs" type="button"><span class="lbl"><?php echo esc_html($L['fs']); ?></span></button>
                        <button class="btn" data-r="TocBtn" type="button" aria-expanded="false"><span class="lbl"><?php echo esc_html($L['toc']); ?></span></button>
                        <button class="btn" data-r="Shut" type="button"><span class="lbl">✕ <?php echo esc_html($L['close']); ?></span></button>
                    </div>
                </header>
                <div class="stage">
                    <div class="view" data-r="View"><div class="spread" data-r="Spread" aria-live="polite"><span class="loading"><?php echo esc_html($L['loading']); ?></span></div></div>
                    <nav class="nav">
                        <button class="btn" data-r="Prev" type="button" aria-label="<?php echo esc_attr($L['prev']); ?>"><?php echo $chev_l; // phpcs:ignore ?></button>
                        <div class="where"><b data-r="Roman">I</b><small data-r="Num"></small></div>
                        <button class="btn" data-r="Next" type="button" aria-label="<?php echo esc_attr($L['next']); ?>"><?php echo $chev_r; // phpcs:ignore ?></button>
                        <span class="zoom">
                            <button class="btn" data-r="ZoomOut" type="button" aria-label="<?php echo esc_attr($L['zoomOut']); ?>">−</button>
                            <output data-r="ZoomV">100 %</output>
                            <button class="btn" data-r="ZoomIn" type="button" aria-label="<?php echo esc_attr($L['zoomIn']); ?>">+</button>
                        </span>
                    </nav>
                </div>
                <footer class="player" aria-label="<?php echo esc_attr($L['player']); ?>">
                    <button class="playbtn" data-r="Play" type="button" aria-label="<?php echo esc_attr($L['play']); ?>" disabled><svg data-r="IcoPlay" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg><svg data-r="IcoPause" viewBox="0 0 24 24" aria-hidden="true" style="display:none"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg></button>
                    <div class="now"><div class="t" data-r="NowT"><?php echo esc_html($L['none']); ?></div><div class="p" data-r="NowP"><?php echo esc_html($L['hint']); ?></div></div>
                    <div class="seek"><input type="range" data-r="Seek" min="0" max="1000" value="0" step="1" aria-label="<?php echo esc_attr($L['seek']); ?>" disabled><time data-r="Time">0:00 / 0:00</time></div>
                    <button class="btn showpage" data-r="Show" type="button" disabled><span class="lbl"><?php echo esc_html($L['showPage']); ?></span></button>
                </footer>
                <div class="toc-back" data-r="Back" hidden></div>
                <aside class="toc" data-r="Toc" hidden><h3><?php echo esc_html($L['toc']); ?></h3><p class="hint"><?php echo esc_html($L['tocHint']); ?></p><ol data-r="List"></ol><button class="btn close" data-r="Close" type="button"><?php echo esc_html($L['close']); ?></button></aside>
                <audio data-r="Audio" preload="none"></audio>
            </div>
        </div>
        <?php
    }
}
