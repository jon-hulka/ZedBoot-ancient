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
namespace cms\admin;
class ZzzTemplate extends AdminForm
{
	private static
		$updateFields=array('name'); //This works for a simple form
	protected static
		$title='Widget',//Shows up on the detail form
		$titlePlural='Widgets',//Shows up on the search form
		$tableName='cms_widget',
		$orderFields=array('list_order'=>'ASC')//array('name'=>'ASC'|'DESC',...)
//These are optional
//		,$rowsPerPage=0//0 to disable paging
//		,$reorderField='list_order'//false to disable reordering
//		,$reorderGroup=array()//array('column_name','another_column_name',...)
//		,$enableCreate=false //Use this to disable creation
//		,$enableDelete=false //Use this to disable deletion
//		,$editList=true //If the editor is to be part of the list - no detail form
		;
	protected static function getListItem($data)
	{
		return htmlspecialchars($data['name']);
	}

	protected static function getItemInputs($data)
	{
		$name='';
		if($data!==false)
		{
			$name=htmlspecialchars($data['name']);
		}
		return '
		<label for="widget_name">Title</label><input type="text" name="name" id="widget_name" value="'.$name.'" />';
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
//Optional functions - Override these for custom implementations
//	protected static function updateCustom($function,$data){}
//	protected static function getFilterInputs($filterConditions){ return ''; }
//  To do:
//    - change this to getFilterClause - ANDed conditions don't allow much flexibility
//    - return array('pdo_sql'=>'`column_name`=>:v1,`other_column`=>:v2','values'=>array('v1'=>'value1','v2'=>$value2))
//	protected static function getFilterConditions(){ return array(); }
/*
	protected static function init()
	{
		//This will run first, so any custom tweaking (titles, for exampe) should go here
		//Might be useful to make sure the list doesn't display anything if a filter isn't set
		setFilterConditions(array(array('name'=>'id','op'=>'=','value'=>'na')));
	}
}
?>
