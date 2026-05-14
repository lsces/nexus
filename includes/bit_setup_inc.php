<?php
/**
 * @author   xing <xing@synapse.plus.com>
 * @version  $Revision$
 * @package  Nexus
 * @subpackage functions
 */
use Bitweaver\Nexus\NexusSystem;

global $gBitSystem, $gBitUser, $gLibertySystem, $gBitThemes;

$pRegisterHash = [
	'package_name' => 'nexus',
	'package_path' => dirname( dirname( __FILE__ ) ).'/',
	'service'      => LIBERTY_SERVICE_MENU,
];

// fix to quieten down VS Code which can't see the dynamic creation of these ...
define( 'NEXUS_PKG_NAME', $pRegisterHash['package_name'] );
define( 'NEXUS_PKG_URL', BIT_ROOT_URL . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'NEXUS_PKG_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'NEXUS_PKG_INCLUDE_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/');
define( 'NEXUS_PKG_CLASS_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/classes/');
define( 'NEXUS_PKG_ADMIN_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/admin/');

$gBitSystem->registerPackage( $pRegisterHash );

if( $gBitSystem->isPackageActive( 'nexus' ) ) {
	// load nexus plugins

	global $gNexusSystem;
	$gNexusSystem = new NexusSystem();

	if( !$gBitSystem->isFeatureActive( NEXUS_PKG_NAME.'_plugin_file_suckerfish' ) ) {
		$gNexusSystem->scanAllPlugins( NEXUS_PKG_PATH.'plugins/' );
	} else {
		$gNexusSystem->loadActivePlugins();
	}
	$gBitSmarty->assign( 'gNexusSystem', $gNexusSystem );

	// include service functions
	require_once NEXUS_PKG_INCLUDE_PATH.'servicefunctions_inc.php';

	$gLibertySystem->registerService( LIBERTY_SERVICE_MENU, NEXUS_PKG_NAME, [
		'content_store_function'   => 'nexus_content_store',
		'content_edit_function'    => 'nexus_content_edit',
		'content_preview_function' => 'nexus_content_preview',
		'content_edit_tab_tpl'     => 'bitpackage:nexus/insert_menu_item_inc.tpl',
	] );

	if( $gBitUser->hasPermission( 'p_nexus_create_menus' ) ) {
		$menuHash = [
			'package_name'  => NEXUS_PKG_NAME,
			'index_url'     => NEXUS_PKG_URL.'index.php',
			'menu_template' => 'bitpackage:nexus/menu_nexus.tpl',
		];
		$gBitSystem->registerAppMenu( $menuHash );
	}

	if( is_dir( TEMP_PKG_PATH.'nexus/modules/' ) ) {
		// make sure the template knows about the custom top bar
		if( is_file( TEMP_PKG_PATH.'nexus/modules/top_bar_inc.tpl' ) ) {
			$gBitSmarty->assign( 'use_custom_top_bar', true );
		}
	}

	$gBitThemes->loadCss( NEXUS_PKG_PATH.'css/nexus.css' );
}
