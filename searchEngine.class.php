<?php

include_once('DB.class.php');
include_once('process.class.php');
include_once('languageProcessor.class.php');

class SearchEngine extends Process{
		
	public function __construct(){
		
	}
	
	public function search($keyword, $context, $network_ID, $peopleInfo){
				
		if($context == "People")
			return $this->searchPeople($keyword, $context, $network_ID, $peopleInfo);
		else
			return $this->searchKnowledge($keyword, $context, $network_ID);
	}
	
	public function searchPeople($keyword, $context, $network_ID, $peopleInfo){
		
		$db= new DB("knowledgefile");
		
		$specificSearchStr= "";
		$networkStr= "";
		$openBr="";
		$closeBr="";
		
		if($peopleInfo){
			$nationality= $peopleInfo['lstNationality'];
			$country= $peopleInfo['lstCountry'];
			$city= $peopleInfo['lstCity'];
			$position= $peopleInfo['lstPosition'];
			$specialism= $peopleInfo['lstSpecialism'];
			$subSpecialism= $peopleInfo['lstSubSpecialism'];
			$company= $peopleInfo['lstCompany'];
			$college= $peopleInfo['lstCollege'];
			$specificSearchStr .= " and ";
			
			//print_r($peopleInfo);
			
			if($nationality != "selected") 
				$specificSearchStr .= "D.nationality_ID='" . $nationality . "' and "; 
			if($country != "selected") 
				$specificSearchStr .= "D.country_ID='" . $country . "' and ";
			if($city != "selected") 
				$specificSearchStr .= "D.city_ID='" . $city . "' and ";
			if($position != "selected") 
				$specificSearchStr .= "D.position_ID='" . $position . "' and ";
			if($specialism != "selected") 
				$specificSearchStr .= "D.specialism_ID='" . $specialism . "' and ";
			if($subSpecialism != "selected") 
				$specificSearchStr .= "D.sub_specialism_ID='" . $subSpecialism . "' and ";
			if($company != "selected") 
				$specificSearchStr .= "D.company_ID='" . $company . "' and ";
			else if($college != "selected")
				$specificSearchStr .= "D.company_ID='" . $college . "' and ";
			
			$specificSearchStr = substr($specificSearchStr, 0, strlen($specificSearchStr)-5);
		}
		
		if($network_ID){
			
			$openBr="(";
			$closeBr=")";
			$networkStr= " AND (
						A.user_ID
						IN (
						SELECT F.user_ID
						FROM user_network F
						WHERE F.network_ID = '$network_ID'
						)
						)";
		}	
		
		$query= "SELECT A.user_ID, B.*
				FROM user A, userinfo B, logininfo C
				WHERE " . $openBr . "(
				A.userinfo_ID = B.userinfo_ID
				AND B.userinfo_ID
				IN (
				SELECT D.userinfo_ID
				FROM userinfo D
				WHERE D.name LIKE (
				'%$keyword%'
				)" . $specificSearchStr . "
				)
				)
				OR (
				A.userinfo_ID = B.userinfo_ID
				AND A.logininfo_ID = C.logininfo_ID
				AND C.logininfo_ID = (
				SELECT E.logininfo_ID
				FROM logininfo E
				WHERE E.email = '$keyword' )
				)" . $closeBr . $networkStr . "
				GROUP BY A.user_ID";
		
		//echo $query;		
		$rows= $db->retrieveRows($query);
		return $rows;
	}
	
	public function indexedSearch($keyword, $network_ID){
		$lang= new LanguageProcessor();
		
		$tokens= $lang->tokenize($keyword);
		
		$db= new DB("kf_corpus");
		
		$lexicon_IDs= array();
		
		for($i=0;$i<count($tokens);$i++){
			$word= $tokens[$i];
			$query= "select * from lexicon where word = '$word'";
			$lexicon= $db->retrieveRow($query); 
			
			if($lexicon)
				$lexicon_IDs[]= $lexicon['lexicon_ID'];
		}
		
		$subQuery= "";
		for($i=0;$i<count($lexicon_IDs);$i++)
			$subQuery .= "lexicon_ID = '" . $lexicon_IDs[$i] . "' or ";
			
		$subQuery = substr($subQuery, 0, strlen($subQuery)-3);
		
		$query= "select * from knowledge_index where network_ID = '$network_ID' and ($subQuery)";
		$recs= $db->retrieveRows($query);
		
		//$query= "select distinct knowledge_ID from knowledge_index where network_ID = '$network_ID' and ($subQuery)";
		//$recs= $db->retrieveRows($query);
		
		//Prioritize the knowledge by awarding points
		$distinctKnowledgeIDs= array();
		$distinctKnowledge= array();
		
		for($i=0;$i<count($recs);$i++){
			$knowledge_ID= $recs[$i]['knowledge_ID'];
			$hits= $recs[$i]['hits'];
			
			if(!$lang->isMember($knowledge_ID, $distinctKnowledgeIDs)){
				$distinctKnowledgeIDs[]= $knowledge_ID;
				$distinctKnowledge["$knowledge_ID"]['hits']= $hits;
				$distinctKnowledge["$knowledge_ID"]['wordCount']= 1; 
			}
			else{
				$distinctKnowledge["$knowledge_ID"]['hits'] += $hits;
				$distinctKnowledge["$knowledge_ID"]['wordCount']++;
			} 			
		}
		
		for($i=0;$i<count($distinctKnowledgeIDs);$i++){
			$knowledge_ID= $distinctKnowledgeIDs[$i];
			$distinctKnowledge["$knowledge_ID"]['points']= $distinctKnowledge["$knowledge_ID"]['wordCount'] * 10000 + $distinctKnowledge["$knowledge_ID"]['hits']; 
		}
		
		//print_r($distinctKnowledge);
		
		$sortedKnowledge= array();
		$max= count($distinctKnowledgeIDs);	
		for($x=0;$x<$max;$x++){
			
			$maxKnowledge_ID= $distinctKnowledgeIDs[0];
			$maxPoints= $distinctKnowledge["$maxKnowledge_ID"]['points'];
			$removeInd= 0;
			
			for($y=1;$y<count($distinctKnowledgeIDs);$y++){
				
				$knowledge_ID= $distinctKnowledgeIDs[$y];
				$points= $distinctKnowledge["$knowledge_ID"]['points'];
				
				if($maxPoints < $points){
					$maxPoints= $points;
					$maxKnowledge_ID= $knowledge_ID; 
					$removeInd= $y;
				}
				
			}
			
			$sortedKnowledge[$x]= $maxKnowledge_ID;
			array_splice($distinctKnowledgeIDs, $removeInd, 1);
		}
		//echo "<br><br>";
		//print_r($sortedKnowledge);
	
		$db= new DB("knowledgefile");
		
		$prioritizedRecs= array();
		
		for($i=0;$i<count($sortedKnowledge);$i++){
			$knowledge_ID= $sortedKnowledge[$i];
			$query= "SELECT A.user_ID, B.name as userName, C.*, D.*, F.*
					FROM user A, userinfo B, user_network_knowledge C, knowledge D, network F
					WHERE A.userinfo_ID = B.userinfo_ID
					AND A.user_ID = C.user_ID
					AND C.knowledge_ID = D.knowledge_ID AND D.knowledge_ID = '$knowledge_ID' 
					AND C.network_ID = '$network_ID'" . 
					" AND F.network_ID = C.network_ID";
			//echo $query;
			$rec= $db->retrieveRow($query);
			$prioritizedRecs[]= $rec;
		}
		return $prioritizedRecs;	
	}
	
	public function searchKnowledge($keyword, $context, $network_ID){
		
		$db= new DB("knowledgefile");
		$networkStr= "";
		$contextStr= " (";
				
		if($network_ID)			
			$networkStr .= " AND C.network_ID = '$network_ID'";
		
		if($context == "Articles" || $context[0] == 1)
			$contextStr .= " E.knowledgeType = '1' OR ";
		if($context == "Q&A" || $context[1] == 1)
			$contextStr .= " E.knowledgeType = '2' OR ";
		if($context == "Discussions" || $context[2] == 1)
			$contextStr .= " E.knowledgeType = '3' OR ";	
		
		$contextStr = substr($contextStr, 0, strlen($contextStr)-4);
		$contextStr .= ") AND ";	
				
		$query= "SELECT A.user_ID, B.name as userName, C.*, D.*, F.*
				FROM user A, userinfo B, user_network_knowledge C, knowledge D, network F
				WHERE A.userinfo_ID = B.userinfo_ID
				AND A.user_ID = C.user_ID
				AND C.knowledge_ID = D.knowledge_ID" . $networkStr . 
				" AND D.knowledge_ID
				IN (
				
				SELECT E.knowledge_ID
				FROM knowledge E
				WHERE" . $contextStr . "(E.title LIKE (
				'%$keyword%'
				)
				OR E.content LIKE (
				'%$keyword%'
				))
				)
				AND F.network_ID = C.network_ID";
		//echo $query;
		$rows= $db->retrieveRows($query);
		return $rows;
				
		
	}
	
	public function recordUserActions($args){
		
		if($args['actionCase'] == 1){			
			if($args['actionType'] == "peopleSearched"){
				
				$searches= $this->retrieveUserGeneralAction($args['user_ID'], "peopleSearched");
				$searches++;
				$this->updateUserGeneralAction($args['user_ID'], "peopleSearched", $searches);
			}
			else if($args['actionType'] == "knowledgeSearched"){
				
				$searches= $this->retrieveUserGeneralAction($args['user_ID'], "knowledgeSearched");
				$searches++;
				$this->updateUserGeneralAction($args['user_ID'], "knowledgeSearched", $searches);
			}			
		}
		else if($args['actionCase'] == 2){			
			if($args['actionType'] == "peopleSearched"){
				
				$searches= $this->retrieveUserAction($args['user_ID'], $args['network_ID'], "peopleSearched");
				$searches++;
				$this->updateUserAction($args['user_ID'], $args['network_ID'], "peopleSearched", $searches);
			}
			else if($args['actionType'] == "knowledgeSearched"){
				
				$searches= $this->retrieveUserAction($args['user_ID'], $args['network_ID'], "knowledgeSearched");
				$searches++;
				$this->updateUserAction($args['user_ID'], $args['network_ID'], "knowledgeSearched", $searches);
			}
		}
	}
	
}


?>