<?php

class K_Nearest_Neighbours{
	
	public function __construct()	{}
	
	private function calcEuclideanDist($tuple1, $tuple2, $attrs, $attrType){
			
		$euclideanDist=0;
				
		for($x=0;$x<count($attrs);$x++){
						
			if($attrType[$attrs[$x]] == 0 || $attrType[$attrs[$x]] == 2){
				
				if($tuple1[$attrs[$x]] == $tuple2[$attrs[$x]])
					$tempDist= 0;
				else
					$tempDist= 1;							
			}
			else{
				$tempDist= abs($tuple2[$attrs[$x]] - $tuple1[$attrs[$x]]);
			}
			
			$euclideanDist += pow($tempDist, 2);
			
			
		}
		//echo "<br> euclideanDist: " . sqrt($euclideanDist);
		return sqrt($euclideanDist);
	}
	
	public function get_K_Nearest_Neighbours($clusterSetTuples, $clusterSet, $meanTuples, $inputTuple, $attrs, $attrType, $k){
		
		$nearest_neighbours= array();
		
		$nearestDist= $this->calcEuclideanDist($inputTuple, $meanTuples[0], $attrs, $attrType);
		
		$nearestClusterInd= 0;
												
		for($y=1;$y<count($clusterSet);$y++){
							
			$tempDist= $this->calcEuclideanDist($inputTuple, $meanTuples[$y], $attrs, $attrType);
			
			if($nearestDist > $tempDist){
				$nearestDist= $tempDist;
				$nearestClusterInd= $y; 
			}
		}
		
		$nearestCluster= $clusterSet[$nearestClusterInd];
		
		for($x=0;$x<$k;$x++){
			
			if($nearestCluster){
				$nearestDist= $this->calcEuclideanDist($inputTuple, $clusterSetTuples[$nearestCluster[0]], $attrs, $attrType);
				$nearestNeighbourInd= $nearestCluster[0];
				$removeInd= 0;
				
				for($y=1;$y<count($nearestCluster);$y++){
					
					$tempDist= $this->calcEuclideanDist($inputTuple, $clusterSetTuples[$nearestCluster[$y]], $attrs, $attrType);
					
					if($nearestDist > $tempDist){
						$nearestDist= $tempDist;
						$nearestNeighbourInd= $nearestCluster[$y]; 
						$removeInd= $y;
					}
					
				}
				
				$nearest_neighbours[$x]= $nearestNeighbourInd;
				array_splice($nearestCluster, $removeInd, 1);
			}
		}
		
		return $nearest_neighbours;		
	}

}

?>