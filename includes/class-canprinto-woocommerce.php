<?php
if (!defined('ABSPATH')) {
    exit; // Sicherheitscheck: Verhindert direkten Zugriff auf die Datei
}

class Canprinto_WooCommerce {

    public function __construct() {
        // WooCommerce-spezifische Hooks und Filter hinzufügen
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_custom_options_to_cart_item'), 100, 2);
        add_filter('woocommerce_get_item_data', array($this, 'display_custom_options_in_cart'), 100, 2);
        add_filter('woocommerce_get_cart_item_from_session', array($this, 'get_cart_item_from_session'), 100, 2);
        add_filter('woocommerce_cart_item_thumbnail', array($this, 'replace_cart_item_thumbnail'), 100, 3);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_custom_options_to_order'), 100, 4);
        add_action('woocommerce_admin_order_item_values', array($this, 'display_custom_options_in_admin_order'), 100, 3);
        add_action('woocommerce_before_calculate_totals', array($this,'canprinto_custom_price_override'), 50, 1);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'canprinto_adjust_price_on_checkout'), 10, 4);
    }

    // Funktion zum Ersetzen des Produktbildes im Warenkorb
    public function replace_cart_item_thumbnail($product_image, $cart_item, $cart_item_key) {
        if (isset($cart_item['cpo_mockup_url']) && !empty($cart_item['cpo_mockup_url'])) {
            $mockup_url = esc_url($cart_item['cpo_mockup_url']);
            $product_image = '<img src="' . $mockup_url . '" alt="Mockup" style="width: 60px; height: auto;">';
        }
        return $product_image;
    }

    // Funktion zum Hinzufügen von benutzerdefinierten Optionen zu Warenkorbartikeln
    public function add_custom_options_to_cart_item($cart_item_data, $product_id) {
        if (isset($_POST['cpo_custom_options'])) {
            $cart_item_data['cpo_custom_options'] = json_decode(stripslashes($_POST['cpo_custom_options']), true);
            $cart_item_data['unique_key'] = md5(microtime() . rand());

            // Debugging: Ausgabe der übergebenen benutzerdefinierten Optionen
            error_log('Übergebene benutzerdefinierte Optionen: ' . print_r($cart_item_data['cpo_custom_options'], true));
        }

        if (isset($_POST['cpo_custom_price'])) {
            $custom_price = str_replace(',', '.', sanitize_text_field($_POST['cpo_custom_price']));
            $cart_item_data['cpo_custom_price'] = $custom_price;
        }

        if (isset($_POST['cpo_mockup_url'])) {
            $cart_item_data['cpo_mockup_url'] = sanitize_text_field($_POST['cpo_mockup_url']);
        }

        return $cart_item_data;
    }

    // Funktion zum Anzeigen von benutzerdefinierten Optionen im Warenkorb
    public function display_custom_options_in_cart($item_data, $cart_item) {
        if (isset($cart_item['cpo_custom_options'])) {
            foreach ($cart_item['cpo_custom_options'] as $key => $value) {
                $item_data[] = array(
                    'name' => ucfirst($key),
                    'value' => $value,
                );
            }
        }

        return $item_data;
    }

    public function canprinto_custom_price_override($cart) {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['cpo_custom_price'])) {
                $custom_price = floatval($cart_item['cpo_custom_price']);
                if ($custom_price > 0) {
                    // Setze den Preis
                    $cart_item['data']->set_price($custom_price);
                }
            }
        }
    }

    // Funktion zum Abrufen von Warenkorbartikeln aus der Session
    public function get_cart_item_from_session($cart_item, $values) {
        if (isset($values['cpo_custom_options'])) {
            $cart_item['cpo_custom_options'] = $values['cpo_custom_options'];
            error_log('Session-Daten für benutzerdefinierte Optionen: ' . print_r($cart_item['cpo_custom_options'], true));
        }

        if (isset($values['cpo_custom_price'])) {
            $cart_item['cpo_custom_price'] = $values['cpo_custom_price'];
        }

        if (isset($values['cpo_mockup_url'])) {
            $cart_item['cpo_mockup_url'] = $values['cpo_mockup_url'];
        }

        return $cart_item;
    }

    // Bestell-Metadaten speichern
    public function save_custom_options_to_order($item, $cart_item_key, $values, $order) {
        if (isset($values['cpo_custom_options'])) {
            foreach ($values['cpo_custom_options'] as $key => $value) {
                $item->add_meta_data(sprintf(__('Option - %s', 'canprinto'), ucfirst($key)), $value, true);
            }
        }

        if (isset($values['cpo_custom_price'])) {
            $item->add_meta_data(__('Benutzerdefinierter Preis', 'canprinto'), wc_price($values['cpo_custom_price']), true);
        }

        if (isset($values['cpo_mockup_url'])) {
            $item->add_meta_data(__('Mockup URL', 'canprinto'), $values['cpo_mockup_url'], true);
        }
    }

    // Funktion zur Anpassung des Preises während des Checkouts
    public function canprinto_adjust_price_on_checkout($item, $cart_item_key, $values, $order) {
        if (isset($values['cpo_custom_price'])) {
            $custom_price = floatval($values['cpo_custom_price']);
            if ($custom_price > 0) {
                $item->set_subtotal($custom_price * $values['quantity']);
                $item->set_total($custom_price * $values['quantity']);
            }
        }
    }

    // Bestell-Metadaten im Backend anzeigen
    public function display_custom_options_in_admin_order($item_id, $item, $order) {
        $custom_options = wc_get_order_item_meta($item_id, '_cpo_custom_options', true);
        if (!empty($custom_options)) {
            foreach ($custom_options as $key => $value) {
                echo '<p><strong>' . esc_html(ucfirst($key)) . ':</strong> ' . esc_html($value) . '</p>';
            }
        }

        $mockup_url = wc_get_order_item_meta($item_id, 'Mockup URL', true);
        if (!empty($mockup_url)) {
            echo '<p><strong>' . __('Mockup', 'canprinto') . ':</strong><br><a href="' . esc_url($mockup_url) . '" target="_blank"><img src="' . esc_url($mockup_url) . '" alt="Mockup" style="width: 60px; height: auto;"></a></p>';
        }
    }
}

// Initialisiere die WooCommerce-spezifische Klasse
new Canprinto_WooCommerce();
