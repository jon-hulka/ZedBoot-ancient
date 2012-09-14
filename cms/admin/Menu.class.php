<?php
/*
 * Copyright 2012 Jonathan Hulka (jon.hulka@gmail.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
 * MA 02110-1301, USA.
 * 
 */
/*
CREATE TABLE `cms_menu_items` (
  `title` varchar(30) NOT NULL,
  `link` varchar(75) NOT NULL,
  `list_order` int(11) NOT NULL,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
*/
namespace cms\admin;
class Menu extends AdminForm
{
	private static
		$updateFields=array('title','link');
	protected static
		$title='Menu Item',//Shows up on the detail form
		$titlePlural='Menu',//Shows up on the search form
		$tableName='cms_menu_items',
		$orderFields=array('list_order'=>'ASC')//array('name'=>'ASC'|'DESC',...)
		,$reorderField='list_order'//false to disable reordering
		;
	protected static function getListItem($data){ return htmlspecialchars($data['title']); }
	protected static function getItemInputs($data)
	{
		$title='';
		$link='';
		if($data!==false)
		{
			$title=htmlspecialchars($data['title']);
			$link=htmlspecialchars($data['link']);
		}
		return '
		<label for="menu_title">Title</label><input type="text" name="title" id="menu_title" value="'.$title.'" />
		<label for="menu_link">Link</label><input type="text" name="link" id="menu_path" value="'.$link.'" />';
	}
	protected static function getUpdateFields($id)
	{
		$values=array();
		foreach(self::$updateFields as $fn)
		{
			if(isset($_POST[$fn])) $values[$fn]=$_POST[$fn];
		}
		return $values;
	}
}
?>
