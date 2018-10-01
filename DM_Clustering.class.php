<?php

include_once('DM.class.php');
include_once('k_means.class.php');

class DM_Clustering extends DM{
	
	private $alg;
	private $clusters;
	private $meanTuples;
	private $trainingTuples;
	private $attrs;
	private $attrVals;
	private $attrType;
	private $attrDep;	
	
	public function set_K_Means_Attributes($trainingTuples, $attrs, $attrVals, $attrType, $attrDep){
		$this->trainingTuples= $trainingTuples;
		$this->attrs= $attrs;
		$this->attrVals= $attrVals;
		$this->attrType= $attrType;
		$this->attrDep= $attrDep;
	}
	
	public function mine($type){
		
		if($type == "K-means"){
			
			$this->alg= new K_means($this->attrs, $this->attrVals, $this->attrType, $this->attrDep);
			$this->alg->process_G_Means($this->trainingTuples);
			
			$this->clusters= $this->alg->getClusters();
			$this->meanTuples= $this->alg->getMeanTuples();

		}
	}
	
	public function getClusters(){
		return $this->clusters;
	}
	
	public function getMeanTuples(){
		return $this->meanTuples;
	}
	
	public function generate_clusterset_ID($time_ID, $network_ID, $type){
		
		$db= new DB($this->target_db);
		
		$query = "insert into clusterset (time_ID, network_ID, type) values ('$time_ID', '$network_ID', '$type')";
		
		//echo $query;	
		
		$db->insert($query);
		
		$query= "select clusterset_ID from clusterset order by clusterset_ID desc limit 0,1";
		
		//echo $query;	
		
		$clusterset_ID= $db->retrieveElement($query); 
		return $clusterset_ID;
	}	
}


?>