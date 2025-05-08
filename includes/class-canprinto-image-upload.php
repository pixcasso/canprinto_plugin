<?php
if (!defined('ABSPATH')) {
    exit;
}

class Canprinto_Image_Upload {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_dropzone_script']);
        add_action('wp_ajax_canprinto_upload_image', [$this, 'handle_image_upload']);
        add_action('wp_ajax_nopriv_canprinto_upload_image', [$this, 'handle_image_upload']);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'display_upload_form']);
        add_action('wp_ajax_canprinto_delete_image', [$this, 'handle_image_delete']);
        add_action('wp_ajax_nopriv_canprinto_delete_image', [$this, 'handle_image_delete']);
    }

    public function enqueue_dropzone_script() {
        wp_enqueue_script('dropzone-js', plugin_dir_url(__FILE__) . '../js/dropzone-min.js', [], '5.9.3', true);
        wp_enqueue_style('dropzone-css', plugin_dir_url(__FILE__) . '../css/dropzone.css', [], '5.9.3');
    }

    // Formular für Dropzone anzeigen
    public function display_upload_form() {
        ?>
        <form action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" class="dropzone" id="canprinto-dropzone">
            <input type="hidden" name="action" value="canprinto_upload_image">
            <input type="hidden" name="canprinto_image_nonce" value="<?php echo wp_create_nonce('canprinto_image_upload'); ?>">
        </form>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (typeof Dropzone !== 'undefined') {
                    Dropzone.options.canprintoDropzone = {
                        maxFilesize: 5, // 5 MB
                        acceptedFiles: 'image/jpeg,image/png,image/gif',
                        addRemoveLinks: true,
                        init: function() {
                            this.on("success", function(file, response) {
                                if (response && response.data && response.data.url) {
                                    console.log("Bild erfolgreich hochgeladen:", response);
                                    file.upload.url = response.data.url;
                                } else {
                                    console.error("Fehlerhafte Antwort beim Hochladen des Bildes:", response);
                                }
                            });
                            this.on("error", function(file, errorMessage) {
                                console.error("Fehler beim Hochladen des Bildes:", errorMessage);
                            });
                            this.on("removedfile", function(file) {
                                if (file.upload && file.upload.url) {
                                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded',
                                        },
                                        body: new URLSearchParams({
                                            'action': 'canprinto_delete_image',
                                            'canprinto_image_nonce': '<?php echo wp_create_nonce('canprinto_image_delete'); ?>',
                                            'file_url': file.upload.url
                                        })
                                    }).then(response => response.json())
                                      .then(data => {
                                          if (data.success) {
                                              console.log("Bild erfolgreich gelöscht:", data);
                                          } else {
                                              console.error("Fehler beim Löschen des Bildes:", data);
                                          }
                                      }).catch(error => {
                                          console.error("Fehler beim Senden der Löschanforderung:", error);
                                      });
                                } else {
                                    console.error("Keine URL für die zu löschende Datei vorhanden.");
                                }
                            });
                        }
                    };
                } else {
                    console.error("Dropzone ist nicht definiert. Überprüfe, ob das Dropzone-Skript korrekt eingebunden wurde.");
                }
            });
        </script>
        <?php
    }

    // Bild-Upload verarbeiten
    public function handle_image_upload() {
        // Nonce-Überprüfung für Sicherheitszwecke
        if (!isset($_POST['canprinto_image_nonce']) || !wp_verify_nonce($_POST['canprinto_image_nonce'], 'canprinto_image_upload')) {
            wp_send_json_error('Ungültige Anfrage.');
            return;
        }

        if (!isset($_FILES['file'])) {
            wp_send_json_error('Keine Datei hochgeladen.');
            return;
        }

        $file = $_FILES['file'];

        // Überprüfe auf Upload-Fehler
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('Fehler beim Hochladen der Datei.');
            return;
        }

        // Datei zu WordPress hochladen
        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            wp_send_json_error($upload['error']);
        } else {
            // Originaldatei behalten und bearbeitetes Bild erstellen
            $original_url = $upload['url'];
            $file_path = $upload['file'];

            // Erstelle eine Kopie und bearbeite sie mit Imagick
            try {
                $imagick = new Imagick($file_path);
                
                // Erstelle eine neue UUID für das Mockup
                $uuid = wp_generate_uuid4();

                // Erstelle ein neues Bild mit hellem Graublau-Hintergrund
                $canvas = new Imagick();
                $canvas->newImage(1000, 1000, new ImagickPixel('rgb(173, 216, 230)')); // Helles Graublau
                $canvas->setImageFormat('jpeg');
                $canvas->setImageCompressionQuality(60);
                
                // Originalbild skalieren und bearbeiten
                $imagick->resizeImage(700, 700, Imagick::FILTER_LANCZOS, 1, true);
                $imagick->roundCorners(5, 5);
                
                // Zentriere das Originalbild auf der Leinwand
                $x = round(($canvas->getImageWidth() - $imagick->getImageWidth()) / 2);
                $y = round(($canvas->getImageHeight() - $imagick->getImageHeight()) / 2);
                
                // Füge das Originalbild zur Leinwand hinzu
                $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, $x, $y);

                // Speichern der bearbeiteten Datei
                $mockup_path = str_replace(basename($file_path), 'mockup_' . $uuid . '.jpg', $file_path);
                $canvas->writeImage($mockup_path);
                $mockup_url = str_replace(basename($original_url), 'mockup_' . $uuid . '.jpg', $original_url);

                wp_send_json_success(['url' => $original_url, 'mockup_url' => $mockup_url]);
            } catch (Exception $e) {
                wp_send_json_error('Fehler beim Bearbeiten der Datei: ' . $e->getMessage());
            }
        }
    }

    // Bild löschen verarbeiten
    public function handle_image_delete() {
        // Nonce-Überprüfung für Sicherheitszwecke
        if (!isset($_POST['canprinto_image_nonce']) || !wp_verify_nonce($_POST['canprinto_image_nonce'], 'canprinto_image_delete')) {
            wp_send_json_error('Ungültige Anfrage.');
            return;
        }

        if (!isset($_POST['file_url'])) {
            wp_send_json_error('Keine URL für die zu löschende Datei übergeben.');
            return;
        }

        $file_path = str_replace(wp_get_upload_dir()['baseurl'], wp_get_upload_dir()['basedir'], $_POST['file_url']);

        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                wp_send_json_success('Datei erfolgreich gelöscht.');
            } else {
                wp_send_json_error('Fehler beim Löschen der Datei.');
            }
        } else {
            wp_send_json_error('Datei nicht gefunden.');
        }
    }
}

// Initialisiere das Plugin
new Canprinto_Image_Upload();
