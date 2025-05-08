<?php
if (!defined('ABSPATH')) {
    exit;
}

class Get_WMD_Options {

    // AJAX-Handler initialisieren
    public static function init() {
        add_action('wp_ajax_load_wmd_options', array(__CLASS__, 'load_wmd_options_callback'));
        add_action('wp_ajax_nopriv_load_wmd_options', array(__CLASS__, 'load_wmd_options_callback'));
    }

    // AJAX Callback-Funktion zum Abrufen der WMD-Optionen
    public static function load_wmd_options_callback() {
        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error('Produkt-ID fehlt.');
        }

        $product_id = intval($_POST['product_id']);
        $instance = new self();
        $output = $instance->get_wmd_options($product_id);
        wp_send_json_success($output);
    }

    // Funktion zum Abrufen des HTML-Inhalts von der angegebenen URL
    public function get_wmd_options($product_id = null) {
        if (is_null($product_id)) {
            return 'Keine Produkt-ID angegeben.';
        }

        // Überprüfen, ob das Produkt eine Beziehung zur Taxonomie 'wmd_option' hat
        $terms = wp_get_post_terms($product_id, 'wmd_option');
        
        if (is_wp_error($terms) || empty($terms)) {
            return 'Keine zugeordnete Option gefunden.';
        }

        // Die URL aus dem benutzerdefinierten Feld 'wmd_product_link' der zugeordneten Taxonomie abrufen
        $pod = pods('wmd_option', $terms[0]->term_id);
        $url = $pod->field('wmd_product_link');
        
        if (empty($url)) {
            return 'Keine gültige URL im benutzerdefinierten Feld gefunden.';
        }
        
        // Quelltext mit wp_remote_get abrufen
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            return 'Fehler beim Abrufen der URL.';
        }
        
        $html = wp_remote_retrieve_body($response);
        
        // DOMDocument verwenden, um die Elemente mit den IDs aus dem Feld "wmd_option_id" zu finden
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        // IDs aus dem Feld "wmd_option_id" abrufen
        $option_ids = $pod->field('wmd_option_id');
        if (empty($option_ids)) {
            return 'Keine gültigen IDs im Feld "wmd_option_id" gefunden.';
        }

        $output = '';

        // Eine Liste der Eingabefeldtypen, die durchsucht werden sollen
        $field_tags = ['input', 'select', 'textarea'];
        
        foreach ($option_ids as $option_id) {
        // Durchsuche alle Eingabefeldtypen nach dem entsprechenden 'name'-Attribut
            foreach ($field_tags as $tag) {
                $elements = $dom->getElementsByTagName($tag);
                foreach ($elements as $element) {
                    if ($element->hasAttribute('name') && $element->getAttribute('name') === $option_id) {
                        // Überprüfen, ob das Element ein "onchange"-Attribut hat
                        if ($element->hasAttribute('onchange')) {
                            // JavaScript-Funktion für das "onchange"-Attribut ersetzen und die Pods-Daten sowie das Event übergeben
                            $pods_data_json = json_encode($pod->row(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
                            $element->setAttribute('onchange', "wmdOptionOnChange(event, " . $pods_data_json . ");");
                        }
                        // Füge die Klasse "cpo-wmd-option" hinzu
                        $existing_class = $element->getAttribute('class');
                        $element->setAttribute('class', trim($existing_class . ' cpo-wmd-option'));
                        // HTML-Inhalt des Elements extrahieren und anhängen
                        $output .= $dom->saveHTML($element);
                    }
                }
            } 
        }

        // HTML-Inhalt direkt auswerten, ohne str_get_html
        $price = '';

        // Beispielhafte Extraktion des Preises aus dem HTML mit regulären Ausdrücken
        if (preg_match('/<span id="gesamtpreis_brutto">(.*?)<\/span>/s', $html, $matches)) {
            $price = trim($matches[1]);
            error_log('Preis gefunden: ' . $price);
        } else {
            $price = 'Preis nicht gefunden';
            error_log('Preis nicht gefunden');
        }

        // Erstelle das Antwort-Array
        $extracted_data = array(
            'price' => $price,
            'html' => $output
        );

        // Sende eine erfolgreiche Antwort mit den extrahierten Daten zurück
        error_log('Antwortdaten: ' . print_r($extracted_data, true));
        wp_send_json_success($extracted_data);
    }
}

// Initialisiere den AJAX-Handler
Get_WMD_Options::init();
