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
abstract class AdminForm
{
//======================================================================
//To do: add setErrorMessage($message);
//======================================================================
	private static
		$session=false,
		$url='',
		$itemEditable,
		$itemDeletable;
	protected static //default values
		$title='', $titlePlural='', $tableName='', $orderFields=array(), $rowsPerPage=0, $reorderField=false, $reorderGroup=array(),
		$enableCreate=true, $enableDelete=true, $editList=false; //$editList not implemented
	/**
	 * @param array $data row of data
	 * @return the label content for a list item
	 */
	protected abstract static function getListItem($data);
	//Only applicable from with getListItem()
	protected final static function setItemEditable($editable){self::$itemEditable=$editable;}
	//Only applicable from within getListItem() or getItemInputs()
	protected final static function setItemDeletable($deletable){self::$itemDeletable=$deletable;}
	/**
	 * @param array|boolean $data row of data, false for new item
	 */
	protected abstract static function getItemInputs($data);
	
	/**
	 * Extra markup to be output before the item detail form.
	 */
	protected static function getItemMarkup($data){ return ''; }
	/*
	 * Processes the edit form POST parameters
	 * @param int|boolean $id primary key for the record to be updated or false for new record
	 * @return array update fields: array($fieldName=>$value,...)
	 */
	protected abstract static function getUpdateFields($id);
	/**
	 * Override this to implement form search.
	 * @param array $filterConditions array($fieldName=>array('op'=>$operand,'value'=>$value),...) - can be modified to alter the search results
	 * @return string fields for the filter form.
	 */
	protected static function getFilterInputs(&$filterConditions){ return '';}
	/**
	 * Processes the filter form POST parameters. Override this to implement form search.
	 * @return array conditions: array(array('name'=>$fieldName,'op'=>$operand,'value'=>$value),...))
	 */
	protected static function getFilterConditions(){ return array(); }
	protected static function onDelete($id){}
	protected final static function getCustomSubmit($name,$function,$data){ return Admin::setData(array('function'=>$function,'data'=>$data)).'<input type="submit" value="'.$name.'" />'; }

	/**
	 * AdminForm handles the 'update', 'cancel', 'delete', 'edit' and 'reorder' functions. Override this to implement custom functions.
	 * @param string $function function name - 'update', 'cancel', 'delete', 'edit' and 'reorder' will be ignored.
	 * @param array $data session data for the update request.
	 * @return string view - 'list' for search form, '' or 'detail' to remain at the detail form, any other value will be interpreted as a url for redirection - relative to the admin folder.
	 */
	protected static function updateCustom($function,$data){}
	protected static function init(){}

	public final static function setup($sessionData,$url)
	{
		if(self::$url=='')
		{
			self::$session=$sessionData;
			self::$url=$url;
			static::init();
		}
	}
	public final static function getSessionData(){ return self::$session; }
	public final static function getURL(){ return self::$url; }
	public final static function setCustomData($key,$value)
	{
		if(!isset(self::$session['custom'])) self::$session['custom']=array();
		self::$session['custom'][$key]=$value;
	}
	public final static function clearCustomData($key){ unset(self::$session['custom'][$key]); }
	public final static function getCustomData($key){ return isset(self::$session['custom'][$key])?self::$session['custom'][$key]:false; }
	//Clears all conditions with matching name attributes
	public final static function setFilterConditions($conditions)
	{
		if(!isset(self::$session['filter_conditions']))self::$session['filter_conditions']=array();
		$cs=&self::$session['filter_conditions'];
		foreach($conditions as $condition)
		{
			$name=$condition['name'];
			foreach($cs as $k=>$c) if($c['name']==$name) unset($cs[$k]);
		}
		foreach($conditions as $condition)
		{
			$cs[]=$condition;
		}
	}

	public final static function update($data)
	{
		switch($data['function'])
		{
			case 'update': //update(false) handles the insert case
				self::doUpdate($data);
			case 'cancel':
				unset(self::$session['item']); //go back to list
				break;
			case 'delete':
				static::onDelete($data['id']);
				\cms\Init::query('AdminForm::update(): deleting.','DELETE FROM `'.static::$tableName.'` WHERE `id`=\''.$data['id'].'\'');
				unset(self::$session['item']); //go back to list
				break;
			case 'edit': //edit ($data['id']=false) handles the 'new' case
				self::$session['item']=$data['id'];
				break;
			case 'reorder':
				self::reorder($data);
				break;
			case 'search':
				self::$session['filter_conditions']=static::getFilterConditions();
			default:
				$view=static::updateCustom($data['function'],$data['data']);
				switch($view)
				{
					case 'list':
						unset(self::$session['item']); //go back to list
					case 'detail':
					case '':
						break;
					default:
						Admin::setRedirect($view);
						break;
				}
				break;
		}
	}

	public final static function getHTML()
	{
		$result='';
		if(isset(self::$session['item'])){ $result=self::getItem(self::$session['item']); }
		else $result=self::getList();
		return $result;
	}

	private final static function getList()
	{
		$result='';
		$offset=false;
		$limit=false;
		$pager='';
		$filterConditions=isset(self::$session['filter_conditions'])?self::$session['filter_conditions']:array();
		$filterInputs=static::getFilterInputs($filterConditions);
		if($filterInputs!='')
		{
			$result.='
		<fieldset>
			<legend>Search '.static::$titlePlural.'</legend>
			<form method="POST">
			'.Admin::setData(array('function'=>'search')).$filterInputs.'
			<div class="clearfix"></div>
			<input type="submit" value="search"/>
			</form>
		</fieldset>';
		}
		if(static::$rowsPerPage>0)
		{
			$pager=static::getPager($filterConditions,$offset);
			$limit=static::$rowsPerPage;
		}
		$rs=\cms\Init::select('\cms\admin\AdminForm::getList()',static::$tableName,false,array(),$filterConditions,static::$orderFields,$offset,$limit);
		if($rs!==false)
		{
			$result.='
		<fieldset>
			<legend>'.static::$titlePlural.'</legend>'.$pager;
			$first=true;
			foreach($rs as $row)
			{
				self::$itemEditable=true;
				self::$itemDeletable=static::$enableDelete;
				$itemHTML=static::getListItem($row);
				if($first){$first=false;}else $result.='
			<div class="clearfix"></div><hr/>';
				if(self::$itemEditable)$result.='
			<label>'.$itemHTML.'</label><form method="POST">
				'.Admin::setData(array('function'=>'edit','id'=>$row['id'])).'
				<input type="submit" value="edit" />
			</form>';
				if(self::$itemDeletable) $result.='
			<form method="POST">'.Admin::setData(array('function'=>'delete','id'=>$row['id'])).'<input type="submit" value="delete" /></form>';
				if(static::$reorderField!==false)
				{
					$conditions=array('id'=>$row['id']);
					foreach(static::$reorderGroup as $n) $conditions[$n]=$row[$n];
					$result.='
			<div class="reorder-buttons">
				<form method="POST">'.Admin::setData(array('function'=>'reorder','conditions'=>$conditions,'direction'=>'up')).'<input type="submit" value="up" /></form>
				<form method="POST">'.Admin::setData(array('function'=>'reorder','conditions'=>$conditions,'direction'=>'down')).'<input type="submit" value="down" /></form>
			</div>';
				}
			}
			if(static::$enableCreate)$result.='
			<div class="clearfix"></div>
			<form method="POST">
				'.Admin::setData(array('function'=>'edit','id'=>false)).'
				<input type="submit" value="New '.static::$title.'" />
			</form>';
			$result.='
		</fieldset>';
		}
		else $result='
		<fieldset>
			<legend>Error</legend>
			Error loading records.
		</fieldset>';
		return $result;
	}

	private final static function getPager($filterConditions,&$offset)
	{
		$result='
			';
		$listPage=0;
		if(isset($_GET['page']))
		{
			$listPage=($_GET['page']);
			if(is_numeric($listPage))
			{
				$listPage=floor($listPage)-1;
				if($listPage<0)$listPage=0;
			}else $listPage=0;
		}
		else if(isset(self::$session['list_page']))
		{
			$listPage=self::$session['list_page'];
		}
		else $listPage=0;
		$pageCount=1;
		$rs=\cms\Init::select('\cms\admin\AdminForm::getPager()',static::$tableName,array(),array('row_count'=>'COUNT(`id`)'),$filterConditions);
		if($rs===false || $rs->rowCount()==0)
		{
			$listPage=0;
			$pageCount=1;
		}
		else
		{
			$row=$rs->fetch(\PDO::FETCH_ASSOC);
			$pageCount=ceil($row['row_count']/static::$rowsPerPage);
			if($pageCount==0)$pageCount=1;
			if($listPage>$pageCount-1) $listPage=$pageCount-1;
			if($pageCount>1)
			{
				if($listPage>0)
				{
					$result.='<a href="'.self::$url.'?page=1">&#060;&#060;</a> <a href="'.self::$url.'?page='.$listPage.'">&#060;</a> ';
				}
				$result.='page '.($listPage+1).' of '.$pageCount;
				if($listPage+1<$pageCount)
				{
					$result.=' <a href="'.self::$url.'?page='.($listPage+2).'">&#062;</a> <a href="'.self::$url.'?page='.$pageCount.'">&#062;&#062;</a>';
				}
				$result.='
					<div class="clearfix"></div><hr/>';
			}
		}
		$offset=static::$rowsPerPage*$listPage;
		self::$session['list_page']=$listPage;
		return $result;
	}

	private final static function getItem($id=false)
	{
		$result='';
		$cancelForm='
		<form method="POST">
		'.Admin::setData(array('function'=>'cancel')).'
		<input type="submit" value="back to search" />
		</form>';
		$legend='';
		$data=false;

		if($id===false)
		{
			$legend='New '.static::$title;
		}
		else
		{
			$legend='Edit '.static::$title;
			$sql='SELECT * FROM `'.static::$tableName.'` WHERE `id`=\''.$id.'\'';
			$rs=\cms\Init::query('\cms\admin\AdminForm::getItem()',$sql);
			if($rs!==false)
			{
				if($rs->rowCount()>0)
				{
					$data=$rs->fetch(\PDO::FETCH_ASSOC);
				}
				else $result='
				Item not found.';
			}
			else $result='
				Error loading item.';
		}
		if($result=='')
		{
			$reorderConditions=false;
			if(static::$reorderField!==false)
			{
				$reorderConditions=array();
				foreach(static::$reorderGroup as $n) $reorderConditions[$n]=$row[$n];
			}
			self::$itemDeletable=static::$enableDelete;
			$result='
		<fieldset>'.$cancelForm.'
		<legend>'.$legend.'</legend>'.static::getItemMarkup($data).'
		<form method="POST" enctype="multipart/form-data">
		'.Admin::setData(array('function'=>'update','id'=>$id,'reorder_conditions'=>$reorderConditions)).static::getItemInputs($data).'
		<div class="clearfix"></div>
		<input type="submit" value="save" />
		</form>
		<div class="clearfix"></div>';
			if($id!==false && self::$itemDeletable)
			{
				$result.='
		<form method="POST">
		'.Admin::setData(array('function'=>'delete','id'=>$id)).'
		<input type="submit" value="delete" />
		</form>';
			}
			$result.='
		<div class="clearfix"></div>'.$cancelForm.'
		</fieldset>';
		}
		else $result='
		<fieldset><legend>Error</legend>'.$result.$cancelForm.'
		</fieldset>';
		return $result;
	}

	/**
	 * @param array $mappedFields field names that have already been mapped to items in $values
	 */
	private final static function getWhereClause(&$conditions,&$values,&$i,$mappedFields=array())
	{
		$result='';
		foreach($conditions as $n=>&$v)
		{
			$vIndex='';
			if(isset($mappedFields[$n]))
			{
				$vIndex=$mappedFields[$n];
			}
			else $vIndex='v'.$i++;
			$values[$vIndex]=$v;
			$v=$vIndex;
			$result.=$delim.'`'.$n.'`=:'.$vIndex;
			$delim=' AND ';
		}
		if($result!='')$result=' WHERE ('.$result.')';
		return $result;
	}
	
	private final static function getOrderFieldSQL(&$fields,&$values,&$i)
	{
		$whereClause='';
		$delim='';
		if(count(static::$reorderGroup)>0)
		{
			$conditions=array();
			foreach(static::$reorderGroup as $n) $conditions[$n]=isset($fields[$n])?$values[$fields[$n]]:'';
			$whereClause=self::getWhereClause($conditions,$values,$i,$fields);
		}
		return',`'.static::$reorderField.'`=IFNULL((SELECT o FROM (SELECT MAX(`'.static::$reorderField.'`) o from `'.static::$tableName.'`'.$whereClause.') i),0)+1';
	}

	private final static function doUpdate($data)
	{
		$id=$data['id'];
		$fields=static::getUpdateFields($id);
		if(is_array($fields) && count($fields)>0)
		{
			$values=array();
			$i=1;
			$sql='`'.static::$tableName.'` SET ';
			$delim='';
			foreach($fields as $n=>&$v)
			{
				$vIndex='v'.$i++;
				$values[$vIndex]=$v;
				$v=$vIndex;
				$sql.=$delim.'`'.$n.'`=:'.$vIndex;
				$delim=',';
			}
			if($id===false)
			{
				$sql='INSERT INTO '.$sql;
				if(static::$reorderField!==false) $sql.=self::getOrderFieldSQL($fields,$values,$i);
			}
			else
			{
				$sql='UPDATE '.$sql;
				if(static::$reorderField!==false)
				{
					$conditions=$data['reorder_conditions'];
					$doReorder=false;
					foreach($conditions as $n=>$v)
					{
						if(isset($fields[$n])&&$fields[$n]!=$v)
						{//reorder group has changed
							$doReorder=true;
							break;
						}
					}
					if($doReorder)$sql.=self::getOrderFieldSQL($fields,$values,$i);
				}
				$vIndex='v'.$i++;
				$values[$vIndex]=$id;
				$sql.=' WHERE `id`=:'.$vIndex;
			}
			\cms\Init::prepareAndExecute('\cms\admin\AdminForm::doUpdate()',$sql,$values);
		}
	}
	
	private final static function reorder($data)
	{
		$conditions=$data['conditions'];
		$id=$conditions['id'];
		$direction=$data['direction']=='up'?array('-1','max','<'):array('+1','min','>');
		$values=array();
		$delim='';
		$i=1;
		$whereClause=self::getWhereClause($conditions,$values,$i); //values in $conditions get set to keys of $values
		//It's ugly, but it works - one query to rule them all
		$sql='
UPDATE `'.static::$tableName.'`
SET `'.static::$reorderField.'`=
IF(`id`=:'.$conditions['id'].',
	`'.static::$reorderField.'`'.$direction[0].',
	(SELECT max(`'.static::$reorderField.'`) FROM (SELECT `'.static::$reorderField.'` FROM `'.static::$tableName.'` '.$whereClause.') AS `a`))
'.$whereClause.' OR 
`'.static::$reorderField.'`=
(
	(SELECT '.$direction[1].'(`'.static::$reorderField.'`) FROM
		(SELECT `'.static::$reorderField.'` FROM `'.static::$tableName.'` WHERE `'.static::$reorderField.'` '.$direction[2].'
			(SELECT max(`'.static::$reorderField.'`) FROM 
				(SELECT `'.static::$reorderField.'` FROM `'.static::$tableName.'` '.$whereClause.') AS `B`
			)
		) AS `C`
	)
)';
		\cms\Init::prepareAndExecute('\cms\admin\AdminForm::reorder()',$sql,$values);
	}
}
?>
