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
class Pages extends AdminForm
{
	private static
		$updateFields=array('title','path','type'),
		$pageTypes=array('Gallery','Content');
	protected static
		$title='Page',//Shows up on the detail form
		$titlePlural='Pages',//Shows up on the search form
		$tableName='cms_pages',
		$orderFields=array('title'=>'ASC')//array('name'=>'ASC'|'DESC',...)
		;

	protected static function getListItem($data)
	{
		$link=htmlspecialchars($_SERVER['HTTP_HOST']).'/'.htmlspecialchars($data['path']);
		return htmlspecialchars($data['title']).' - <a href="https://'.$link.'">'.$link.'</a>';
	}
	protected static function getItemInputs($data)
	{
		$title='';
		$path='';
		$type='';
		if($data!==false)
		{
			$title=htmlspecialchars($data['title']);
			$path=htmlspecialchars($data['path']);
			$type=htmlspecialchars($data['type']);
//			$metaTitle=htmlspecialchars($data['meta_title']);
//			$metaKeywords=htmlspecialchars($data['meta_keywords']);
		}
		return '
		<label for="page_title">Title</label><input type="text" name="title" id="page_title" value="'.$title.'" />
		<label for="page_path">'.htmlspecialchars($_SERVER['HTTP_HOST']).'/</label><input type="text" name="path" id="page_path" value="'.$path.'" />'.self::getPageTypeList($type);
//		<label for="meta_title">SEO Title</label><textarea name="meta_title" id="meta_title">'.$metaTitle.'</textarea>
//		<label for="meta_keywords">SEO Keywords</label><textarea name="meta_keywords" id="meta_keywords">'.$metaKeywords.'</textarea>';
	}

	protected static function getUpdateFields($id)
	{
		$values=array();
		foreach(self::$updateFields as $fn)
		{
			if(isset($_POST[$fn])) $values[$fn]=$_POST[$fn];
		}
		if(isset($values['path']))$values['path']=self::fixPath($values['path']);
		return $values;
	}

	private static function getPageTypeList($type)
	{
		$result='
		<label for="page-type">Page Type</label><select name="type" id="page-type">';
		$index=array_search($type,self::$pageTypes);
		if($index===false)
		{
			$result.='
		<option selected="selected"></option>';
		}
		foreach(self::$pageTypes as $t)
		{
			$result.='
		<option'.($t==$type?' selected="selected"':'').'>'.htmlentities($t).'</option>';
		}
		$result.='
		</select>';
		return $result;
	}

	private static function fixPath($path)
	{
		$result='';
		$l1=strlen($path);
		do
		{
			$l2=$l1;
			$path=str_replace('//','/',$path);
			$l1=strlen($path);
		}while($l2!=$l1);
		$pieces=explode('/',trim($path,'/'));
		$delim='';
		foreach($pieces as $piece)
		{
			$result.=$delim.urlencode($piece);
			$delim='/';
		}
		return $result;
	}
}
?>
