<?php
declare(strict_types=1);

namespace Canprinto;

/**
 * Einstiegsklasse des Canprinto-Plugins.
 *
 * Alle WordPress-Hooks werden in der Methode {@see run()} registriert.
 * Zusätzliche Services kannst du später via Konstruktor-Injection
 * oder einen einfachen Service-Container einbinden.
 */
final class Plugin {

    /**
     * Boot-Methode – wird einmal aus canprinto.php aufgerufen.
     *
     * @return void
     */
    public function run(): void
    {
        // Registriert Custom Post Types, Taxonomien …
        add_action( 'init', [ $this, 'register_post_types' ] );

        // Lädt CSS/JS im Frontend
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // AJAX-Endpunkte
        add_action( 'wp_ajax_cpo_upload',        [ $this, 'handle_upload' ] );
        add_action( 'wp_ajax_nopriv_cpo_upload', [ $this, 'handle_upload' ] );
    }

    /* ---------------------------------------------------------------------
     *  Hook-Callbacks
     * -------------------------------------------------------------------*/

    public function register_post_types(): void
    {
        // Beispiel: Produkt-Entwurf
        register_post_type(
            'cpo_draft',
            [
                'public'      => false,
                'show_ui'     => true,
                'label'       => 'Druck-Entwürfe',
                'supports'    => [ 'title', 'thumbnail', 'custom-fields' ],
                'menu_icon'   => 'dashicons-format-image',
            ]
        );
    }

    public function enqueue_assets(): void
    {
        $base = plugin_dir_url( dirname( __DIR__ ) . '/canprinto.php' ); // Root ermitteln

        wp_enqueue_style(
            'canprinto-css',
            $base . 'assets/css/canprinto.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'canprinto-js',
            $base . 'assets/js/canprinto.js',
            [ 'jquery' ],
            '1.0.0',
            true
        );

        // AJAX-Daten ins JS schieben
        wp_localize_script(
            'canprinto-js',
            'cpo',
            [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'cpo_nonce' ),
            ]
        );
    }

    public function handle_upload(): void
    {
        check_ajax_referer( 'cpo_nonce', 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( 'Permission denied', 403 );
        }

        // Dateiupload-Logik hier …
        wp_send_json_success( [ 'message' => 'Upload ok' ] );
    }
}
