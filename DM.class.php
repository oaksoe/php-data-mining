<?php

include_once('DB.class.php');

abstract class DM {
	
	protected $source_db;
	protected $target_db;
	
	public function __construct($source, $target){
		$this->source_db= $source;
		$this->target_db= $target;
	}
	
	public function extract($columns, $table, $condition, $dataQty){
		
		$db= new DB($this->source_db);
		
		$query= "select $columns from $table where $condition";
		
		//echo $query;	
			
		if($dataQty == 1){
				
			$element= $db->retrieveElement($query);
			return $element;
		}
		else if($dataQty == 2){
			$row= $db->retrieveRow($query);
			return $row;
		}
		else if($dataQty == 3){
			$rows= $db->retrieveRows($query);
			return $rows;
		}
	}
	
	public function load($table, $columns, $records){
		
		$db= new DB($this->target_db);
		
		$strRows= "";
		
		for($i=0;$i<count($records);$i++)
			$strRows .= $records[$i] . ",";
		
		$strRows = substr($strRows, 0, strlen($strRows)-1);
				
		$query = "insert into $table " . $columns . " values " . $strRows;
		
		//echo $query;	
		
		$db->insert($query); 
		
	}
	
	public function generate_time_ID(){
		
		$db= new DB($this->target_db);
		
		$week= date("W");
		$month= date("m");
		$year= date("Y");
		
		$query = "insert into dmtime (week, month, year) values ('$week', '$month', '$year')";
		
		//echo $query;	
		
		$db->insert($query);
		
		$query= "select time_ID from dmtime order by time_ID desc limit 0,1";
		
		//echo $query;	
		
		$time_ID= $db->retrieveElement($query); 
		return $time_ID;
	}
		
	abstract protected function mine($type);
	
}

?>