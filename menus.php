<?php
/**
 * @author   xing <xing@synapse.plus.com>
 * @version  $Revision$
 * @package  nexus
 * @subpackage functions
 */

/**
* required setup
*/
require_once '../kernel/includes/setup_inc.php';
global $gBitSystem;
use Bitweaver\KernelTools;

include_once NEXUS_PKG_INCLUDE_PATH.'menu_lookup_inc.php';

$formfeedback = '';
$gBitSystem->verifyPermission( 'p_nexus_create_menus' );

if( isset( $_REQUEST['action'] ) ) {
	if( $_REQUEST['action'] == 'edit' ) {
		$gBitSmarty->assign( 'editMenu', $gNexus->getMenu() );
	}

	if( $_REQUEST['action'] == 'remove' ) {
		$formHash['remove'] = true;
		$formHash['menu_id'] = $menuId;
		$msgHash = [
			'label' => KernelTools::tra('Delete Menu'),
			'confirm_item' => $gNexus->mInfo['title'],
			'warning' => KernelTools::tra('Remove this menu including all menu items associated with it.'),
			'error' => KernelTools::tra('This cannot be undone!'),
		];
		$gBitSystem->confirmDialog( $formHash,$msgHash );
	}

	if( $_REQUEST['action'] == 'remove_dead' ) {
		if( $deadLinks = $gNexus->expungeDeadItems( $menuId ) ) {
			$deadHtml = '<ul>';
			foreach( $deadLinks as $dead ) {
				$deadHtml .= '<li>'.$dead.'</li>';
			}
			$deadHtml .= '</ul>';
			$formfeedback['warning'] = KernelTools::tra( 'Links that were dead and removed from ' ).": ".$gNexus->mInfo['title'].$deadHtml;
		} else {
			$formfeedback['success'] = KernelTools::tra( 'No dead links were found for this menu.' );
		}
	}

	if( $_REQUEST['action'] == 'convert_structure' ) {
		if( $gNexus->importStructure( $_REQUEST['structure_id'] ) ) {
			$formfeedback['success'] = KernelTools::tra( 'The structure was successfully imported as menu.' );
		} else {
			$gBitSystem->fatalError( KernelTools::tra("There was an error importing the structure ").\Bitweaver\vc( $gNexus->mErrors ));
		}
	}
}

if( isset( $_REQUEST['confirm'] ) ) {
	if( $gNexus->expungeMenu( $menuId ) ) {
		header ("Location: ".NEXUS_PKG_URL."menus.php");
		die;
	}
		$gBitSystem->fatalError( KernelTools::tra("There was an error deleting the menu ").\Bitweaver\vc( $gNexus->mErrors ));

}

if( isset( $_REQUEST['store_menu'] ) ) {
	$menu_id = $gNexus->storeMenu( $_REQUEST );
	// redirect to menu items page if this is a new menu
	if( empty( $menuId ) ) {
		header( 'Location: '.NEXUS_PKG_URL.'menu_items.php?menu_id='.$menu_id );
		die;
	}
	$gNexus->load();
	$formfeedback['success'] = KernelTools::tra( "The following menu was updated successfully" ).": ".$gNexus->mInfo['title'] ;
}

$gBitSmarty->assign( 'menuList', $menuList = $gNexus->getMenuList() );
$gBitSmarty->assign( 'formfeedback', $formfeedback );

// options only available if there is a top bar menu
if( is_file( TEMP_PKG_PATH.'nexus/modules/top_bar_inc.tpl' ) ) {
	// if the top bar is set and we don't need it, remove it.
	$nuke_top_bar = true;
	foreach( $menuList as $menu ) {
		if(  $menu['plugin_guid'] == NEXUS_PLUGIN_GUID_SUCKERFISH && $menu['menu_type'] == 'hor'  ) {
			$nuke_top_bar = false;
		}
	}

	if( !empty( $nuke_top_bar ) ) {
		unlink( TEMP_PKG_PATH.'nexus/modules/top_bar_inc.tpl' );
	}
}

$gBitSystem->setBrowserTitle( 'Nexus Menus' );
$gBitSystem->display( 'bitpackage:nexus/menus.tpl' , null, [ 'display_mode' => 'display' ]);
