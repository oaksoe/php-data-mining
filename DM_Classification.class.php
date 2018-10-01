<?php

include_once('DM.class.php');
include_once('c4_5.class.php');
include_once('k_nearest_neighbours.class.php');

class DM_Classification extends DM{
	
	private $alg;
	private $nodes;
	private $edges;
	private $trainingTuples;
	private $targetName;
	private $attrs;
	private $attrVals;
	private $attrType;	
	private $clusterSetTuples;
	private $clusterSet;
	private $meanTuples;
	private $inputTuple;
	private $k;
	private $nearestNeighbours;
	
	public function set_C4_5_Attributes($trainingTuples, $targetName, $attrs, $attrVals){
		$this->trainingTuples= $trainingTuples;
		$this->targetName= $targetName;
		$this->attrs= $attrs;
		$this->attrVals= $attrVals;
	}
	
	public function set_NN_Attributes($clusterSetTuples, $clusterSet, $meanTuples, $inputTuple, $attrs, $attrType, $k){
		$this->clusterSetTuples= $clusterSetTuples;
		$this->clusterSet= $clusterSet;
		$this->meanTuples= $meanTuples;
		$this->inputTuple= $inputTuple;
		$this->attrs= $attrs;
		$this->attrType= $attrType;
		$this->k= $k;
	}
	
	public function mine($type){
		
		$classArr= array(0,1,2);
		
		if($type == "C4_5"){
			
			$this->alg= new C4_5($this->trainingTuples, $classArr, $this->targetName);
			$this->alg->process_C4_5($this->attrs, $this->attrVals);
			
			$this->nodes= $this->alg->getNodes();
			$this->edges= $this->alg->getEdges();	
 			
 			//print_r($this->nodes);
		}
		else if($type == "NN"){
			
			$this->alg= new K_Nearest_Neighbours();
			
			$this->nearestNeighbours= $this->alg->get_K_Nearest_Neighbours($this->clusterSetTuples, $this->clusterSet, $this->meanTuples, $this->inputTuple, $this->attrs, $this->attrType, $this->k);
		}
	}
	
	public function getNodes(){
		return $this->nodes;
	}
	
	public function getEdges(){
		return $this->edges;
	}
	
	public function getNearestNeighbours(){
		return $this->nearestNeighbours;
	}
	
	public function generate_decisionTree_ID($time_ID, $network_ID, $targetName){
		
		$db= new DB($this->target_db);
		
		$query = "insert into decisiontree (time_ID, network_ID, targetAttribute) values ('$time_ID', '$network_ID', '$targetName')";
		
		//echo $query;	
		
		$db->insert($query);
		
		$query= "select decisiontree_ID from decisiontree order by decisionTree_ID desc limit 0,1";
		
		//echo $query;	
		
		$decisionTree_ID= $db->retrieveElement($query); 
		return $decisionTree_ID;
	}
		
}


?>