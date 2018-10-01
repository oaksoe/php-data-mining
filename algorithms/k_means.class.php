<?php

class K_means{
	
	private $attrs;
	private $attrVals;
	private $attrType;
	private $attrDep;
	private $clusters;
	private $meanTuples;
	private $clusterCount;
		
	public function __construct($attrs, $attrVals, $attrType, $attrDep)	{
		
		$this->attrs= $attrs;
		$this->attrVals= $attrVals;
		$this->attrType= $attrType;
		$this->attrDep= $attrDep;
		$this->clusters= array();
		$this->meanTuples= array();
		$this->classCount= 0;
	}
	
	public function getClusters(){
		return $this->clusters;
	}
	
	public function getMeanTuples(){
		return $this->meanTuples;
	}
	
	private function checkSeeds($x, $seeds, $tempSeed, $max){
		
		for($y=0;$y<$x;$y++){
			if($tempSeed == $seeds[$y]){
				$tempSeed= rand(0,$max);
				
				return $this->checkSeeds($x, $seeds, $tempSeed, $max);
			}
		}
		return $tempSeed;
	}
	
	private function calcEuclideanDist($tuple, $mean){	
		$euclideanDist=0;
				
		for($x=0;$x<count($this->attrs);$x++){
						
			if($this->attrType[$this->attrs[$x]] == 0 || $this->attrType[$this->attrs[$x]] == 2){
				
				if($tuple[$this->attrs[$x]] == $mean[$this->attrs[$x]])
					$tempDist= 0;
				else
					$tempDist= 1;							
			}
			else{
				$tempDist= abs($mean[$this->attrs[$x]] - $tuple[$this->attrs[$x]]);
			}
			
			$euclideanDist += pow($tempDist, 2);
			
			
		}
		
		return sqrt($euclideanDist);
	}
	
	private function calcMode($sample, $cluster, $attrInd, $depAttr, $depAttrVal){
		
		$freq= array();
		$vals= $this->attrVals[$attrInd];
		
		for($x=0;$x<count($vals);$x++)				
			$freq[$vals[$x]]= 0;
			
		for($x=0;$x<count($cluster);$x++){
			
			if($depAttr){
				if($sample[$cluster[$x]][$depAttr] == $depAttrVal)
					$freq[$sample[$cluster[$x]][$attrInd]]++;
			}
			else
				$freq[$sample[$cluster[$x]][$attrInd]]++;
			
		}
		
		$mode= $freq[$vals[0]];
		$modeVal= $vals[0];
		
		for($x=0;$x<count($vals);$x++){
			if($mode < $freq[$vals[$x]]){
				$mode= $freq[$vals[$x]];
				$modeVal= $vals[$x];
			}
		}
		
		return $modeVal;
		
	}
	
	private function calcMean($sample, $cluster, $attrInd){
		
		$meanVal=0;
		for($x=0;$x<count($cluster);$x++){
			$meanVal += $sample[$cluster[$x]][$attrInd];
			
		}
		$meanVal /= $x;
		
		return $meanVal;
	}
	
	private function process_K_Means($sample, $k){
		
		$sampleCount= count($sample);
		$seeds= array();
				
		//1. Random pick a seed for each cluster k
		for($x=0;$x<$k;$x++){
			$tempSeed= rand(0,$sampleCount-1);
			
			$seeds[$x]= $this->checkSeeds($x, $seeds, $tempSeed, $sampleCount-1);
			$this->meanTuples[$x]= $sample[$seeds[$x]];		
		}
		
		$change= true;
		$prevCluster= -1;
		$prevClusterInd= -1;
				
		while($change){
						
			$change= false;
					
			//2. Assign every point to its closest seeds
								
			for($x=0;$x<$sampleCount;$x++){
				
				$dist= array();
				$nearestDist= $this->calcEuclideanDist($sample[$x], $this->meanTuples[0]);
				$dist[$x][0]= $nearestDist;
				$nearestSeed= $seeds[0];
				$nearestCluster= 0;
				$found= false;
												
				for($y=1;$y<$k;$y++){
									
					$tempDist= $this->calcEuclideanDist($sample[$x], $this->meanTuples[$y]);
					$dist[$x][$y]= $tempDist;
					if($nearestDist > $tempDist){
						$nearestDist= $tempDist;
						$nearestSeed= $seeds[$y];
						$nearestCluster= $y; 
					}
				}
						
				for($y=0;$y<$k;$y++){
					if(!$found){
						for($z=0;$z<count($this->clusters[$y]);$z++){
							if($x == $this->clusters[$y][$z]){
								$prevCluster= $y;
								$prevClusterInd= $z;
								$found= true;
								break;
							}
						}
					}
				}
				
				if($prevCluster != $nearestCluster){
					$change= true;
					if($prevClusterInd != -1)
						array_splice($this->clusters[$prevCluster], $prevClusterInd, 1);
					$this->clusters[$nearestCluster][]= $x;
				}
				
			}
			
			//3. Calculate the center or mean of each cluster
			
			for($x=0;$x<$k;$x++){
				for($y=0;$y<count($this->attrs);$y++){
					
					if($this->attrType[$this->attrs[$y]] == 0)
						$this->meanTuples[$x][$this->attrs[$y]] = $this->calcMode($sample, $this->clusters[$x], $this->attrs[$y], '', '');
					else if($this->attrType[$this->attrs[$y]] != 2)
						$this->meanTuples[$x][$this->attrs[$y]] = $this->calcMean($sample, $this->clusters[$x], $this->attrs[$y]);
				}
				
				for($y=0;$y<count($this->attrDep);$y++){
					
					$depAttr1= key($this->attrDep[$y]);
					$depAttr2= $this->attrDep[$y][$depAttr1];
					$depAttr2Val= $this->meanTuples[$x][$depAttr2];
					$this->meanTuples[$x][$depAttr1] = $this->calcMode($sample, $this->clusters[$x], $depAttr1, $depAttr2, $depAttr2Val);
				
				}
			}
		}
	}
	
	public function process_G_Means($sample){
		
		$this->process_K_Means($sample, 2);	
	}
}



?>