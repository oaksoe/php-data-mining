<?php

include_once('DM.class.php');
include_once('fp_growth.class.php');

class DM_Association extends DM{
	
	private $alg;
	private $fpSet;
	private $dbScan1_results;
	private $dbScan2_results;
	
	public function set_FP_Growth_Attributes($dbScan1_results, $dbScan2_results){
		$this->dbScan1_results= $dbScan1_results;
		$this->dbScan2_results= $dbScan2_results;
	}
				
	public function mine($type){
		
		if($type == "FP_Growth"){
			
			$fpg= new FP_Growth();
			$fpg->construct_FP_Tree($this->dbScan1_results, $this->dbScan2_results);
			
			$ptnNull= array();
			
			$fpg->generate_Frequent_Patterns($ptnNull);
			
			$this->fpSet= $fpg->getFreqPatternSet();
		}
		
	}
	
	public function getFreqPatternSet(){
		return $this->fpSet;
	}
	
	public function generate_patternset_ID($time_ID){
		
		$db= new DB($this->target_db);
		
		$query = "insert into patternset (time_ID) values ('$time_ID')";
		
		//echo $query;	
		
		$db->insert($query);
		
		$query= "select patternset_ID from patternset order by patternset_ID desc limit 0,1";
		
		//echo $query;	
		
		$patternset_ID= $db->retrieveElement($query); 
		return $patternset_ID;
	}
		
}


?>