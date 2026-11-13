<?php
/**
 * Élément Elementor « Lecteur PDF en ligne ».
 *
 * Affiche un bouton « Lire en ligne » qui ouvre, sous le bouton, un lecteur
 * du PDF d'un numéro (cahier de partitions ou magazine) avec, quand le PDF
 * contient des liens .mp3, la lecture des enregistrements.
 *
 * Le produit est retrouvé comme pour la balise « Adresse de lecture » : même
 * numéro que la page, même langue, téléchargeable et gratuit pour le client
 * connecté (voir includes/usage/dynamic-tags.php). Le PDF est servi par
 * /?on_action=read_pdf&product_id=… : le visiteur ne voit jamais l'adresse
 * réelle du fichier et la lecture est comptée (includes/usage/count.php).
 *
 * Sans droit de lecture, l'élément n'affiche rien (en édition : une note).
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
        return 'Lecteur PDF en ligne';
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
        $this->start_controls_section('on_reader_content', array('label' => 'Lecteur'));

        $this->add_control('type', array(
            'label'   => 'Document à lire',
            'type'    => Controls_Manager::SELECT,
            'default' => 'cahier',
            'options' => array(
                'cahier'   => 'Cahier de partitions',
                'magazine' => 'Magazine',
            ),
        ));

        $this->add_control('motif', array(
            'label'       => 'Le nom du produit contient',
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'description' => 'Laisser vide : CahPart pour un cahier, ONED pour un magazine. Sinon un morceau du nom du produit.',
        ));

        $this->add_control('button_label', array(
            'label'       => 'Texte du bouton',
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'description' => 'Laisser vide : « Lire en ligne » (FR) ou « Read online » (EN) selon la langue de la page.',
        ));

        $this->add_control('title', array(
            'label'       => 'Titre dans le lecteur',
            'type'        => Controls_Manager::TEXT,
            'default'     => '',
            'description' => 'Laisser vide : « Cahier de partitions » ou « Magazine » (FR/EN) suivi du numéro.',
        ));

        $this->end_controls_section();

        $this->start_controls_section('on_reader_style', array(
            'label' => 'Bouton',
            'tab'   => Controls_Manager::TAB_STYLE,
        ));

        $this->add_control('button_color', array(
            'label'     => 'Couleur du fond',
            'type'      => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .on-rd-open' => 'background-color: {{VALUE}};'),
        ));
        $this->add_control('button_text_color', array(
            'label'     => 'Couleur du texte',
            'type'      => Controls_Manager::COLOR,
            'selectors' => array('{{WRAPPER}} .on-rd-open' => 'color: {{VALUE}};'),
        ));
        $this->add_control('reader_height', array(
            'label'      => 'Hauteur du lecteur',
            'type'       => Controls_Manager::SLIDER,
            'size_units' => array('px', 'vh'),
            'range'      => array('px' => array('min' => 400, 'max' => 1400), 'vh' => array('min' => 40, 'max' => 100)),
            'selectors'  => array('{{WRAPPER}} .on-rd' => 'height: {{SIZE}}{{UNIT}};'),
        ));

        $this->end_controls_section();
    }

    /** Textes selon la langue de la page (Polylang), français par défaut. */
    private static function labels($lang)
    {
        if ($lang === 'en') {
            return array(
                'open' => 'Read online', 'cahier' => 'Score booklet', 'magazine' => 'Magazine',
                'issue' => 'issue', 'toc' => 'Recordings', 'tocHint' => 'Tap a title to open the score, or the button to listen. Sound keeps playing while you turn pages.',
                'close' => 'Close', 'fs' => 'Full screen', 'prev' => 'Previous pages', 'next' => 'Next pages',
                'zoomOut' => 'Zoom out', 'zoomIn' => 'Zoom in', 'loading' => 'Loading…', 'player' => 'Audio player',
                'none' => 'No track playing', 'hint' => 'Tap a speaker icon on the page to listen.',
                'showPage' => 'Show the page', 'seek' => 'Position in the recording',
                'play' => 'Play', 'pause' => 'Pause', 'listen' => 'Listen', 'listenTitle' => 'Listen to “%s”',
                'audioError' => 'This recording could not be played. Check your connection and try again.',
                'openLink' => 'Open link', 'pageFail' => 'This page could not be displayed.', 'piece' => 'Track',
                'noRecordings' => 'No recordings in this document.', 'pageWord' => 'page',
                'libFail' => 'The reader could not be loaded.', 'openFail' => 'The document could not be opened.',
                'noAccess' => 'Online reading is not available for this issue with your account.',
            );
        }
        return array(
            'open' => 'Lire en ligne', 'cahier' => 'Cahier de partitions', 'magazine' => 'Magazine',
            'issue' => 'n°', 'toc' => 'Enregistrements', 'tocHint' => 'Touchez le titre pour ouvrir la partition, ou le bouton pour écouter. Le son continue quand vous tournez les pages.',
            'close' => 'Fermer', 'fs' => 'Plein écran', 'prev' => 'Pages précédentes', 'next' => 'Pages suivantes',
            'zoomOut' => 'Réduire', 'zoomIn' => 'Agrandir', 'loading' => 'Chargement…', 'player' => 'Lecteur audio',
            'none' => 'Aucune pièce en cours', 'hint' => 'Touchez le haut-parleur d\'une partition pour l\'écouter.',
            'showPage' => 'Voir la page', 'seek' => 'Position dans l\'enregistrement',
            'play' => 'Lecture', 'pause' => 'Pause', 'listen' => 'Écouter', 'listenTitle' => 'Écouter « %s »',
            'audioError' => 'Cet enregistrement n\'a pas pu être lu. Vérifiez la connexion, puis réessayez.',
            'openLink' => 'Ouvrir le lien', 'pageFail' => 'Cette page n\'a pas pu être affichée.', 'piece' => 'Pièce',
            'noRecordings' => 'Aucun enregistrement dans ce document.', 'pageWord' => 'page',
            'libFail' => 'La visionneuse n\'a pas pu se charger.', 'openFail' => 'Le document n\'a pas pu être ouvert.',
            'noAccess' => 'La lecture en ligne n\'est pas disponible pour ce numéro avec votre compte.',
        );
    }

    protected function render()
    {
        $s    = $this->get_settings_for_display();
        $type = ($s['type'] === 'magazine') ? 'magazine' : 'cahier';
        $lang = function_exists('pll_current_language') ? (string) pll_current_language() : substr(get_locale(), 0, 2);
        $lang = ($lang === 'en') ? 'en' : 'fr';
        $L    = self::labels($lang);

        $motif = trim((string) $s['motif']);
        if ($motif === '') {
            $motif = ($type === 'magazine') ? 'ONED' : 'CahPart';
        }

        $product = function_exists('on_usage_page_product') ? on_usage_page_product($motif) : null;
        if (!$product) {
            // Pas de droit (ou pas de produit) : rien pour le visiteur.
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<p style="padding:8px;border:1px dashed #999;font:14px sans-serif">Lecteur PDF en ligne : aucun produit lisible pour cette page et ce compte (rien ne s\'affiche aux visiteurs sans droit).</p>';
            }
            return;
        }

        wp_enqueue_style('on-online-reader');
        wp_enqueue_script('on-online-reader');

        $url = add_query_arg(array(
            'on_action'  => 'read_pdf',
            'product_id' => $product->get_id(),
        ), home_url('/'));

        $numero = function_exists('pods_field') ? pods_field(get_post_type(), get_the_ID(), 'numero', true) : '';
        $numero = is_scalar($numero) ? (string) $numero : '';
        $sub    = 'Orgues Nouvelles' . ($numero !== '' ? ' ' . $L['issue'] . ' ' . $numero : '');
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
