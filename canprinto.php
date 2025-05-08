<?php
/*
Plugin Name: Canprinto
Plugin URI: https://sumopixel.com
Description: Entwickelt für den Service Canprinto.
Version: 1.0.0
Author: Pixcasso
Author URI: https://sumopixel.com
License: GPL2
Text Domain: canprinto
Domain Path: /languages
*/

// Verhindere den direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

// Hauptklasse des Plugins laden
require_once plugin_dir_path(__FILE__) . 'includes/class-canprinto-main.php';
