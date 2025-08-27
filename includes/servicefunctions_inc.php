<?php
/**
 * $Header$
 *
 * @package nexus
 * @subpackage functions
 */

namespace Bitweaver\Nexus;

/**
 * Nexus edit template service
 */
function nexus_content_edit( $pObject=null ) {
	global $gBitSmarty;

	$nexus = new Nexus();

	$nexusList = $nexus->getMenuList();
	$gBitSmarty->assign( 'nexusList', $nexusList );

	foreach( $nexusList as $menu ) {
		foreach( $menu['items'] as $item ) {
			if( !empty( $item['rsrc'] ) && $item['rsrc'] == $pObject->mContentId && $item['rsrc_type'] == 'content_id' ) {
				$gBitSmarty->assign( 'inNexusMenu', $menu );
				$gBitSmarty->assign( 'inNexusMenuItem', $item['item_id'] );
			}
		}
	}
}

/**
 * Nexus preview service
 * when we hit preview, we make the selections persistent
 */
function nexus_content_preview( $pObject=null ) {
}

/**
 * Nexus store service
 * store the content as part of an existing menu
 */
function nexus_content_store( $pObject, $pParamHash ) {
	global $gBitSystem, $gBitUser, $gBitSmarty;

	$nexus = new Nexus();

	if( !empty( $pParamHash['content_id'] ) && !empty( $pParamHash['nexus']['menu_id'] ) ) {
		$nexusHash['title'] = $pParamHash['content_store']['title'];
		$nexusHash['hint'] = !empty( $pParamHash['description'] ) ? $pParamHash['description'] : null;
		$nexusHash['menu_id'] = $pParamHash['nexus']['menu_id'];
		$nexusHash['after_ref_id'] = $pParamHash['nexus']['after_ref_id'];
		$nexusHash['rsrc'] = $pParamHash['content_id'];
		$nexusHash['rsrc_type'] = 'content_id';
		if( !$nexus->storeItem( $nexusHash ) ) {
			$gBitSystem->fatalError( "There was an error storing the item: ".\Bitweaver\vc( $nexus->mErrors ));
		}
		$nexus->load();
	} elseif( !empty( $pParamHash['nexus']['remove_item'] ) ) {
		$nexus->expungeItem( $pParamHash['nexus']['remove_item'] );
	}
}
