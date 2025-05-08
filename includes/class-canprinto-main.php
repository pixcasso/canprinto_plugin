<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'class-canprinto-woocommerce.php';
require_once plugin_dir_path(__FILE__) . 'class-get-wmd-options.php';
require_once plugin_dir_path(__FILE__) . 'class-canprinto-ajax-handler.php';
require_once plugin_dir_path(__FILE__) . 'class-canprinto-image-upload.php';

class Canprinto_Main
{

    public function __construct()
    {
        // Hook für Plugin-Initialisierung nach WooCommerce
        add_action('plugins_loaded', array($this, 'init_hooks'));

        // CSS und JavaScript hinzufügen
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Optionen Wrapper und Hidden Field für die Produkt-ID hinzufügen
        add_action('woocommerce_before_add_to_cart_button', array($this, 'add_options_wrapper'));
    }

    // Initialisiere AJAX-Handler (kein zusätzlicher 'init'-Hook notwendig)
    public function init_hooks()
    {
        if (class_exists('WooCommerce')) {
            Canprinto_AJAX_Handler::init(); // Nur einmalige Initialisierung, wenn WooCommerce vorhanden ist
        }
    }

    // Skripte und Styles registrieren und einfügen
    public function enqueue_scripts()
    {
        // jQuery Skript hinzufügen
        wp_enqueue_script('canprinto-custom-js', plugin_dir_url(__FILE__) . '../js/canprinto.js', array('jquery'), '1.0.0', true);

        // AJAX-URL für JavaScript verfügbar machen
        wp_localize_script('canprinto-custom-js', 'canprinto_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));

        // CSS hinzufügen
        wp_enqueue_style('canprinto-custom-css', plugin_dir_url(__FILE__) . '../css/canprinto.css');
    }

    function enqueue_dropzone_scripts()
    {
        wp_enqueue_script('dropzone-js', get_template_directory_uri() . '/js/dropzone.js', array(), null, true);
        wp_enqueue_style('dropzone-css', get_template_directory_uri() . '/css/dropzone.css', array(), null);
    }


    // Optionen Wrapper und Hidden Field für die Produkt-ID hinzufügen
    public function add_options_wrapper()
    {
        global $product;
        if ($product && is_product()) {
            echo '<div id="cpo_options_wrapper"></div>';
            echo '<input type="hidden" id="product_id" name="product_id" value="' . esc_attr($product->get_id()) . '">';
            echo '<input type="hidden" id="cpo_custom_price" name="cpo_custom_price" value="">';
        }
    }

    function add_dropzone_form_before_cart_button() {
        ?>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dropzone" id="canprinto-dropzone">
            <input type="hidden" name="action" value="canprinto_upload_image">
        </form>

        <script>
            Dropzone.options.canprintoDropzone = {
                maxFilesize: 5, // 5 MB
                acceptedFiles: 'image/jpeg,image/png,image/gif',
                init: function() {
                    this.on("success", function(file, response) {
                        console.log("Bild erfolgreich hochgeladen:", response);
                    });
                    this.on("error", function(file, errorMessage) {
                        console.error("Fehler beim Hochladen des Bildes:", errorMessage);
                    });
                }
            };
        </script>
        <?php
    }

}

// Initialisiere das Plugin
new Canprinto_Main();
