<?php
/**
 * Plugin Name: Content Blocks
 * Plugin URI:  http://rwsite.ru/
 * Description: Repeatable Content Blocks plugin
 * Version:     1.0.0
 * Text Domain: block
 * Domain Path: /languages/
 * Author:      Aleksey Tikhomirov
 * Author URI:  https://rwsite.ru/
 * License:     GPLv3 or later
*/


defined( 'ABSPATH' ) || die();

require_once 'includes/ContentBlock.php';
require_once 'includes/ContentBlockWidget.php';

$plugin = new ContentBlock(__FILE__);
$plugin->add_actions();

register_activation_hook( __FILE__, ContentBlock::class . '::plugin_activate' );
register_deactivation_hook( __FILE__, ContentBlock::class . '::plugin_deactivate' );

