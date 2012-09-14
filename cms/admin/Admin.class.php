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
require_once 'AdminForm.class.php';

class Admin
{
	const
		MODE_UPDATE=1,
		MODE_RENDER=2,
		MODE_AJAX=3;
	private static
		$title='Appropriate Title Here',
		$redirect='',
		$mode,
		$urlPrefix='',
		$userPriv=1000000,
		$sessionIndex=0,
		$sessionData=array(),
		$pages=array(
			'admin/pages'=>array('title'=>'Pages','class'=>'Pages','privilege'=>0),
			'admin/menu'=>array('title'=>'Menu','class'=>'Menu','privilege'=>0));
	public static function run($url)
	{
		if(\cms\Init::forceSecure())
		{
			$result=false;
			$render=true;
			self::$sessionIndex=rand(100,10000);
			$title='';
			$menuHTML='';
			$logoutHTML='';
			$formHTML='';
			$user=\cms\Init::getUser();
			if($user===false)
			{
				self::render(self::getLoginForm(),'Login');
			}
			else
			{
				$page=false;
				self::$userPriv=$user['privilege'];
				if($url=='admin')
				{
					$title='Admin';
				}
				else if(substr($url,0,14)=='admin/reports/')
				{
					$report=dirname(__FILE__).'/reports/'.substr($url,14).'.php';
					if(is_file($report))
					{
						$render=false;
						loadReport($report);
					}
					else $formHTML.='
			Page not found.';

				}
				else
				{
					$page=isset(self::$pages[$url])?self::$pages[$url]:false;
					$title=$page===false?'error':$page['title'];
					$formHTML='';
					if($page===false)
					{
							$formHTML.='
			Page not found.';
							header("HTTP/1.0 404 Not Found");
					}
				}
				if($page!==false)
				{
					if(self::$userPriv>$page['privilege']){ $formHTML='Insufficient privilege level.'; }
					else if(!\cms\Init::connect()){ $formHTML='Error: Unable to connect'; }
					else
					{
						$className=$page['class'];
						require_once($className.'.class.php');
						$className='\\cms\\admin\\'.$className;
						$ru=explode('?',$_SERVER['REQUEST_URI']);
						$ru=$ru[0];
						self::$redirect='https://'.$_SERVER['SERVER_NAME'].$ru;
						if(isset($_POST['cms_index']))
						{
							$data=self::getData($_POST['cms_index']);
							if($data===false)
							{
								$formHTML.='
			Form input invalid - you might have reloaded the page or hit the \'back\' button on your browser.';
							}
							else
							{
								$render=false;
								if(!isset($_SESSION[$url]))$_SESSION[$url]=array();
								$className::setup($_SESSION[$url],$fullURL);
								$className::update($data);
								$_SESSION[$url]=$className::getSessionData();
								header('Location: '.self::$redirect);
							}
						}
						else
						{
							if(!isset($_SESSION[$url]))$_SESSION[$url]=array();
							$className::setup($_SESSION[$url],$fullURL);
							$formHTML.=$className::getHTML();
							$_SESSION[$url]=$className::getSessionData();
						}
					}
				}
				if($render)
				{
					$logoutHTML='
		<fieldset><legend>'.self::$title.' - Admin</legend><form method="POST"><input type="hidden" name="cms_logout" /><input type="submit" value="logout" /></form></fieldset>';
					$menuHTML=self::getMenu($url);
					self::render($logoutHTML.$menuHTML.$formHTML,$title);
				}
			}
			$_SESSION['cms_data']=self::$sessionData;
		}
	}
	
	public static function setRedirect($url){ self::$redirect='/'.self::$urlPrefix.'admin/'.$url; }
	
	/**
	 * @param array $data data to save for one request cycle - This will be passed to the 'update' function.
	 * @return string form element containing an index to the saved data - to be used by getData
	 */
	public static function setData($data)
	{
		$result='<input type="hidden" name="cms_index" value="'.self::$sessionIndex.'">';
		self::$sessionData[self::$sessionIndex]=$data;
		self::$sessionIndex++;
		return $result;
	}
	
	//Used by \cms\Init on logout
	public static function clearSession(){ foreach(self::$pages as $url=>$page) unset($_SESSION[$url]); }

	private static function getData($index)
	{
		return isset($_SESSION['cms_data'][$index])?$_SESSION['cms_data'][$index]:false;
	}
	
	private static function getMenu($url)
	{
		$result = '
		<ul class="topnav">';
		foreach(self::$pages as $u=>$p)
		{
			if($p['privilege']>=self::$userPriv)
			{
				$result.= '
			<li><a '.($u==$url?'class="current"':'href="/'.self::$urlPrefix.$u.'"').'>'.$p['title'].'</a></li>';
			}
		}
		$result.= '
		</ul><div class="clearfix"></div>';
		return $result;
	}

	private static function getLoginForm()
	{
		return '
	<fieldset><legend>'.self::$title.' - Login</legend>
		<form method="POST">
			<label for="cms_user">User</label><input type="text" name="cms_user" id="cms_user" />
			<label for="cms_password">Password</label><input type="password" name="cms_password" id="cms_password" />
			<div class="clearfix"></div>
			<input type="submit" value="login" />
		</form>
	</fieldset>
	';
	}
	
	private static function render($inner,$title)
	{
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<title><?php echo 'ZedBoot admin - '.htmlentities($title); ?></title>
<style>
* {font-family: Arial, Helvetica, sans-serif;}
.clearfix{ clear:both; }
.number{text-align:right;}
.center{text-align:center;}
th, td{padding:0 3em 0 0;}
th{text-align:left;}
ul.topnav { list-style: none; padding: 0; width:800px; margin: 0.5em auto;}
ul.topnav li { padding: 0 0.5em 1em; float:left;}
ul.topnav li a {display:block; text-align:center; width:100px; font-weight:bold; background-color:#ff0; text-decoration:none; color:#444; padding:0.25em; border: solid 3px #444; border-radius: 1em; -webkit-border-radius: 1em; -moz-border-radius: 1em; box-shadow: inset 0 -10px 3px -3px rgba(0,0,0,0.25); -webkit-box-shadow: inset 0 -10px 3px -3px rgba(0,0,0,0.25); -moz-box-shadow: inset 0 -10px 3px -3px rgba(0,0,0,0.25);}
ul.topnav li a.current, ul.topnav li a:hover {color: #fff; background-color:#f00; box-shadow: inset 0 10px 3px -3px rgba(0,0,0,0.25); -webkit-box-shadow: inset 0 10px 3px -3px rgba(0,0,0,0.25); -moz-box-shadow: inset 0 10px 3px -3px rgba(0,0,0,0.25);}
/* Thanks to themaninblue.com */
fieldset{ border-radius: 5px; display: block; width: 800px; margin: 0 auto 3em auto; padding: 0 1em 1em 1em; background-color: #EEEEEE; }
fieldset.submit{ border: none; background-color: transparent; text-align: center; }
fieldset.radio{ width: 15em; margin: 1em 0 2em 0; background-color: #DDDDDD; }
fieldset.radio label{ font-weight: normal; }
fieldset.radio input{ clear: both; float: left; width: auto; }
input, select, textarea{ display: block; float:left;}
input, label, textarea{width: 14em;}
div.reorder-buttons{float:right;}
div.reorder-buttons input{width: 5em;}
label{ display: block; margin-bottom: 1em; font-weight: bold; float:left; clear:both;}
select{ display: block; }
legend{background-color:#fff;border-radius: 5px; -webkit-border-radius: 5px; -moz-border-radius: 5px; box-shadow: 0px 0px 5px rgba(0,0,0,1); -webkit-box-shadow: 0px 0px 5px rgba(0,0,0,1); -moz-box-shadow: 0px 0px 5px rgba(0,0,0,1); margin-bottom: 0.5em;}
</style>
</head>
<body>
<?php echo $inner; ?>
</body>
</html>
<?php
	}
}
function loadReport($report)
{
	include $report;
}
?>
