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
namespace cms;
class Init
{
	const
		ENV_PP_SANDBOX=0,
		ENV_PP_BETA_SANDBOX=1,
		ENV_PP_LIVE=2;

	private static
		//Moneris settings
		$monerisStoreId='store5',
		$monerisApiToken='yesguy',
		//Paypal settings
		$ppEnv=self::ENV_PP_SANDBOX,
		$ppUser='',
		$ppPass='',
		$ppSig='',
		$dbHost='localhost',
		$dbUser='db_user',
		$dbPass='db_pass',
		$dbName='db_name',
		$dbConnection=false,
		$dbStatement=false, //For transparent cleanup
		$hashAlgorithm='sha256',
		$users=array(
			//privilege determines which admin forms the user will see, and can be used by page content classes
			'webmaster'=>array('password'=>'****hashed password here****','privilege'=>0),
			'admin' =>array('password'=>'****hashed password here****','privilege'=>1000)),
		//Users can be loaded from a table - required columns: login,password and privilege
		//getUser checks the users array then the table
		$userTable='';
	public static function connect()
	{
		$result=false;
		if(self::$dbConnection===false)
		{
			try {
				self::$dbConnection = new \PDO('mysql:host='.self::$dbHost.';dbname='.self::$dbName, self::$dbUser, self::$dbPass);
				$result=true;
			} catch (PDOException $e) {
				self::$dbConnection=false;
				error_log($e->getMessage());
			}
		}
		else $result=true;
		return $result;
	}
	public static function getUser()
	{
		$result=false;
		if(isset($_POST['cms_logout']))
		{
			unset($_SESSION['cms_user']);
			require_once 'admin/Admin.class.php';
			\cms\admin\Admin::clearSession();
		}
		else if(isset($_POST['cms_user']) && isset($_POST['cms_password']))
		{
			unset($_SESSION['cms_user']);
			$login=$_POST['cms_user'];
			if(isset(self::$users[$login]))
			{
				if(self::$users[$login]['password']==hash(self::$hashAlgorithm,$_POST['cms_password']))
				{
					$user=self::$users[$login];
					$user['login']=$login;
					$_SESSION['cms_user']=$user;
				}
			}
			else if(!empty(self::$userTable))
			{
				if(self::connect())
				{
					$rs=self::prepareAndExecute('Init::getUser()','SELECT * FROM `'.self::$userTable.'` WHERE `login`=:l AND `password`=:p',array('l'=>$login,'p'=>hash(self::$hashAlgorithm,$_POST['cms_password'])));
					if($rs!==false)
					{
						$data=$rs->fetch(\PDO::FETCH_ASSOC);
						if($data!==false)
						{
							unset($data['password']);
							$_SESSION['cms_user']=$data;
						}
					}
				}
			}
		}
		$result=isset($_SESSION['cms_user'])?$_SESSION['cms_user']:false;
		return $result;
	}
	public static function forceSecure()
	{
		$result=isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on';
		if(!$result)
		{
			$url='https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
			//Send the redirect header
			header('Location: '.$url);
		}
		return $result;
	}
	
	/**
	 * This will handle selects that aren't too complicated.
	 * @param string $table
	 * @param array|boolean $fields field names array('name','another_name',...), false for '*'
	 * @param array $expressions field expressions array('name'=>'exp','another name'=>'another exp',...)
	 * @param array $conditions array(array('name'=>$fieldName,'op'=>$operand,'value'=>$value),array('expr'=>$expression,'op'=>$operand,'value'=>$value),...)
	 * 				if 'op'=>'IN', then $value must be an array
	 * 				use 'name' for field names (will be wrapped in ``
	 * 				use 'expr' for expressions
	 * @param array $orderFields array($fieldName=>['asc'|'desc'])
	 * @param int|boolean $limitStart
	 * $param int|boolean $limitLength
	 */
	public static function select($context,$table,$fields,$expressions=array(),$conditions=array(),$orderFields=array(),$offset=false,$rowCount=false)
	{
		$sql='SELECT ';
		$values=array();
		$i=1;
		if(is_array($fields))
		{
			$delim='';
			foreach($fields as $name)
			{
				$sql.='`'.$name.'`';
				$delim=',';
			}
		}
		else
		{
			$sql.='*';
			$delim=',';
		}
		foreach($expressions as $name=>$exp)
		{
			$sql.=$delim.$exp.' AS `'.$name.'`'; 
		}
		$sql.=' FROM `'.$table.'`';
		if(count($conditions)>0)
		{
			$sql.=' WHERE ';
			$delim='';
			foreach($conditions as $c)
			{
				$op=$c['op'];
				$expr=isset($c['name'])?('`'.$c['name'].'` '):(isset($c['expr'])?($c['expr'].' '):false);
				if($expr!==false)
				{
					$sql.=$delim.$expr;
					if($op=='IN')
					{
						$vs=$c['value'];
						$sql.='IN (';
						$delim='';
						foreach($vs as $v)
						{
							$valueIndex='v'.$i++;
							$values[$valueIndex]=$v;
							$sql.=$delim.':'.$valueIndex;
							$delim=',';
						}
						$sql.=')';
					}
					else
					{
						$valueIndex='v'.$i++;
						$values[$valueIndex]=$c['value'];
						$sql.=$op.':'.$valueIndex;
					}
					$delim=' AND ';
				}
			}
		}
		if(count($orderFields)>0)
		{
			$sql.=' ORDER BY ';
			$delim='';
			foreach($orderFields as $name=>$d)
			{
				$sql.=$delim.'`'.$name.'` '.$d;
				$delim=',';
			}
		}
		if($rowCount!==false)
		{
			$sql.=' LIMIT ';
			if($offset!==false)$sql.=$offset.',';
			$sql.=$rowCount;
		}
		return self::prepareAndExecute($context,$sql,$values);
	}
	public static function insert($context,$table,$fieldValues,$fieldExpressions)
	{
		$result=false;
		$sql='INSERT INTO `'.$table.'` SET ';
		$delim='';
		$values=array();
		$i=1;
		foreach($fieldValues as $n=>$v)
		{
			$valueIndex='v'.$i++;
			$values[$valueIndex]=$v;
			$sql.=$delim.'`'.$n.'`=:'.$valueIndex;
			$delim=',';
		}
		foreach($fieldExpressions as $n=>$e)
		{
			$sql.=$delim.'`'.$n.'`='.$e;
			$delim=',';
		}
		if(self::prepareAndExecute($context,$sql,$values)!==false)$result=self::$dbConnection->lastInsertId();
		return $result;
	}
	/**
	 * $conditions is just an array of field name=>value pairs
	 */
	public static function update($context,$table,$fieldValues,$fieldExpressions,$conditions)
	{
		$result=false;
		$sql='UPDATE `'.$table.'` SET ';
		$delim='';
		$i=1;
		$values=array();
		foreach($fieldValues as $n=>$v)
		{
			$vIndex='v'.$i++;
			$values[$vIndex]=$v;
			$sql.=$delim.'`'.$n.'`=:'.$vIndex;
			$delim=',';
		}
		foreach($fieldExpressions as $n=>$e)
		{
			$sql.=$delim.'`'.$n.'`='.$e;
			$delim=',';
		}
		if(count($conditions)>0)
		{
			$sql.=' WHERE ';
			$delim='';
			foreach($conditions as $n=>$v)
			{
				$vIndex='v'.$i++;
				$values[$vIndex]=$v;
				$sql.=$delim.'`'.$n.'`=:'.$vIndex;
				$delim=' AND ';
			}
			//Don't update without a where clause.
			$result=self::prepareAndExecute($context,$sql,$values);
		}
		return $result;
	}
	public static function prepareAndExecute($context,$sql,$values)
	{
		if(self::$dbStatement!==false)self::$dbStatement->closeCursor();
		self::$dbStatement=false;
		try
		{
			self::$dbStatement=self::$dbConnection->prepare($sql);
			if(self::$dbStatement===false)
			{
				$err=self::$dbConnection->errorInfo();
//echo $err[3];
				error_log($context.': '.$err[3]);
			}
		} catch (PDOException $e) {
			error_log($context.': '.$e->getMessage());
		}
		if(self::$dbStatement!==false)
		{
			try
			{
				if(!self::$dbStatement->execute($values))
				{
					self::$dbStatement=false;
					$err=self::$dbConnection->errorInfo();
//echo $err[3];
					error_log($context.': '.$err[3]);
				}
			} catch (PDOException $e) {
				self::$dbStatement=false;
				error_log($context.': '.$e->getMessage());
			}
		}
		return self::$dbStatement;
	}

	public static function query($context,$sql)
	{
		if(self::$dbStatement!==false)self::$dbStatement->closeCursor();
		self::$dbStatement=self::$dbConnection->query($sql);
		if(self::$dbStatement===false)
		{
			$err=self::$dbConnection->errorInfo();
//echo $err[3];
			error_log($context.': '.$err[3]);
		}
		return self::$dbStatement;
	}
	public static function loadPaypalObject()
	{
		require_once dirname(__FILE__).'/utilities/PaypalExpressCheckoutNVP.class.php';
		return new \cms\utilities\PaypalExpressCheckoutNVP(self::$ppUser,self::$ppPass,self::$ppSig,self::$ppEnv);
	}
	public static function doMonerisTransaction($orderId,$amount,$ccNumber,$expiryDate,$cvdValue,$transactionType)
	{
		require_once dirname(__FILE__).'/utilities/mpgClasses.php';
		$response=false;
		if(empty($cvdValue)){ $cvdData=array('cvd_indicator'=>'9'); }
		else $cvdData=array(
			'cvd_indicator' => '1',
			'cvd_value' => $cvdValue);
		$cvdInfo = new \mpgCvdInfo($cvdData);
		$expiryDate=substr($expiryDate,-2).substr($expiryDate,0,2);
		$transactionData=array(
			'type'=>$transactionType,
			'order_id'=>$orderId,
			'amount'=>$amount,
			'pan'=>$ccNumber,
			'expdate'=>$expiryDate,
			'crypt_type'=>'7'
		);
ob_start();
		$transaction=new \mpgTransaction($transactionData);
		$transaction->setCvdInfo($cvdInfo);
		$request = new \mpgRequest($transaction);
		$mpgHttpPost=new \mpgHttpsPost(self::$monerisStoreId,self::$monerisApiToken,$request);
		$response=$mpgHttpPost->getMpgResponse();
ob_clean();
		return $response;
	}
}
?>
