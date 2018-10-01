<?php

class C4_5{
	
	private $rootSample;
	private $rootSampleCount;
	private $classes;
	private $classCount;
	private $nodes;
	private $edges;
	private $nodeCount;
	private $targetAttrName;
	
	public function __construct($sample, $classArr, $targetName){
		
		$this->rootSample= $sample;
		$this->rootSampleCount= count($sample);
		$this->classes= $classArr;
		$this->classCount= count($classArr);
		$this->nodes= array();
		$this->edges= array();
		$nodeCount=0;
		$this->targetAttrName= $targetName;
	}
	
	public function getNodes(){
		return $this->nodes;
	}
	
	public function getEdges(){
		return $this->edges;
	}
	
	private function getFreq($sample, $sampleCount){
		
		$freq= array(0,0,0);
		for($i=0, $j=0;$i<$sampleCount;$i++){
		
			$class= $sample[$i][$this->targetAttrName];
					
			$freq[$class]++;
		}
		
		return $freq;
	}
	
	private function getOneClass($freq){
		
		$emptyClassCount=0;
		for($i=0; $i<$this->classCount; $i++){
			if($freq[$this->classes[$i]]==0)
				$emptyClassCount++;
			else
				$class= $this->classes[$i];	
		}
		
		if($emptyClassCount!=$this->classCount-1)
			$class= -1;
		
		return $class;
		
	}
	
	private function fewCases($sampleCount){
		
		$threshold= 1; //intval(0.1 * $this->rootSampleCount);
		if($sampleCount < $threshold)
			return true;
		return false;
	}
	
	private function getFreqClass($freq){
		
		$maxFreq= $freq[$this->classes[0]];
		$freqClass= $this->classes[0];
		
		for($i=0; $i<$this->classCount; $i++){
			
			if($maxFreq<$freq[$this->classes[$i]]){
				$maxFreq= $freq[$this->classes[$i]];
				$freqClass= $this->classes[$i];
			}
		
		}
		return $freqClass;
	}
	
	private function getErrorVal($freq, $class){
		
		$errCount= 0;
		
		for($i=0; $i<$this->classCount; $i++){
			
			if($this->classes[$i] != $class)				
				$errCount += $freq[$this->classes[$i]];
					
		}
		return $errCount;
	}
	
	private function entropy($freq, $s){
		
		$info= 0;
		
		for($i=0; $i<$this->classCount; $i++){
			
			$temp = $freq[$this->classes[$i]] / $s;
			
			if($temp != 0)  
				$info += $temp * log($temp, 2);
					
		}
		
		$info *= -1;
		return $info;
	}
	
	private function subsetOf($sample, $sampleCount, $attrName, $attrVal){
		
		$subset= array();
		
		for($k=0;$k<$sampleCount;$k++){
			
			if($attrName == "age"){
			
				if($sample[$k]["$attrName"] > $attrVal-10 && $sample[$k]["$attrName"] <= $attrVal){
					$subset[]= $sample[$k];
									
				}
			}
			else{
				if($sample[$k]["$attrName"] == $attrVal){
					$subset[]= $sample[$k];
										
				}
			}
		}
		return $subset;
	}
	
	private function buildDecisionTree($sample, $parent_id, $predVal, $attrs, $attrVals){
		
		$sampleCount= count($sample);
				
		$error=0;
		
		$freq= $this->getFreq($sample, $sampleCount);
		
		$oneClass= $this->getOneClass($freq);
					
		$edge= array();
		$edge['node1']= $parent_id;
		$edge['predVal']= $predVal;
								
		if($this->fewCases($sampleCount) || $oneClass!=-1 || count($attrs) == 0){
			
			$node= array();
			$node['type']= 0;
			if($oneClass==-1 || count($attrs) == 0){
				$node['class']= $this->getFreqClass($freq);
				$node['error']= $this->getErrorVal($freq, $node['class']);
			}
			else{
				$node['class']= $oneClass;
				$node['error']= 0;
			} 
			$this->nodeCount++;
			$this->nodes["$this->nodeCount"]= $node;
			$edge['node2']= $this->nodeCount;
			$this->edges[]= $edge;
			
			return $node;
		}
		else{
			$node= array();
			$node['type']= 1;
			
			$bestGain= 0;
			$bestAttr= $attrs[0];
			$bestAttrInd=0;
			$sameGain= true;
			
			$info_T= $this->entropy($freq, $sampleCount);
						
			for($i=0;$i<count($attrs);$i++){
				
				$attrName= $attrs[$i];
				
				$attrValCount= count($attrVals["$attrName"]);
				
				$info_Ti= 0;
				
				for($j=0;$j<$attrValCount;$j++){
					
					$attrVal= $attrVals["$attrName"][$j];
					
					$subSample[$attrVal]= $this->subsetOf($sample, $sampleCount, $attrName, $attrVal);
					
					$subSampleCount= count($subSample[$attrVal]);
					
					if($subSampleCount != 0){
						$freq_j= $this->getFreq($subSample[$attrVal], $subSampleCount);
											
						$temp= $this->entropy($freq_j, $subSampleCount);
		
						$info_Ti += ($subSampleCount / $sampleCount) * $temp;
					}
				}
				
				$gain= $info_T - $info_Ti;
				if($bestGain < $gain){
					$bestGain= $gain;
					$bestAttr= $attrName;
					$bestAttrInd=$i;					
				}
				
				if($bestGain != $gain)
					$sameGain= false;
			}
			
			if($sameGain){
				$node= array();
				$node['type']= 0;
				
				$node['class']= $this->getFreqClass($freq);
				$node['error']= $this->getErrorVal($freq, $node['class']);
				
				$this->nodeCount++;
				$this->nodes["$this->nodeCount"]= $node;
				$edge['node2']= $this->nodeCount;
				$this->edges[]= $edge;
				
				return $node;
			}
			else{
				$node['predictor']= $bestAttr;
				$this->nodeCount++;
				$this->nodes["$this->nodeCount"]= $node;
				$nodeID= $this->nodeCount;
				$edge['node2']= $nodeID;
				$this->edges[]= $edge;
				
				$attrValCount= count($attrVals["$bestAttr"]);				
							
				$subAttrs= $attrs;
				$subAttrVals= $attrVals;
				
				array_splice($subAttrs, $bestAttrInd, 1);
				array_splice($subAttrVals, $bestAttrInd, 1);
							
				for($i=0;$i<$attrValCount;$i++){
					
					$attrVal= $attrVals["$bestAttr"][$i];
					$subSample= $this->subsetOf($sample, $sampleCount, $bestAttr, $attrVal);
					
					if(!$subSample){		
						$childNode= array();
						$childNode['type']= 0;
						$childNode['class']= 0;		//$this->getFreqClass($freq);
						$childNode['error']= 0;
						$this->nodeCount++;
						$this->nodes["$this->nodeCount"]= $childNode;
						$edge1= array();
						$edge1['node1']= $nodeID;
						$edge1['predVal']= $attrVal;
						$edge1['node2']= $this->nodeCount;
						$this->edges[]= $edge1;
						
					}
					else{				
						$childNode= $this->buildDecisionTree($subSample, $nodeID, $attrVal, $subAttrs, $subAttrVals);
					}
				}		
			}
		}
	}
	
	public function process_C4_5($attrs, $attrVals){
		
		$this->buildDecisionTree($this->rootSample, -1, "", $attrs, $attrVals);		
	}
}


?>