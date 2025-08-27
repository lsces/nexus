<?php
/**
 * @author   xing <xing@synapse.plus.com>
 * @version  $Revision$
 * @package  nexus
 * @subpackage functions
 */
global $gNexus;

use Bitweaver\Nexus\Nexus;
use Bitweaver\BitBase;

if( BitBase::verifyId( $_REQUEST['menu_id'] ?? 0 ) ) {
	$menuId = $_REQUEST['menu_id'];
	$gNexus = new Nexus( $menuId );
} else {
	$gNexus = new Nexus();
	$menuId = null;
}
$gNexus -> load();

$gBitSmarty->assign( 'gNexus', $gNexus );
$gBitSmarty->assign( 'menuId', $menuId );
