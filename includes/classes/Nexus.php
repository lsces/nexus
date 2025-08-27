<?php
/**
* Nexus base class
*
* @abstract
* @author   xing <xing@synapse.plus.com>
* @version  $Revision$
* @package  nexus
*/

/**
 * required setup
 */
namespace Bitweaver\Nexus;
use Bitweaver\BitBase;
use Bitweaver\Fisheye\FisheyeGallery;
use Bitweaver\Liberty\LibertyStructure;
use Bitweaver\KernelTools;
use Bitweaver\Themes\BitweaverExtension;

/**
 * @package  nexus
 */
class Nexus extends NexusSystem {
	/**
	* Primary key for the menu
	*/
	var $mMenuId;

	/**
	* Initialisation of this class
	*/
	public function Nexus( $pMenuId=null ) {
		parent::__construct();
		$this->mMenuId = $pMenuId;
		// if the cache folder doesn't exist yet, create it
		if( !is_dir( TEMP_PKG_PATH.NEXUS_PKG_NAME.'/modules' ) ) {
			KernelTools::mkdir_p( TEMP_PKG_PATH.NEXUS_PKG_NAME.'/modules' );
		}
	}

	/**
	* Load the menu
	*/
	public function load() {
		if( BitBase::verifyId( $this->mMenuId ) ) {
			$this->mInfo = $this->getMenu( $this->mMenuId );
			$this->mInfo['items'] = $this->getItemList( $this->mMenuId );
			$this->mInfo['tree'] = $this->createMenuTree( $this->mInfo['items'] );
		}
		return count( $this->mInfo );
	}

	/**
	* Get menu information from database
	* @param $pMenuId menu id of the menu we want information from
	*/
	public function getMenu( $pMenuId=null ) {
		$ret = [];
		if( !BitBase::verifyId( $pMenuId ) && $this->isValid() ) {
			$pMenuId = $this->mMenuId;
		}

		$bindVars = [];
		$query = 'SELECT nm.* FROM `'.BIT_DB_PREFIX.'nexus_menus` nm';
		if( is_numeric( $pMenuId ) ) {
			$query .= ' WHERE nm.`menu_id`=?';
			$bindVars = array( $pMenuId );
		}
		if( $result = $this->mDb->query( $query, $bindVars ) ) {
			$ret = $result->fields;
			$ret['cache']['file'] = 'mod_'.preg_replace( "/ /", "_", $ret['title'] ).'_'.$pMenuId.'.tpl';
			$ret['cache']['path'] = TEMP_PKG_PATH.NEXUS_PKG_NAME."/modules/".$ret['cache']['file'];
			$ret['cache']['module'] = "bitpackage:temp/nexus/".$ret['cache']['file'];
		}
		return $ret;
	}

	/**
	* Get menu information from database
	* @param $pMenuId menu id of the menu we want information from
	*/
	public function getMenuList( $pFindString=null, $pSortMode=null, $pOffset=null, $pMaxRows=null ) {
		$bindVars = [];
		$mid = '';
		if( $pFindString ) {
			$mid .= " WHERE UPPER(nm.`title`) LIKE ? ";
			$bindVars[] = '%'.strtoupper( $pFindString ).'%';
		}
		if( $pSortMode ) {
			$mid .= " ORDER BY ".$this->mDb->convertSortmode( $pSortMode )." ";
		}

		$query = 'SELECT nm.`menu_id` FROM `'.BIT_DB_PREFIX.'nexus_menus` nm'.$mid;
		$result = $pMaxRows && is_numeric( $pMaxRows )
			? $this->mDb->query( $query, $bindVars, $pOffset, $pMaxRows )
			: $this->mDb->query( $query, $bindVars );

		$menuIds = $result->getRows();
		$menus = [];
		foreach( $menuIds as $menuId ) {
			$tmpMenu = new Nexus( $menuId['menu_id'] );
			$tmpMenu->load();
			$menus[] = $tmpMenu->mInfo;
		}

		return $menus;
	}

	/**
	* Create usable menu tree
	* @param $pMenuHash full menu as supplied by '$this->getItemList( $pMenuId );'
	* @param $pStripped if set to true, removes all permissions, user isn't part of
	* @return array menu with all menu items sorted with first and last items of each 'level' marked. items that contain siblings are marked with 'head' = true;
	*/
	public function createMenuTree( $pMenuHash, $pStripped=false, $parent_id=0, $pForceBuild=false ) {
		$ret = [];
		if( $pForceBuild || $this->isValid() && empty( $this->mInfo['tree'] )) {
			if( $pStripped && $parent_id == 0 ) {
				$pMenuHash = $this->checkUserPermission( $pMenuHash );
			}
			// get all child menu items for this item_id
			$children = $this->getChildItems( $pMenuHash, $parent_id );
			$pos = 1;
			$row_max = count( $children );
			foreach( $children as $item ) {
				$aux = $item;
				$aux['first']       = $pos == 1;
				$aux['last']        = false;
				$aux['head']        = false;
				$ret[] = $aux;
				//Recursively add any children
				$subs = $this->createMenuTree( $pMenuHash, $pStripped, $item['item_id'], true );
				if( !empty( $subs ) ) {
					// mark items that have children
					$row_last = count( $ret );
					$ret[$row_last - 1]['head'] = true;
					$ret = array_merge( $ret, $subs );
				}
				if( $pos == $row_max ) {
					if( BitBase::verifyId( $item['parent_id'] ) ) {
						$tmpItem = $this->getItemList( null, $item['parent_id'] );
						$aux = $tmpItem[$item['parent_id']];
					} else {
						$aux['item_id'] = $item['item_id'];
					}
					$aux['first'] = false;
					$aux['last']  = true;
					$ret[] = $aux;
				}
				$pos++;
			}
		} else {
			$ret = $this->mInfo['tree'];
		}
		return $ret;
	}

	/**
	* Strip out all items a user doesn't have permission to view
	* @param $pMenuHash full menu as supplied by '$this->getItemList( $pMenuId );'
	* @return array menu containing only items user is allowed to view
	*/
	public function checkUserPermission( $pMenuHash ) {
		global $gBitUser;
		$ret = [];
		foreach( $pMenuHash as $item ) {
			if( !empty( $item['perm'] ) ) {
				if( $gBitUser->hasPermission( $item['perm'] ) ) {
					$ret[] = $item;
				}
			} else {
				$ret[] = $item;
			}
		}
		return $ret;
	}

	/**
	* Get all items in $pMenuHash that have a given parent_id
	* @param $pMenuHash full menu as supplied by '$this->getItemList( $pMenuId );'
	* @return array of items with a given parent_id
	*/
	public function getChildItems( $pMenuHash, $parent_id=0 ) {
		$ret = [];
		foreach( $pMenuHash as $item ) {
			if( $item['parent_id'] == $parent_id ) {
				$ret[] = $item;
			}
		}
		return $ret;
	}

	/**
	* Validate that a menu is being loaded and present
	* @return true if all is ok
	*/
	public function isValid() {
		return BitBase::verifyId( $this->mMenuId );
	}

	/**
	* Check if all required items are present for menu creation / insertion
	* @return number of errors encountered
	*/
	public function verifyMenu( &$pParamHash ) {
		if( empty( $pParamHash['title'] ) ) {
			$this->mErrors['verify_title'] = KernelTools::tra( 'Could not store menu because no title was given.' );
		}
		if( empty( $pParamHash['description'] ) ) {
			$pParamHash['description'] = null;
		}
		// set the default plugin_guid to suckerfish menus
		if( empty( $pParamHash['plugin_guid'] ) ) {
			$pParamHash['plugin_guid'] = NEXUS_PLUGIN_GUID_SUCKERFISH;
		}
		$type_name = 'type_' . $pParamHash['plugin_guid'];
		$pParamHash['menu_type'] = $pParamHash[$type_name] ?? 'nor';

		if( empty( $pParamHash['editable'][0] ) || !is_numeric( $pParamHash['editable'][0] ) ) {
			$pParamHash['editable'][0] = 0;
		}
		$pParamHash['editable'] = $pParamHash['editable'][0];
		return count( $this->mErrors ) == 0;
	}

	/**
	* Store menu in db
	* @param array hash of values
	* @var int menu_id if set, will update given menu - if not set, we create a new entry in the db
	* @var string title title of menu
	* @var string description description of menu
	* @var string menu_type type of menu
	* @var bool editable if menu is editable by other users - takes 0 or 1
	* @return int|bool new menu menu_id or false if not created
	*/
	public function storeMenu( &$pParamHash ) {
		$ret = false;
		if( $this->verifyMenu( $pParamHash ) ) {
			$this->mDb->StartTrans();
			if( !BitBase::verifyId( $pParamHash['menu_id'] ?? 0 ) ) {
				$query = "INSERT INTO `".BIT_DB_PREFIX."nexus_menus`( `title`,`description`,`menu_type`,`plugin_guid`,`editable` ) VALUES(?,?,?,?,?)";
				$result = $this->mDb->query( $query, array( $pParamHash['title'], $pParamHash['description'], $pParamHash['menu_type'], $pParamHash['plugin_guid'], $pParamHash['editable'] ) );
				$query = "SELECT MAX(`menu_id`) FROM `".BIT_DB_PREFIX."nexus_menus`";
				$ret = $this->mDb->getOne( $query, [] );
			} else {
				$query = "UPDATE `".BIT_DB_PREFIX."nexus_menus` SET `title`=?,`description`=?,`menu_type`=?,`plugin_guid`=?,`editable`=? WHERE `".BIT_DB_PREFIX."nexus_menus`.`menu_id`=?";
				$result = $this->mDb->query( $query, array( $pParamHash['title'], $pParamHash['description'], $pParamHash['menu_type'], $pParamHash['plugin_guid'], $pParamHash['editable'], $pParamHash['menu_id'] ) );
				$ret = $pParamHash['menu_id'];
			}
			$this->mDb->CompleteTrans();
			$this->writeMenuCache( $ret );
		} else {
			error_log( "Error storing menu: " . \Bitweaver\vc($this->mErrors) );
		}
		return $ret;
	}

	/**
	* Delete menu and associated menu items from db
	* @return number of errors encountered
	*/
	public function expungeMenu( $pMenuId ) {
		// first off, remove the menu from the layout
		global $gBitThemes, $gBitSystem;
		$menu = $this->getMenu( $this->mMenuId );
		$gBitThemes->unassignModule( $menu['cache']['module'], ROOT_USER_ID );

		// delete menu items
		$query = "DELETE FROM `".BIT_DB_PREFIX."nexus_menu_items` WHERE `menu_id`=?";
		$this->mDb->query( $query, array( $pMenuId ) );

		// delete menu
		$query = "DELETE FROM `".BIT_DB_PREFIX."nexus_menus` WHERE `menu_id`=?";
		$this->mDb->query( $query, array( $pMenuId ) );

		// rewrite the entire cache, just to make sure everything is in order
		$this->rewriteMenuCache();

		return count( $this->mErrors ) == 0;
	}

	/**
	* Get menu items from the database
	* @param $pItemId ID of menu item to get. if set, we only get this item.
	* @param $pMenuId ID of menu to get
	* @return array all menu items with a given menu ID
	*/
	public function getItemList( $pMenuId=null, $pItemId=null ) {
		$bindVars = [];
		$ret = [];
		$query = 'SELECT nmi.* FROM `'.BIT_DB_PREFIX.'nexus_menu_items` nmi';
		if( is_numeric( $pItemId ) ) {
			$query .= ' WHERE nmi.`item_id`=?';
			$bindVars = array( $pItemId );
		} elseif( is_numeric( $pMenuId ) ) {
			$query .= ' WHERE nmi.`menu_id`=?';
			$bindVars = array( $pMenuId );
		}
		$query .= ' ORDER BY nmi.`pos`';
		$result = $this->mDb->query( $query, $bindVars );
		while( !$result->EOF ) {
			$item = $result->fields;
			$item['display_url'] = $this->printUrl( $item );
			$ret[$item['item_id']] = $item;
			$result->MoveNext();
		}
		// this version of the loop inserts the submenu at the point of choice
		foreach( $ret as $item ) {
			if( $item['rsrc_type'] == 'menu_id' ) {
				$tmp = $this->getItemList( $item['rsrc'] );
				foreach( $tmp as $i ) {
					if( $i['parent_id'] == 0 ) {
						$tmp[$i['item_id']]['parent_id'] = $item['item_id'];
					}
					// pass all items on to ret
					$ret[$i['item_id']] = $tmp[$i['item_id']];
				}
			}
		}
		return $ret;
	}

	/**
	* Create the correct url for a given item
	* @param $pItemHash complete item hash
	* @return string url
	*/
	public function printUrl( $pItemHash ) {
		global $gLibertySystem, $gBitSystem;
		$contentTypes = $gLibertySystem->mContentTypes;
		$ret = null;

		if( isset( $pItemHash['rsrc'] ) && isset( $pItemHash['rsrc_type'] )) {
			switch( $pItemHash['rsrc_type'] ) {
				case 'external':
					$ret .= $pItemHash['rsrc'];
					break;
				case 'internal':
					// annoying duplicate BIT_ROOT_URL removal and then adding is for people who add the leading section of the URL as well.
					$ret .= str_replace( "//", "/", BIT_ROOT_URL.str_replace( rtrim( BIT_ROOT_URL, '/' ), "", $pItemHash['rsrc'] ));
					break;
				case 'content_id':
					// create *one* object for each object *type* to  call virtual methods.
					$row = $this->mDb->getRow( "SELECT `title`,`content_id`,`content_type_guid` FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_id`=?", array( $pItemHash['rsrc'] ));
					if( !empty( $row['content_type_guid'] )) {
						$type = &$contentTypes[$row['content_type_guid']];

						if( empty( $type['content_object'] )) {
							include_once $gBitSystem->mPackages[$type['handler_package']]['path'].$type['handler_file'];
							$type['content_object'] = new $type['handler_class']();
						}

						$type['content_object']->mContentId = $row['content_id'];
						$type['content_object']->load();
						$ret = $type['content_object']->getDisplayUrl();
					}
					break;
				case 'structure_id':
					$ret .= BIT_ROOT_URL.'index.php?structure_id='.$pItemHash['rsrc'];
					break;
			}
		}
		return $ret;
	}

	/**
	* Verify rsrc handed to us and specify what type it is
	* @return void fixed rsrc and corresponding rsrc_type
	*/
	public function verifyRsrc( &$pParamHash ) {
		// if we have something like http:// or ftp:// in the url, we know it's external
		if( preg_match( "/^([a-zA-Z]{2,8}:\/\/)/i", $pParamHash['rsrc'] ) ) {
			$pParamHash['rsrc_type'] = 'external';
		} elseif( substr( $pParamHash['rsrc'], 0, 1 ) == '/' && substr( $pParamHash['rsrc'], 0, strlen( BIT_ROOT_URL )) != BIT_ROOT_URL ) {
			// if the first character is a / and it doesn't match BIT_ROOT_URL, we know it's external even though it's in the same domain
			$pParamHash['rsrc_type'] = 'external';
		} elseif( is_numeric( $pParamHash['rsrc'] ) ) {
			// if we have a numeric rsrc, we know it's either a content_id or a structure_id
			// if the resource type is numeric but we don't know what type it is, assume that it's a content_id
			if( empty( $pParamHash['rsrc_type'] ) ) {
				$pParamHash['rsrc_type'] = 'content_id';
			}
		} else {
			// any other URL will be considered as internal
			$pParamHash['rsrc_type'] = 'internal';
		}
	}

	/**
	* Verify if a given menu item contains all required information and prepare menu_items table for item insertion.
	* @return number of errors encountered
	*/
	public function verifyItem( &$pParamHash ) {
		if( empty( $pParamHash['hint'] ) )		{ $pParamHash['hint'] = null; }
		if( empty( $pParamHash['perm'] ) )		{ $pParamHash['perm'] = null; }
		if( empty( $pParamHash['title'] ) ) {
			$this->mErrors['verify_item_title'] = KernelTools::tra( 'Could not store menu item. No item title was given.' );
		}
		if( !BitBase::verifyId( $pParamHash['menu_id'] ?? 0 ) ) {
			$this->mErrors['verify_menu_id'] = KernelTools::tra( 'Could not store menu item. Invalid menu id. Menu id ' ).': '.$pParamHash['menu_id'];
		} else {
			$this->mDb->StartTrans();
			if( !BitBase::verifyId( $pParamHash['parent_id'] ?? 0 ) ) {
				$pParamHash['parent_id'] = 0;
				// if no parent_id is not known, but we have an after_ref_id, we use that to work out the parent_id
				if( BitBase::verifyId( $pParamHash['after_ref_id'] ?? 0 ) ) {
					$pParamHash['parent_id'] = $this->mDb->getOne("SELECT `parent_id` FROM `".BIT_DB_PREFIX."nexus_menu_items` WHERE `item_id`=?", array( (int)$pParamHash['after_ref_id'] ) );
				}
			}
			$pParamHash['max'] = 0;
			if( BitBase::verifyId( $pParamHash['after_ref_id'] ?? 0 ) ) {
				$pParamHash['max'] = $this->mDb->getOne("SELECT `pos` FROM `".BIT_DB_PREFIX."nexus_menu_items` WHERE `item_id`=?", array( (int)$pParamHash['after_ref_id'] ) );
				if( $pParamHash['max'] > 0 ) {
					//If max is 5 then we are inserting after position 5 so we'll insert 5 and move all the others
					$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `pos`=`pos`+1 WHERE `pos`>? AND `parent_id`=?";
					$result = $this->mDb->query( $query, array( (int)$pParamHash['max'], (int)$pParamHash['parent_id'] ) );
				}
			}
			$this->mDb->CompleteTrans();
			$pParamHash['max']++;
			// if we get passed the position of where the item is to go, we pass it to 'max' -- used for importStructure()
			if( BitBase::verifyId( $pParamHash['pos'] ?? 0 ) && !BitBase::verifyId( $pParamHash['after_ref_id'] ?? 0 ) ) {
				$pParamHash['max'] = $pParamHash['pos'];
			}
			// work out what type of rsrc was passed in and fix
			if( !empty( $pParamHash['rsrc'] ) ) {
				$this->verifyRsrc( $pParamHash );
			} else {
				$pParamHash['rsrc'] = null;
				$pParamHash['rsrc_type'] = null;
			}
		}
		return count( $this->mErrors ) == 0;
	}

	/**
	* Create a menu item entry
	* @param array hash of values
	* @var int parent_id The parent entry to add this to. If null, create new structure.
	* @var int after_ref_id The entry to add this one after. If null, put it in position 0.
	* @var string title The wiki page to reference
	* @return int|bool the new entries item_id or false if not created.
	*/
	public function storeItem( &$pParamHash ) {
		$ret = false;
		if ( $this->verifyItem( $pParamHash ) ) {
			$this->mDb->StartTrans();
			if( !BitBase::verifyId( $pParamHash['item_id'] ?? 0 ) ) {
				$query = "INSERT INTO `".BIT_DB_PREFIX."nexus_menu_items`( `menu_id`,`parent_id`,`pos`,`title`,`hint`,`rsrc`,`rsrc_type`,`perm` ) VALUES( ?,?,?,?,?,?,?,? )";
				$result = $this->mDb->query( $query, array( (int)$pParamHash['menu_id'], (int)$pParamHash['parent_id'], (int)$pParamHash['max'], $pParamHash['title'], $pParamHash['hint'], $pParamHash['rsrc'], $pParamHash['rsrc_type'], $pParamHash['perm'] ) );
				$query = "SELECT MAX(`item_id`) FROM `".BIT_DB_PREFIX."nexus_menu_items`";
				$ret = $this->mDb->getOne( $query, [] );
			} else {
				$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `title`=?, `hint`=?, `rsrc`=?, `rsrc_type`=?, `perm`=? WHERE `item_id`=?";
				$result = $this->mDb->query( $query, array( $pParamHash['title'], $pParamHash['hint'], $pParamHash['rsrc'], $pParamHash['rsrc_type'], $pParamHash['perm'], $pParamHash['item_id'] ) );
				$ret = $pParamHash['item_id'];
			}
			$this->mDb->CompleteTrans();
			$this->rewriteMenuCache();
		} else {
			return count( $this->mErrors ) == 0;
		}
		return $ret;
	}

	/**
	* Delete item
	* @param $pItemId item to be removed
	* @return bool deleted item information
	*/
	public function expungeItem( $pItemId=null, $pWriteCache=true ) {
		if( BitBase::verifyId( $pItemId ) ) {
			// get full information of item that we are removing
			$remItem = $this->getItemList( null, $pItemId );
			if( BitBase::verifyId( $remItem[$pItemId]['item_id'] ) ) {
				$remItem = $remItem[$pItemId];
				$this->mDb->StartTrans();
				// get all items that are on the same level
				$query = "SELECT * FROM `".BIT_DB_PREFIX."nexus_menu_items` WHERE `parent_id`=? ORDER BY `pos`";
				$result = $this->mDb->query( $query, array( $pItemId ) );
				// this value is needed to correclty position items that are moved up a level
				$pos_count = 0;
				// first we move children up one level
				while( !$result->EOF ) {
					$item = $result->fields;
					//Make a space for the item after its parent
					$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `pos`=`pos`+1 WHERE `pos`>?+".$pos_count." AND `parent_id`=? AND `menu_id`=?";
					$res = $this->mDb->query( $query, array( (int)$remItem['pos'], (int)$remItem['parent_id'], (int)$remItem['menu_id'] ) );
					// increase insertion count here
					$pos_count++;
					// move item up one level
					$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `parent_id`=?, `pos`=?+".$pos_count." WHERE `item_id`=?";
					$this->mDb->query( $query, array( (int)$remItem['parent_id'], (int)$remItem['pos'], (int)$item['item_id'] ) );
					$result->MoveNext();
				}
				// all items below remItem have to be shifted up by one
				$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `pos`=`pos`-1 WHERE `pos`>? AND `parent_id`=? AND `menu_id`=?";
				$this->mDb->query( $query, array( (int)$remItem['pos'], (int)$remItem['parent_id'], (int)$remItem['menu_id'] ) );
				// finally, we are ready do delete remItem
				$query = "DELETE FROM `".BIT_DB_PREFIX."nexus_menu_items` WHERE `item_id`=?";
				$result = $this->mDb->query( $query, array( $pItemId ) );
				$this->mDb->CompleteTrans();
				if( $pWriteCache ) {
					$this->writeMenuCache( $remItem['menu_id'] );
				}
				return $remItem;
			} else {
				$this->mErrors['remove_item'] = KernelTools::tra( "There was a problem trying to remove the menu item." );
				return false;
			}
		} else {
			$this->mErrors['remove_item_id'] = KernelTools::tra( "The menu item could not be removed because no valid item id was given." );
			return false;
		}
	}

	/**
	* Remove items to content that has been removed
	* @param $pMenuId menu from which we want to remove dead links
	* @return array titles of links removed
	* @TODO check for items in structures. if they don't exist there, replace them with the appropriate content id
	*/
	public function expungeDeadItems( $pMenuId=null ) {
		if( isset( $pMenuId ) && is_numeric( $pMenuId ) ) {
			// get $contentList
			include_once LIBERTY_PKG_INCLUDE_PATH.'get_content_list_inc.php';
			foreach( $contentList as $contentItem ) {
				$contentIds[] = $contentItem['content_id'];
			}
			$deathList = [];
			foreach( $this->mInfo['items'] as $item ) {
				if( $item['rsrc_type'] == 'content_id' && !in_array( $item['rsrc'], $contentIds ) ) {
					$this->expungeItem( $item['item_id'], false );
					$deathList[] = $item['title'];
				}
			}
			$this->writeMenuCache( $pMenuId );
		}
		return $deathList;
	}

	/**
	* Move item west
	* @param $pItemId item id of item to be moved
	*/
	public function moveItemWest( $pItemId=null ) {
		if( $this->isValid() && $pItemId ) {
			// pass current item into managable array
			$item = $this->mInfo["items"][$pItemId];
			// if there is a parent and the parent isnt the menu root item.
			if( $item['parent_id'] > 0 ) {
				$parentItem = $this->getItemList( $this->mMenuId, (int)$item["parent_id"] );
				$parentItem = $parentItem[$item["parent_id"]];
				$this->mDb->StartTrans();
				if( !BitBase::verifyId( $parentItem["parent_id"] ) ) {
					$max_row = $this->mDb->getOne("SELECT `pos` FROM `".BIT_DB_PREFIX."nexus_menu_items` WHERE `item_id`=?", array( $item['parent_id'] ) );
					$parent_item['pos'] = $max_row;
					$parent_item['parent_id'] = 0;
				}
				//Make a space for the item after its parent
				$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `pos`=`pos`+1 WHERE `pos`>? AND `parent_id`=?";
				$this->mDb->query( $query, array( (int)$parentItem["pos"], (int)$parentItem["parent_id"] ) );
				//Move the item up one level
				$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `parent_id`=?, `pos`=(? + 1) WHERE `item_id`=?";
				$this->mDb->query($query, array( (int)$parentItem["parent_id"], (int)$parentItem["pos"], $pItemId ) );
				$this->mDb->CompleteTrans();
				$this->writeMenuCache( $item['menu_id'] );
			}
		}
	}

	/**
	* Move item east
	* @param $pItemId item id of item to be moved
	*/
	public function moveItemEast( $pItemId=null ) {
		if( $this->isValid() && $pItemId ) {
			// pass current item into managable array
			$item = $this->mInfo["items"][$pItemId];
			$this->mDb->StartTrans();
			$query = "SELECT `item_id`, `pos` FROM `".BIT_DB_PREFIX."nexus_menu_items` WHERE `pos`<? AND `parent_id`=? AND `menu_id`=? ORDER BY `pos` DESC";
			$result = $this->mDb->query( $query, array( (int)$item["pos"], (int)$item["parent_id"], (int)$item["menu_id"] ) );
			if( $previous = $result->fetchRow() ) {
				//Get last child item for previous sibling
				$query = "SELECT `pos` FROM `".BIT_DB_PREFIX."nexus_menu_items` WHERE `parent_id`=? AND `menu_id`=? ORDER BY `pos` DESC";
				$result = $this->mDb->query( $query, array( (int)$previous["item_id"], (int)$item["menu_id"] ) );
				$pos = ( $res = $result->fetchRow() ) ? $res["pos"] : 0;
				$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `parent_id`=?, `pos`=(? + 1) WHERE `item_id`=?";
				$this->mDb->query( $query, array( (int)$previous["item_id"], (int)$pos, (int)$item["item_id"] ) );
				//Move items up below that had previous parent and pos
				$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `pos`=`pos`-1 WHERE `pos`>? AND `parent_id`=? AND `menu_id`=?";
				$this->mDb->query( $query, array( $item["pos"], $item["parent_id"], $item["menu_id"] ) );
				$this->mDb->CompleteTrans();
				$this->writeMenuCache( $item['menu_id'] );
			}
		}
	}

	/**
	* Move item south
	* @param $pItemId item id of item to be moved
	*/
	public function moveItemSouth( $pItemId=null ) {
		if( $this->isValid() && $pItemId ) {
			// pass current item into managable array
			$item = $this->mInfo["items"][$pItemId];
			$this->mDb->StartTrans();
			$query = "SELECT `item_id`, `pos` FROM `".BIT_DB_PREFIX."nexus_menu_items` WHERE `pos`>? AND `parent_id`=? ORDER BY `pos` ASC";
			$result = $this->mDb->query( $query, array( (int)$item["pos"], (int)$item["parent_id"] ) );
			if( $res = $result->fetchRow() ) {
				//Swap position values
				$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `pos`=? WHERE `item_id`=?";
				$this->mDb->query( $query, array( (int)$item["pos"], (int)$res["item_id"] ) );
				$this->mDb->query( $query, array( (int)$res["pos"], (int)$item["item_id"] ) );
			}
			$this->mDb->CompleteTrans();
			$this->writeMenuCache( $item['menu_id'] );
		}
	}

	/**
	* Move item north
	* @param $pItemId item id of item to be moved
	*/
	public function moveItemNorth( $pItemId=null ) {
		if( $this->isValid() && $pItemId ) {
			// pass current item into managable array
			$item = $this->mInfo["items"][$pItemId];
			$this->mDb->StartTrans();
			$query = "SELECT `item_id`, `pos` from `".BIT_DB_PREFIX."nexus_menu_items` WHERE `pos`<? AND `parent_id`=? ORDER BY `pos` desc";
			$result = $this->mDb->query( $query, array((int)$item["pos"], (int)$item["parent_id"] ) );
			if( $res = $result->fetchRow() ) {
				//Swap position values
				$query = "UPDATE `".BIT_DB_PREFIX."nexus_menu_items` SET `pos`=? WHERE `item_id`=?";
				$this->mDb->query( $query, array( (int)$res["pos"], (int)$item["item_id"] ) );
				$this->mDb->query( $query, array( (int)$item["pos"], (int)$res["item_id"] ) );
			}
			$this->mDb->CompleteTrans();
			$this->writeMenuCache( $item['menu_id'] );
		}
	}

	/**
	* Imports a structure from liberty_structures to nexus including hierarchy
	* @return number of errors encountered
	*/
	public function importStructure( $pStructureId=null ) {
		if( $pStructureId || !is_numeric( $pStructureId ) ) {

			$structure = new LibertyStructure( $pStructureId );
			$structure->load();

			// order matters for these conditionals
			if( empty( $structure ) || !$structure->isValid() ) {
				$this->mErrors['structure'] = KernelTools::tra( 'Invalid structure' );
			}

			if( $structure->mInfo['root_structure_id'] == $structure->mInfo['structure_id'] ) {
				$rootStructure = &$structure;
			} else {
				$rootStructure = new LibertyStructure( $structure->mInfo['root_structure_id'] );
				$rootStructure->load();
			}
			$structureList = $rootStructure->getSubTree( $rootStructure->mInfo['structure_id'] );
			$menuHash['title'] = $rootStructure->mInfo['title'];
			if( $menu_id = $this->storeMenu( $menuHash ) ) {
				// we need to insert the structure title manually, as this is not part of the structure
				$itemHash = array(
					'menu_id' => $menu_id,
					'title' => $rootStructure->mInfo['title'],
					'pos' => 1,
					'parent_id' => 0,
					'rsrc' => $rootStructure->mInfo['structure_id'],
					'rsrc_type' => 'structure_id'
				);
				$storedItem = $this->storeItem( $itemHash );

				// insert all nodes in structure as menu items
				foreach( $structureList as $structureItem ) {
					if( $structureItem['first'] ) {
						// get id of the current item
						$query = "SELECT MAX(`item_id`) FROM `".BIT_DB_PREFIX."nexus_menu_items`";
						$parentPath[] = $this->mDb->getOne( $query, [] );
						$parent_id = end( $parentPath );
					}
					if( $structureItem['last'] ) {
						// move up one step in the structure parentPath
						array_pop( $parentPath );
						$parent_id = end( $parentPath );
					} else {
						// save the item in the menu
						$tmpItem = $rootStructure->getNode( $structureItem['structure_id'] );
						$itemHash = array(
							'menu_id' => $menu_id,
							'title' => $structureItem['title'],
							'pos' => $tmpItem['pos'],
							'parent_id' => $parent_id,
							'rsrc' => isset( $structureItem['structure_id'] ) ? $structureItem['structure_id'] : null,
							'rsrc_type' => 'structure_id'
						);
						$storedItem = $this->storeItem( $itemHash );
					}
				}
			} else {
				$this->mErrors['store_menu'] = KernelTools::tra( 'The menu could not be stored.' );
			}
		} else {
			$this->mErrors['structure_id'] = KernelTools::tra( 'No valid structure id was given.' );
		}
		return count( $this->mErrors ) == 0;
	}

	/**
	* rewrites all menu cache files. particularly important when menus have been renamed or deleted
	* @param $pMenuId menu id of the menu for which we want to create a cache file
	* @return number of errors encountered
	*/
	public function rewriteMenuCache() {
		if( is_dir( $path = TEMP_PKG_PATH.NEXUS_PKG_NAME.'/modules' ) ) {
			$handle = opendir( $path );
			while( false!== ( $cache_file = readdir( $handle ) ) ) {
				if( $cache_file != "." && $cache_file != ".." ) {
					unlink( $path.'/'.$cache_file );
				}
			}

			// get the menus and rewrite the cache, one by one
			$menuList = $this->getMenuList();
			if( !empty( $menuList ) ) {
				foreach( $menuList as $menu ) {
					$this->writeMenuCache( $menu['menu_id'] );
				}
			}
		} else {
			$this->mErrors['chache_rewrite'] = KernelTools::tra( "The cache directory for nexus menus doesn't exist." );
		}
		return count( $this->mErrors ) == 0;
	}

	/**
	* writes cache files to where the plugins determine
	* @param $pMenuId menu id of the menu for which we want to create a cache file
	* @return number of errors encountered
	*/
	public function writeMenuCache( $pMenuId=null ) {
		if( $this->isValid() && !BitBase::verifyId( $pMenuId )) {
			$pMenuId = $this->mInfo['menu_id'];
		}

		// load the menu if need be
		$cacheMenu = new Nexus( $pMenuId );
		$cacheMenu->load();

		if( !empty( $cacheMenu->mInfo['plugin_guid'] )) {
			global $gNexusSystem;
			if( $func = $gNexusSystem->getPluginFunction( $cacheMenu->mInfo['plugin_guid'], 'write_cache_function' ) ) {
				$moduleCache = $func( $cacheMenu );
			}
		}

		if( !empty( $moduleCache ) ) {
			foreach( $moduleCache as $cache_file => $cache_string ) {
				$h = fopen( TEMP_PKG_PATH.NEXUS_PKG_NAME.'/modules/'.$cache_file, 'w' );
				if( isset( $h ) ) {
					fwrite( $h, $cache_string );
					fclose( $h );
				} else {
					$this->mErrors['write_module_cache'] = KernelTools::tra( "Unable to write to" ).': '.realpath( $cache_file );
				}
			}
		} else {
			$this->mErrors['write_module_cache'] = KernelTools::tra( "Unable to write the cache file because there was something wrong with the plugin " ).': '.$cacheMenu->mInfo['plugin_guid'];
		}
		return count( $this->mErrors ) == 0;
	}

	/**
	* This is not in use yet. would be good if this could be updated directly from the db
	*/
	public function getGalleryListMenu( $pParentId=null ) {
		global $gBitSystem, $gFisheyeGallery;

		$gFisheyeGallery = new FisheyeGallery();

		$hash['root_only'] = true;
		$hash['get_thumbnails'] = false;
		$galleryList = $gFisheyeGallery->getList( $hash );

		foreach( $galleryList as $key => $gal ) {
			$itemHash['item_id'] = 'gl'.$key;
			$itemHash['parent_id'] = $pParentId;
			$itemHash['title'] = $gal['title'];
			$itemHash['rsrc'] = $gal['content_id'];
			$itemHash['rsrc_type'] = 'content_id';
			$ret['gl'.$key] = $itemHash;
		}
		return $ret;
	}
}
