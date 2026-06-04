{strip}
{foreach from=$gNexus->mInfo.tree item=item key=key}
	{if $item.first}<ul{if $key eq 0} class="toc"{/if}>{else}</li>{/if}
	{if $item.last}</ul>{else}
		<li>
			<div class="{cycle values='even,odd'} clear">
				<a href="{$smarty.const.NEXUS_PKG_URL}menu_items.php?sort_menu={$menuId}&amp;menu_id={$item.menu_id}&amp;item_id={$item.item_id}&amp;tab=edit">{biticon ipackage="icons" iname="document-properties"  iforce=icon ipackage="icons"  iexplain="edit item" style="float:right"}</a>

				<a href="{$smarty.const.NEXUS_PKG_URL}menu_sort.php?sort_menu={$menuId}&amp;menu_id={$item.menu_id}&amp;item_id={$item.item_id}&amp;move_item=e&amp;tab=organise">{biticon iforce=icon ipackage="icons" iname="go-next" iexplain="move right" style="float:right"}</a>
				<a href="{$smarty.const.NEXUS_PKG_URL}menu_sort.php?sort_menu={$menuId}&amp;menu_id={$item.menu_id}&amp;item_id={$item.item_id}&amp;move_item=s&amp;tab=organise">{biticon ipackage="icons" iname="network-receive"  iforce=icon ipackage="icons"  iexplain="move down" style="float:right"}</a>
				<a href="{$smarty.const.NEXUS_PKG_URL}menu_sort.php?sort_menu={$menuId}&amp;menu_id={$item.menu_id}&amp;item_id={$item.item_id}&amp;move_item=n&amp;tab=organise">{biticon ipackage="icons" iname="network-transmit"  iforce=icon ipackage="icons"  iexplain="move up" style="float:right"}</a>
				<a href="{$smarty.const.NEXUS_PKG_URL}menu_sort.php?sort_menu={$menuId}&amp;menu_id={$item.menu_id}&amp;item_id={$item.item_id}&amp;move_item=w&amp;tab=organise">{biticon ipackage="icons" iname="go-previous"  iforce=icon ipackage="icons"  iexplain="move left" style="float:right"}</a>

				{$item.title|escape}
			</div>
	{/if}
{/foreach}
{/strip}
