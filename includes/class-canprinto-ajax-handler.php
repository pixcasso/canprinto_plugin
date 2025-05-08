<?php
if (!defined('ABSPATH')) {
    exit;
}

class Canprinto_AJAX_Handler
{

    private static $initialized = false;

    public static function init()
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        // Debugging-Ausgabe
        //error_log('Canprinto_AJAX_Handler init gestartet');

        // Registriere die AJAX-Callbacks für authentifizierte und nicht-authentifizierte Benutzer
        self::register_ajax_handlers();
    }

    // Registriere die AJAX-Callbacks für authentifizierte und nicht-authentifizierte Benutzer
    private static function register_ajax_handlers()
    {
        add_action('wp_ajax_load_sorten_options', array(__CLASS__, 'load_sorten_options_callback'));
        add_action('wp_ajax_nopriv_load_sorten_options', array(__CLASS__, 'load_sorten_options_callback'));

        add_action('wp_ajax_get_price_and_options', array(__CLASS__, 'get_price_and_options_callback'));
        add_action('wp_ajax_nopriv_get_price_and_options', array(__CLASS__, 'get_price_and_options_callback'));

        add_action('wp_ajax_process_wmd_options', array(__CLASS__, 'process_wmd_options_callback'));
        add_action('wp_ajax_nopriv_process_wmd_options', array(__CLASS__, 'process_wmd_options_callback'));
    }

    // Callback-Funktion zum Abrufen von Preis und Optionen
    public static function get_price_and_options_callback()
    {
        error_log('get_price_and_options_callback gestartet');

        // Überprüfe, ob die notwendigen Parameter gesetzt sind
        if (!isset($_POST['product_link']) || empty($_POST['product_link'])) {
            error_log('Produktlink fehlt.');
            wp_send_json_error('Produktlink fehlt.');
        }

        if (!isset($_POST['selected_values']) || empty($_POST['selected_values'])) {
            error_log('Ausgewählter Wert fehlt.');
            wp_send_json_error('Ausgewählter Wert fehlt.');
        }

        if (isset($_POST['pods_data'])) {
            $pods_data = $_POST['pods_data']; // 'pods_data' wird als Array empfangen
        
            // Zugriff auf einzelne Werte innerhalb von pods_data
            if (isset($pods_data['wmd_option_id']) && is_array($pods_data['wmd_option_id'])) {
                $wmd_option_id = $pods_data['wmd_option_id'];
        
                // Debugging: Ausgabe der wmd_option_id im Log
                error_log(print_r($wmd_option_id, true));
            } else {
                error_log('wmd_option_id ist nicht vorhanden oder kein Array.');
            }
        } else {
            error_log('Keine pods_data vorhanden.');
        }
        

        if (isset($_POST['selected_values']) && is_array($_POST['selected_values'])) {
            $selected_values = $_POST['selected_values'];
            
            // Initialisiere das Array für die verarbeiteten Werte
            $processed_values = [];

            // Iteriere durch die erhaltenen Werte
            foreach ($selected_values as $item) {
                if (isset($item['id']) && isset($item['value'])) {
                    // Füge die Werte zu einem Array hinzu (Sanitization der Werte)
                    $processed_values[sanitize_text_field($item['id'])] = sanitize_text_field($item['value']);
                }
            }

            // Debugging: Ausgabe der verarbeiteten Werte im Log
            error_log(print_r($processed_values, true));


        } else {
            error_log('Keine validen selected_values vorhanden.');
        }

        // Produktlink und ausgewählter Wert verarbeiten
        $product_link = esc_url_raw($_POST['product_link']); // Sanitize URL
        //$selected_value = sanitize_text_field($_POST['selected_value']); // Sanitize input
        error_log('Produktlink: ' . $product_link);
        //error_log('Ausgewählter Wert: ' . $selected_value);

        // cURL Initialisierung
        $curl = curl_init();
        error_log('cURL Initialisierung gestartet');

        // Setze die cURL Optionen
        curl_setopt_array($curl, array(
            CURLOPT_URL => $product_link, // URL des Produkts
            CURLOPT_RETURNTRANSFER => true, // Rückgabe als String
            CURLOPT_ENCODING => '', // Alle unterstützten Codierungen akzeptieren
            CURLOPT_MAXREDIRS => 100, // Maximale Anzahl der Weiterleitungen
            CURLOPT_TIMEOUT => 30, // Timeout von 30 Sekunden, um hängende Anfragen zu vermeiden
            CURLOPT_FOLLOWLOCATION => true, // Automatisch Weiterleitungen folgen
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, // HTTP-Version
            CURLOPT_CUSTOMREQUEST => 'POST', // POST-Anfrage
            CURLOPT_POSTFIELDS => $processed_values
        ));

        // Führe die cURL Anfrage aus
        $response = curl_exec($curl);
        error_log('Curl ausgeführt');

        // Fehlerprüfung für cURL
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            error_log('cURL Fehler: ' . $error_msg);
            curl_close($curl);
            wp_send_json_error('cURL Fehler: ' . $error_msg);
        }

        // Schließe die cURL Sitzung
        curl_close($curl);
        error_log('cURL Anfrage erfolgreich ausgeführt');

        // HTML-Inhalt direkt auswerten, ohne str_get_html
        $price = '';
        $option_details = '';

        /* // Beispielhafte Extraktion des Preises aus dem HTML mit regulären Ausdrücken
        if (preg_match('/<span id="gesamtpreis_brutto">(.*?)<\/span>/s', $response, $matches)) {
            $price = trim($matches[1]);
            error_log('Preis gefunden: ' . $price);
        } else {
            $price = 'Preis nicht gefunden';
            error_log('Preis nicht gefunden');
        }

        */

        // Brutto-Gesamtpreis per DOMXPath extrahieren
libxml_use_internal_errors(true); // Fehler unterdrücken
$dom = new DOMDocument();
try {
    $dom->loadHTML($response);
} catch (Exception $e) {
    error_log('Fehler beim Laden des HTML: ' . $e->getMessage());
    wp_send_json_error('Fehler beim Laden des HTML-Inhalts.');
}

$xpath = new DOMXPath($dom);
$nodes = $xpath->query('//*[@id="gesamtpreis_brutto"]');
$price = null;

if ($nodes->length > 0) {
    $price = trim($nodes->item(0)->textContent);
    error_log('Bruttopreis gefunden: ' . $price);
} else {
    $price = 'Preis nicht gefunden';
    error_log('Bruttopreis nicht gefunden');
}


        // Verwende DOMDocument zur Extraktion aller Select-Elemente mit den IDs aus wmd_option_id

        libxml_use_internal_errors(true); // Fehler unterdrücken, um HTML Parsing zu ermöglichen
        $dom = new DOMDocument();
        try {
            $dom->loadHTML($response); // Lade das HTML-Dokument
        } catch (Exception $e) {
            error_log('Fehler beim Laden des HTML: ' . $e->getMessage());
            wp_send_json_error('Fehler beim Laden des HTML-Inhalts.');
        }

        $option_details = '';

        // Eine Liste der Eingabefeldtypen, die durchsucht werden sollen
        $field_tags = ['input', 'select', 'textarea'];
        
        foreach ($wmd_option_id as $id) {
            foreach ($field_tags as $tag) {
                $elements = $dom->getElementsByTagName($tag); // Suche das Element mit der jeweiligen ID
                foreach ($elements as $element) {
                    if ($element->hasAttribute('name') && $element->getAttribute('name') === $id) {
                        // Überprüfen, ob das Element ein "onchange"-Attribut hat
                        if ($element->hasAttribute('onchange')) {
                            // JavaScript-Funktion für das "onchange"-Attribut ersetzen und die Pods-Daten sowie das Event übergeben
                            $pods_data_json = json_encode($pods_data, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
                            $element->setAttribute('onchange', "wmdOptionOnChange(event, " . $pods_data_json . ");");
                        }
                        // Füge die Klasse "cpo-wmd-option" hinzu
                        $existing_class = $element->getAttribute('class');
                        $element->setAttribute('class', trim($existing_class . ' cpo-wmd-option'));
                        // HTML-Inhalt des Elements extrahieren und anhängen
                        $option_details .= $dom->saveHTML($element); // Speichere das HTML des gefundenen Elements
                        error_log('Option gefunden: ' . $dom->saveHTML($element));
                    } else {
                    error_log('Option mit ID ' . $id . ' nicht gefunden');
                    }
                }
            }
        }

        // Erstelle das Antwort-Array
        $extracted_data = array(
            'price' => $price,
            'html' => $option_details
        );

        // Sende eine erfolgreiche Antwort mit den extrahierten Daten zurück
        error_log('Antwortdaten: ' . print_r($extracted_data, true));
        wp_send_json_success($extracted_data);
    }

    // Callback-Funktion zum Verarbeiten von WMD-Optionen
    public static function process_wmd_options_callback()
    {
        error_log('process_wmd_options_callback gestartet');

        // Überprüfe, ob die notwendigen Parameter gesetzt sind
        if (!isset($_POST['pods_data']) || empty($_POST['pods_data'])) {
            error_log('Pods-Daten fehlen.');
            wp_send_json_error('Pods-Daten fehlen.');
        }

        // Pods-Daten verarbeiten
        $pods_data = sanitize_text_field($_POST['pods_data']); // Sanitize und validiere die Pods-Daten
        error_log('Pods-Daten: ' . print_r($pods_data, true));

        // Beispiel: Verarbeite die Pods-Daten
        $response_data = array(
            'message' => 'Pods-Daten wurden erfolgreich verarbeitet.'
        );

        // Sende eine erfolgreiche Antwort zurück
        error_log('Antwortdaten: ' . print_r($response_data, true));
        wp_send_json_success($response_data);
    }

    // AJAX Callback zum Laden der Sorten-Optionen
    public static function load_sorten_options_callback()
    {
        if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
            wp_send_json_error('Produkt-ID fehlt.');
        }

        $product_id = absint($_POST['product_id']);

        // Überprüfen, ob das Produkt eine Beziehung zur Taxonomie 'wmd_option' hat
        $terms = wp_get_post_terms($product_id, 'wmd_option');
        if (is_wp_error($terms) || empty($terms)) {
            wp_send_json_error('Keine zugeordnete Option gefunden.');
        }

        // Wenn eine Beziehung vorhanden ist, die get_wmd_options-Methode ausführen
        $htmlFetcher = new Get_WMD_Options();
        $innerHTML = $htmlFetcher->get_wmd_options($product_id);
        wp_send_json_success($innerHTML);
    }
}

// Initialisiere den AJAX-Handler
Canprinto_AJAX_Handler::init();
