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
CREATE TABLE `cms_pages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` varchar(75) NOT NULL,
  `title` varchar(75) NOT NULL,
  `type` varchar(15) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM;
*/
namespace cms;
class Page
{
	const MODE_VIEW=0;
	const MODE_EDIT=1;
	private static
		$includeMenus=true,
		$urlPrefix='',
		$sessionIndex=0,
		$contentClass='',
		$styles='',
		$menu='',
		$catalog='',
		$sessionData=array(),
		$uri,
		$mode=self::MODE_VIEW,
		$errorMessage=false,
		$pageParameters=false,
		$headers=array(
			404=>array(
				'HTTP/1.0 404 Not Found'
				));
	public static function run($uri)
	{
		$user=Init::getUser();
		self::$mode=$user===false?self::MODE_VIEW:self::MODE_EDIT;
		$continue=true;
		if(self::$mode==self::MODE_EDIT){ $continue=Init::forceSecure(); }
		if($continue)
		{
			self::$uri=$uri;
			if(!Init::connect()){ self::$errorMessage='Database connection error.'; }
			else if(self::load() && !self::checkUpdate()) self::render();
		}
	}
	
	/**
	 * Useful for forms - save sensitive data here and put the index in a hidden input.
	 * The data will be available to update/ajax calls via getData
	 * Please Note: this is only meant to be used by render()
	 * If content is being written by update/ajax calls, the page content class should implement its own mechanism for saving and retrieving data.
	 * @return index to for retrieval via getData()
	 */
	public static function setData($data)
	{
		self::$sessionData[self::$sessionIndex]=$data;
		return self::$sessionIndex++;
	}
	/**
	 * To be used by AJAX and update functions.
	 */
	public static function getData($index)
	{
		if(self::$sessionData===false)
			self::$sessionData=isset($_SESSION['cms_page_data'][self::$pageParameters['type']][self::$pageParameters['id']])
				?$_SESSION['cms_page_data'][self::$pageParameters['type']][self::$pageParameters['id']]
				:array();
		return isset(self::$sessionData[$index])?self::$sessionData[$index]:false;
	}
	private static function writeHeaders($index)
	{
		foreach(self::$headers[$index] as $header)
			header($header);
	}
	
	private static function checkUpdate()
	{
		$result=false;
		$function='';
		if(isset($_POST['cms_function']))
		{
			$result=true;
			$function=$_POST['cms_function'];
			header('Location: https://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);
		}
		else if(isset($_POST['cms_ajax_data']))
		{
			$result=true;
			$function='ajax';
		}
		if($result)
		{
			require_once self::$pageParameters['type'].'.class.php';
			$class='\\cms\\'.self::$pageParameters['type'];
			$class::update(self::$pageParameters['id'],$function);
		}
		return $result;
	}
	
	/**
	 * Normally, self::$pageParameters['type'] and self::$pageParameters['id'] should be set up here.
	 * This is important to ensure that session variables are set up correctly.
	 */
	private static function load()
	{
		$result=false;
		self::loadMenu();
/*==================== -- For the shopping cart -- =====================
//This is a hack to allow url parameters beyond the first segment
		$parts=explode('/',self::$uri);
		$count=count($parts);
		$top=$count>0?$parts[0]:'';
		if($top=='eshop')
		{
			self::$pageParameters=array('type'=>'Cart','id'=>-1);
			$result=true;
			if($count>1)
			{
				$sub=array();
				for($i=1; $i<$count; $i++)
				{
					$sub[]=$parts[$i];
				}
				self::$pageParameters['sub_url']=$sub;
				if($sub[0]=='catalog-item')
				{
					include 'catalog_item.php'; //For colorbox
					$result=false; //Don't render the page
				}
			}else self::$pageParameters['sub_url']=array();
		}
		else
		{
//====================================================================*/
		$sql='SELECT * FROM `cms_pages` WHERE `path`=:p';
		$rs=Init::prepareAndExecute('Page::load()',$sql,array('p'=>self::$uri));
		if($rs!==false)self::$pageParameters=$rs->fetch(\PDO::FETCH_ASSOC);
		$result=self::$pageParameters!==false;
		if(!$result)
		{
			self::$errorMessage='Page Not found.';
			self::writeHeaders(404);
		}
//		}
		if($result)
		{
			$contentType=self::$pageParameters['type'];
			require_once $contentType.'.class.php';
			self::$contentClass='\\cms\\'.$contentType;
		}
		return $result;
	}
	
	private static function render()
	{
		//Always start rendering the page with fresh session data.
		self::$sessionData=array();
		$title=(self::$pageParameters!==false&&isset(self::$pageParameters['title']))?self::$pageParameters['title']:'';
		ob_start();
		include 'template_page.php';
		ob_flush();
		$_SESSION['cms_page_data'][self::$pageParameters['type']][self::$pageParameters['id']]=self::$sessionData;
	}
	private static function writeStyles(){ if(self::$contentClass!==''){$class=self::$contentClass; $class::writeStyles(self::$mode); }}
	private static function writeScript(){ if(self::$contentClass!==''){$class=self::$contentClass; $class::writeScript(self::$mode); }}

	//Called by template_page.php
	private static function writeContent()
	{
		if(self::$errorMessage!==false)
		{
			echo '<h1>'.self::$errorMessage.'</h1>';
		}
		else
		{
			if(self::$mode==self::MODE_EDIT)
			{
				if(self::$includeMenus) echo '
<a href="/admin">go to admin</a>
<form method="POST"><input type="hidden" name="cms_logout" value="logout" /><input type="submit" value="logout" /></form>';
			}
			$contentType=self::$pageParameters['type'];
			$_SESSION['cms_page_content_type']=$contentType;
			$_SESSION['cms_page_id']=self::$pageParameters['id'];
			$class=self::$contentClass;
			$class::render(self::$pageParameters['id'],self::$mode,self::$pageParameters);
		}
	}
	private static function writeMenu(){ echo self::$menu; }
	private static function loadMenu()
	{
		self::$menu='';
		$sql='SELECT * FROM `cms_menu_items` ORDER BY `list_order`';
		$rs=Init::query('Page::writeMenu',$sql);
		if($rs!==false)
		{
			foreach($rs as $row)
			{
				$link=$row['link']==self::$uri?' class="current"':' href="http://foo.bar.baz/'.$row['link'].'"';
				self::$menu.='<a'.$link.'>'.htmlspecialchars($row['title']).'</a>';
			}
		}
	}
}

?>
