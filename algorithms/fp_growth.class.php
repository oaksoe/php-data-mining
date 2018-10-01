<?php

class FP_Growth{
	
	private $minSupport;
	private $fp_tree;
	private $fp_headerTable;
	private $cond_fp_tree;
	private $cond_headerTable;
	private $tree;
	private $nodeCount;
	private $headerTable; 
	private $freqPatternSet; 
	
	public function __construct(){
		$this->minSupport=2;
		$this->nodeCount=0;
		$this->freqPatternSet=array();
	}
	
	public function getTree(){
		return $this->tree;
	}
	
	public function getHeaderTable(){
		return $this->headerTable;
	}
	
	public function getFreqPatternSet(){
		return $this->freqPatternSet;
	}
	
	private function getIndex($elem, $arr){
		
		for($i=0;$i<count($arr);$i++)
			if($elem == $arr[$i])
			 	return $i;
		return -1;
		
	}
	
	private function getIndex2($elem, $objArr, $objAttrName){
		
		for($i=0;$i<count($objArr);$i++)
			if($elem == $objArr[$i]->$objAttrName)
			 	return $i;
		return -1;
		
	}
	
	private function insertTree($itemSet, $parentNodeIndex, $is_cond_tree, $cond_support){
		
		$first= $itemSet[0];
		$remSet= $itemSet;					//$remSet -> the remaining portion of $itemSet or $sorted_NetworkIDSet
		array_splice($remSet, 0, 1);
		
		$exist= false;
		
		$parentNode= $this->tree[$parentNodeIndex];
				
		if($parentNode->hasChild()){
			
			for($i=0;$i<count($parentNode->childNodes);$i++){
				
				$childNodeIndex= $parentNode->childNodes[$i];
				
				$childNode= $this->tree[$childNodeIndex];
				if($childNode->itemID == $first){
					
					if($is_cond_tree)
						$this->tree[$childNodeIndex]->support += $cond_support;
					else
						$this->tree[$childNodeIndex]->support++;
					$nodeIndex= $childNodeIndex;
					$exist=true;
					break;
				}
			}
		}
		
		if(!$exist){
			
			$newNode= new Node(2);
			$newNode->nodeID= $this->nodeCount;
			$newNode->parentNodeID= $parentNodeIndex; 
			$newNode->itemID= $first;
			
			if($is_cond_tree)
				$newNode->support = $cond_support;
			else
				$newNode->support= 1;
			
			$this->tree[$parentNodeIndex]->childNodes[]= $newNode->nodeID;
			
			$headerIndex= $this->getIndex2($newNode->itemID, $this->headerTable, "itemID");
			$this->headerTable[$headerIndex]->nodeLinks[]= $newNode->nodeID;
			
			$nodeIndex= $newNode->nodeID;
			
			$this->tree[$this->nodeCount++]= $newNode;
		}
		
		if(count($remSet)!=0)
			$this->insertTree($remSet, $nodeIndex, $is_cond_tree, $cond_support);
	}
	
	public function construct_FP_Tree($dbScan1_results, $dbScan2_results){
			
		$itemSet= array();
		$support= array();
		
		//from the db first scan results, collect $itemSet, the set of frequent items and their support counts
		
		$i= 0;
		for($j=0;$j<count($dbScan1_results);$j++) {
			
			$index= $this->getIndex($dbScan1_results[$j]['network_ID'], $itemSet);
		    if($index==-1){
				
				if(!$support[$i])
			    	$support[$i]=0;
			    $support[$i]++;
				$itemSet[$i++]= $dbScan1_results[$j]['network_ID'];	
				
			}
		    else{
		    	$support[$index]++;
		    }
		}
		
		for($i=0;$i<count($itemSet);$i++){
			if($support[$i]<$this->minSupport){
				array_splice($itemSet, $i, 1);
				array_splice($support, $i, 1);
			}
		}
		
		$itemSet_support= array();
		for($i=0;$i<count($itemSet);$i++)
			$itemSet_support[$itemSet[$i]]= $support[$i];
				
		//Sort $itemSet_support in support count descending order. arsort sorts the array in value (support) descending order
		arsort($itemSet_support);
		
		$i=0;
		while ($itemSet_support_elem = current($itemSet_support)) {
		    $l1_itemSet[$i]= key($itemSet_support);
		    $l1_support[$i]= $itemSet_support[key($itemSet_support)];
		    $i++;
		    next($itemSet_support);
		}
				
		//Initialize the root of an FP-tree as null
		$root= new Node(1);			// 1 for root, 2 for internal nodes, 3 for leaves
		$root->nodeID= 0;
		$root->parentNodeID= -1;
		//Initialize the tree
		$this->tree= array();
		$this->nodeCount=0;
		$this->tree[$this->nodeCount++]= $root;
		
		
		//Initialize the header table
		$this->headerTable= array();
		
		for($i=0;$i<count($l1_itemSet);$i++){
			$header= new Header($l1_itemSet[$i], $l1_support[$i]);
			$this->headerTable[$i]= $header;
			
		}
		
		//Use db second scan results
		for($x=0;$x<count($dbScan2_results);$x++) {
			$networkIDSet_str= $dbScan2_results[$x]['networks'];
			//echo "<br>" . $networkIDSet_str . "<br>";
			$networkIDSet= array();
			$j=0;
			$ID=0;
			$step=1;
		
			for($i=0;$i<strlen($networkIDSet_str);$i++){
				
				if($networkIDSet_str[$i]!='-'){
					$ID = $ID * $step + intval($networkIDSet_str[$i]);
					$step *= 10;
				}
				else{
					$networkIDSet[$j++]= $ID;
					$ID=0;
					$step=1;
				}
			}
			$networkIDSet[$j]= $ID;

			//Select and sort the frequent items in each record or transaction
			
			for($i=0;$i<count($networkIDSet);$i++){
				$index= $this->getIndex($networkIDSet[$i], $l1_itemSet);
				
				if($index == -1)
					array_splice($networkIDSet, $i, 1);	
			}
			
			if(count($networkIDSet)!=0){
				
				$sorted_networkIDSet= array();
				$k= 0;
				$isComplete= false;
				
				for($i=0;$i<count($l1_itemSet);$i++){
					
					if($isComplete)
						break;
					else{
						for($j=0;$j<count($networkIDSet);$j++){
							
							if($l1_itemSet[$i]==$networkIDSet[$j]){
								$sorted_networkIDSet[$k++]= $networkIDSet[$j];
								
								if($k == count($networkIDSet)) 
									$isComplete= true;
								break;
							}
						}
					}
				}
				
				$this->insertTree($sorted_networkIDSet, 0, false, 0);
			}
		}
		$this->fp_tree= $this->tree;
		$this->fp_headerTable= $this->headerTable;
	}
	
	private function getParentItemIDs($tree, $nodeID){
		
		$parentItemIDs= array();
		$parentNodeID= $tree[$nodeID]->parentNodeID;
		
		while($parentNodeID != 0){		//while the node's parent is not a root
		
			$itemID= $tree[$parentNodeID]->itemID;
			$parentItemIDs["$itemID"]= $itemID;
			
			$parentNodeID= $tree[$parentNodeID]->parentNodeID;
		}
		
		$parentItemIDs= array_reverse($parentItemIDs, true);
		
		return $parentItemIDs;
	}
	
	public function generate_Frequent_Patterns($ptnAlpha){
		$this->mine_Patterns($this->fp_tree, $this->fp_headerTable, $ptnAlpha);
	}
	
	public function mine_Patterns($cond_FP_Tree, $cond_headerTable, $ptnAlpha){
		
		for($i=0;$i<count($cond_headerTable);$i++){
			
			$ptnBeta= array();
			$ptnItemID= array();
			
			$itemID= $cond_headerTable[$i]->itemID;
			$support= $cond_headerTable[$i]->support;
			$ptnItemID["$itemID"]= $itemID;
			
			if(count($ptnAlpha)==0)		
				$ptnBeta['pattern']= $ptnItemID;
			else
				$ptnBeta['pattern']= $ptnItemID + $ptnAlpha['pattern'];
			$ptnBeta['support']= $support;
			
			//if(count($ptnBeta['pattern']) > 1)
			$this->freqPatternSet[]= $ptnBeta;
			
			$nodeLinks= $cond_headerTable[$i]->nodeLinks;
			
			//construct $ptnBeta's conditional pattern base
			$cond_ptn_set= array();
			
			for($j=0;$j<count($nodeLinks);$j++){
				
				$nodeID= $nodeLinks[$j];
				
				$cond_ptn= array();
				$parentItemIDs= $this->getParentItemIDs($cond_FP_Tree, $nodeID);
				
				if(count($parentItemIDs)!=0){
					$cond_ptn['pattern']= $parentItemIDs;
					$cond_ptn['support']= $cond_FP_Tree[$nodeID]->support;
					
					$cond_ptn_set[]= $cond_ptn;
				}
			}
				
			//Remove the item less than minimum support count
			
			$itemSet= array();
			$supportSet= array();
			
			$y=0;
			for($x=0;$x<count($cond_ptn_set);$x++){
				
				$itemIDs= $cond_ptn_set[$x]['pattern'];
				$support= $cond_ptn_set[$x]['support'];
								
				while ($itemIDs_elem = current($itemIDs)) {
				    $itemID= key($itemIDs);
				    
				    $index= $this->getIndex($itemID, $itemSet);
				    if($index==-1){
						
						if(!$supportSet[$y])
					    	$supportSet[$y]=0;
					    $supportSet[$y] += $support;
						$itemSet[$y++]= $itemID;	
						
					}
				    else{
				    	$supportSet[$index] += $support;
				    }
				    
				    next($itemIDs);
				}							
			}
									
			//Copy the items of $cond_ptn_set to $freq_cond_ptn_set if the item has required minimum support
			$freq_cond_ptn_set= array();
			$freq_itemSet= array();
			$freq_supportSet= array();
										
		    for($x=0;$x<count($itemSet);$x++){
		    	
				if($supportSet[$x]>=$this->minSupport){
					
					$includeItemID= $itemSet[$x];
					$freq_itemSet[]= $itemSet[$x];
					$freq_supportSet[]= $supportSet[$x];
					
					for($y=0;$y<count($cond_ptn_set);$y++){
						
						$itemIDs= $cond_ptn_set[$y]['pattern'];
						$support= $cond_ptn_set[$y]['support'];
						
						while ($itemIDs_elem = current($itemIDs)) {
						    $itemID= key($itemIDs);
							if($itemID == $includeItemID){
								$freq_cond_ptn_set[$y]['pattern'][]= $itemID;
								$freq_cond_ptn_set[$y]['support']= $support;
								break;
							}
							next($itemIDs);
						}
					}
				}
			}
			
			if(count($freq_cond_ptn_set)!=0){
				
				array_splice($freq_cond_ptn_set, 0, 0);		//Compact the array to remove gaps or empty cells in between
								
				//construct $ptnBeta's conditional FP-tree
				
				//Initialize the root of an FP-tree as null
				$root= new Node(1);			// 1 for root, 2 for internal nodes, 3 for leaves
				$root->nodeID= 0;
				$root->parentNodeID= -1;
				//Initialize the tree
				$this->tree= array();
				$this->nodeCount=0;
				$this->tree[$this->nodeCount++]= $root;
				
				
				//Initialize the header table
				$this->headerTable= array();
				
				for($x=0;$x<count($freq_itemSet);$x++){
					$header= new Header($freq_itemSet[$x], $freq_supportSet[$x]);
					$this->headerTable[$x]= $header;
					
				}
				
				for($x=0;$x<count($freq_cond_ptn_set);$x++){
					
					$this->insertTree($freq_cond_ptn_set[$x]['pattern'], 0, true, $freq_cond_ptn_set[$x]['support']);
					
				}
				
				$this->cond_fp_tree= $this->tree;
				$this->cond_headerTable= $this->headerTable;
				
				if(count($this->cond_fp_tree)!=0)
					$this->mine_Patterns($this->cond_fp_tree, $this->cond_headerTable, $ptnBeta);		
			}	
		}
	}
}

class Node{
	
	public $nodeID;
	public $parentNodeID;
	public $itemID;
	public $support;
	public $childNodes;
	public $type;
	
	public function __construct($type){
		$this->childNodes= array();
		$this->type= $type;
	}
	
	public function hasChild(){
		if(count($this->childNodes)==0)
			return false;
		return true;
	}
	
	public function isRoot(){
		if($this->type==1)
			return true;
		return false;
	}
	
	public function isLeaf(){
		if($this->type==3)
			return true;
		return false;
	}
}

class Header{
	
	public $itemID;
	public $support;
	public $nodeLinks;
	
	public function __construct($itemID, $support){
		$this->itemID= $itemID;
		$this->support= $support;
		$this->nodeLinks= array();
	}
}



?>