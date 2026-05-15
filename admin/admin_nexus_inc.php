<?php
use Bitweaver\KernelTools;
use Bitweaver\Nexus\Nexus;

$gNexus = new Nexus();
$gNexusSystem->scanAllPlugins( NEXUS_PKG_PATH.'plugins/' );

$feedback = [];

if( !empty( $_REQUEST['rewrite_cache'] ) ) {
	if( $gNexus->rewriteMenuCache() ) {
		$feedback['success'] = KernelTools::tra( 'The complete menu cache has been rewritten.' );
	}
}

if( !empty( $_REQUEST['pluginsave'] ) ) {
	$gNexusSystem->setActivePlugins( $_REQUEST['plugins'] );
	$feedback['success'] = KernelTools::tra( 'The plugins were successfully updated' );
}
$gBitSmarty->assign( 'feedback', $feedback );
?>
