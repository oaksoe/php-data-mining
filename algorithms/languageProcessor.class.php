<?php

include_once('DB.class.php');

class LanguageProcessor {
		
	public function __construct(){
		
	}
	
	public function tokenize($content){
				
		$db= new DB("kf_corpus");
		
		//Convert the content alphabets to lowercase
		$content= strtolower($content);
		$content= " $content ";
		//filter the punctuations
		//$punctuations= array("\.", "\,", "\:", "\;", "\-", "\?" , "\'", "\"", "\!", "\&", "\(", "\)", "\[", "\]", "\{", "\}");
		
		$pattern= "/[^a-z0-9]/";  
		$filteredContent= preg_replace($pattern, " ", $content);
		$content= $filteredContent;
		
			
		//echo "$content <br><br><br>";
		
		//filter the trivial and foul words
		$query= "select word from pronoun_lexicon";
		$pronouns= $db->retrieveRows($query);
		
		$query= "select word from preposition_lexicon";
		$prepositions= $db->retrieveRows($query);
		
		$query= "select word from conjunction_lexicon";
		$conjunctions= $db->retrieveRows($query);
		
		$query= "select word from other_lexicon";
		$others= $db->retrieveRows($query);
		
		$query= "select word from foul_lexicon";
		$fouls= $db->retrieveRows($query);
		
		$filterWords= array_merge($pronouns, $prepositions, $conjunctions, $others, $fouls);
							
		for($i=0;$i<count($filterWords);$i++){
			
			$pattern= "/ " . $filterWords[$i]['word'] . " /"; 
			$filteredContent= preg_replace($pattern, " ", $content);
			$content= $filteredContent;
		}
		//echo "$content <br><br><br>";
		$tokens = explode(" ", $content);
		
		$newTokens= array();
		for($i=0;$i<count($tokens);$i++){
			if($tokens[$i] != "")
				$newTokens[]= $tokens[$i];
		}
		
		return $newTokens;
	}
	
	public function indexKnowledge($network_ID, $knowledge_ID, $content){
		
		$tokens= $this->tokenize($content);
		
		$db= new DB("kf_corpus");		
		$query= "select * from lexicon";
		$lexicons= $db->retrieveRows($query);
		
		for($i=0;$i<count($tokens);$i++){
			
			$word= $tokens[$i];
			
			for($j=0;$j<count($lexicons);$j++){
				if($word == $lexicons[$j]['word']){
					$lexicon_ID= $lexicons[$j]['lexicon_ID'];
					break;
				}	
				
			}
			
			// if the word is new (not in lexicon table), insert the lexicon and retrieve back the lexicon_ID
			if($j == count($lexicons)){
				
				$query = "insert into lexicon (word) values ('$word')";
				$db->insert($query);
				
				$query= "select lexicon_ID from lexicon order by lexicon_ID desc limit 0,1";
				$lexicon_ID= $db->retrieveElement($query); 	 
			}
			
			// insert or update the index table
			
			$query= "select * from knowledge_index where lexicon_ID = '$lexicon_ID' and network_ID = '$network_ID' and knowledge_ID = '$knowledge_ID'";
			
			$rec= $db->retrieveRow($query);
			
			if($rec){
				
				$hits= $rec['hits'];
				$hits++;
				
				$query= "update knowledge_index set hits = '$hits' where lexicon_ID = '$lexicon_ID' and network_ID = '$network_ID' and knowledge_ID = '$knowledge_ID'";				
				$db->update($query); 
			}
			else{
				$query = "insert into knowledge_index (network_ID, knowledge_ID, lexicon_ID, hits) values ('$network_ID', '$knowledge_ID', '$lexicon_ID', '1')";				
				$db->insert($query); 
			}
		}
	}
	
	public function train_NSAFE($trueText, $topic_ID){		//Network-Specific Article Filtering Engine
	
		$tokens= $this->tokenize($trueText);
		
		$db= new DB("kf_corpus");
		
		$query= "select * from lexicon";
		
		$lexicons= $db->retrieveRows($query);
		
		$distinctTokens= array();
		$repeatedWord= false;
		
		for($i=0;$i<count($tokens);$i++){
			
			$word= $tokens[$i];
			
			if(!$this->isMember($word, $distinctTokens))
				$distinctTokens[]= $word;
		}
		
		for($i=0;$i<count($distinctTokens);$i++){
			
			$word= $distinctTokens[$i];
			
			for($j=0;$j<count($lexicons);$j++){
				if($word == $lexicons[$j]['word']){
					$lexicon_ID= $lexicons[$j]['lexicon_ID'];
					break;
				}	
				
			}
			
			// if the word is new (not in lexicon table), insert the lexicon and retrieve back the lexicon_ID
			if($j == count($lexicons)){
				
				$query = "insert into lexicon (word) values ('$word')";
			
				$db->insert($query);
				
				$query= "select lexicon_ID from lexicon order by lexicon_ID desc limit 0,1";
				
				$lexicon_ID= $db->retrieveElement($query); 	 
			}
			
			// insert or update the lexicon_topic table
			
			$query= "select * from lexicon_topic where lexicon_ID = '$lexicon_ID' and topic_ID = '$topic_ID'";
			
			$rec= $db->retrieveRow($query);
			
			if($rec){
				
				$docCount= $rec['docCount'];
				$docCount++;
				
				$query= "update lexicon_topic set docCount = '$docCount' where lexicon_ID = '$lexicon_ID' and topic_ID = '$topic_ID'";				
				$db->update($query);
			}
			else{
				$query = "insert into lexicon_topic (lexicon_ID, topic_ID, docType, docCount) values ('$lexicon_ID', '$topic_ID', '1', '1')";				
				$db->insert($query);
			}
		}
		
		// Update topic table	
			
		$query= "select * from topic where topic_ID = '$topic_ID'";
		
		$rec= $db->retrieveRow($query);
		
		$docCount= $rec['docCount'];
		$docCount++;
		
		$query= "update topic set docCount = '$docCount' where topic_ID = '$topic_ID'";
		
		$db->update($query);
		
	}
	
	public function filterText($newText, $topic_ID){
		
		//Thresholds
		$gamma= 1.1;
		$beta= 2.05;
		$fi= 0.03;
		$mu= 1.5;
		
		$tokens= $this->tokenize($newText);
				
		//Retrieve the number of documents under the topic
		$db= new DB("kf_corpus");
		$query= "select * from topic where topic_ID = '$topic_ID'"; 
		$topicInfo= $db->retrieveRow($query);
		
		$topicStatus= $topicInfo['status'];
		$totalDocCount= $topicInfo['docCount'];
		
		$phase1= false;
		//if the first time entering into NSAFE's Phase 2
		if($topicStatus == 1){
			
			//Update the status
			$query= "update topic set status = '2' where topic_ID = '$topic_ID'";
			//$db->update($query);
			
			$phase1= true;			
		}
		
		//retrieve poolR and poolN
		$poolR= array();
		$poolN= array();
		
		$query= "select * from lexicon_topic where topic_ID = '$topic_ID' and docType= '1'";
		$recs= $db->retrieveRows($query);
		
		$initWeightFactor= count($recs);
		
		for($i=0;$i<count($recs);$i++)
			$poolR[$i]= $recs[$i]['lexicon_ID'];

		$query= "select * from lexicon_topic where topic_ID = '$topic_ID' and docType= '0'";
		$recs= $db->retrieveRows($query);
		
		for($i=0;$i<count($recs);$i++)
			$poolN[$i]= $recs[$i]['lexicon_ID'];			
			
		//find TermFreq(w,d) -> freq of the word in the document
		$distinctTokens= array();
		$freqs= array();
				
		for($i=0;$i<count($tokens);$i++){
			
			$word= $tokens[$i];
			
			$index= $this->indexOf($word, $distinctTokens);
			
			if($index == -1){
				$distinctTokens[]= $word;
				$freqs[]= 1;
			}
			else{
				$freqs[$index]++;
			}
		}
		
		//Assign weight to each token or feature (word) by using TF-IDF scheme
		$query= "select * from lexicon";
		$lexicons= $db->retrieveRows($query);
		
		$poolX= array();
		$tf_idf_weights= array();
		$tokenStatus= array();			// 1 for existing terms, 2 for new terms
		$featureWeights= array();
		$newTerms= array();
				
		for($i=0;$i<count($distinctTokens);$i++){
			
			$word= $distinctTokens[$i];
			
			for($j=0;$j<count($lexicons);$j++){
				if($word == $lexicons[$j]['word']){
					$lexicon_ID= $lexicons[$j]['lexicon_ID'];
					break;
				}		
			}
			if($j != count($lexicons)){
				
				$poolX[]= $lexicon_ID;
				
				$query= "select * from lexicon_topic where lexicon_ID = '$lexicon_ID' and topic_ID = '$topic_ID'"; 
				$rec= $db->retrieveRow($query);
				
				if($rec){
					$tokenStatus[$i]= 1;
					if($rec['docType'] == 1){
						$docFreq= $rec['docCount'];
						
						//initialize or retrieve the feature weights
						if($phase1)
							$featureWeights[$i]= 1 / $initWeightFactor;
						else
							$featureWeights[$i]= $rec['featureWeight'];
							
					}
					else{
						$docFreq= 0;
						$featureWeights[$i]= $rec['featureWeight'];
					}
				}
				else{
					$tokenStatus[$i]= 2;
					$docFreq= 0;
					$featureWeights[$i]= 1 / (2 * $initWeightFactor);
					$newTerms[]= $lexicon_ID;										
				}					
			}
			else{
				$tokenStatus[$i]= 2;
				$docFreq= 0;
				$featureWeights[$i]= 1 / (2 * $initWeightFactor);
				//if a new term absent in lexicon table, insert the term and retrieve back the lexicon_ID
				$query = "insert into lexicon (word) values ('$word')"; 
				$db->insert($query);
				
				$query= "select lexicon_ID from lexicon order by lexicon_ID desc limit 0,1"; 
				$lexicon_ID= $db->retrieveElement($query); 
				
				$newTerms[]= $lexicon_ID;				
				$poolX[]= $lexicon_ID;
			}
			
			//TF-IDF_Weight(w,d) = TermFreq(w,d) * log (N / DocFreq(w))
			$tf_idf_weights[$i]=0;
			
			if($docFreq != 0){
				$temp= $totalDocCount / $docFreq;
				$tf_idf_weights[$i]= $freqs[$i] * log($temp, 10);
			}			
			//echo "<br>" . $freqs[$i] . " * log ($totalDocCount / $docFreq) = " . $tf_idf_weights[$i];
		}
		
		//normalize TF-IDF Weights
		$factor= 0;
		for($i=0;$i<count($tf_idf_weights);$i++){
			
			$factor += pow($tf_idf_weights[$i], 2);
		}
		$factor= pow($factor, 0.5);
		
		$normalizedDocWeights= array();
		for($i=0;$i<count($tf_idf_weights);$i++){
			
			if($factor == 0)			
				$normalizedDocWeights[$i]= 0;
			else
				$normalizedDocWeights[$i]= $tf_idf_weights[$i] / $factor;
			
			//echo "<br>Unnormalized: " . $tf_idf_weights[$i] . "&nbsp;&nbsp;&nbsp;Normalized: " . $normalizedDocWeights[$i];
		}
		
		$poolI= array_intersect($poolX, $poolR);
		//print_r($poolR); echo "<br><br>";
		//print_r($poolX); echo "<br><br>";
		//print_r($poolI); echo "<br><br>";
		//print_r($poolN); echo "<br><br>";
		
		$keys= array();
		while($elem = current($poolI)) {
		    $keys[]= key($poolI);	    
		    next($poolI);
		}
		
		$docType= 0;
		$pass= false;
		$totalWeight=0;
		
		//print_r($normalizedDocWeights); echo "<br><br>";
		//print_r($featureWeights); echo "<br><br>";
		
		for($i=0;$i<count($keys);$i++)
			$totalWeight += $normalizedDocWeights[$keys[$i]] * $featureWeights[$keys[$i]];
		//echo "$totalWeight : $fi";
		if($totalWeight >= $fi){
			$docType= 1;
			$pass= true;
		}
		
		$tempPool1= array_diff($poolN, $poolR);
		$tempPool2= array_merge($poolR, $tempPool1);
		$poolD= array_diff($poolX, $tempPool2);
		
		$result= (($gamma * count($poolI)) / count($poolR)) + (count($poolD) / count($poolX));
		
		if($result >= $beta){
			$pass= true;
		}
		
		if(1){			// if(pass){
			$class= $this->classifyText($newText, $topic_ID, $totalDocCount);
			for($i=0;$i<count($poolX);$i++){
				$featureWeights[$i] *=  exp(($class - $docType) * $normalizedDocWeights[$i] * $mu);
			}
			
			if($class == 1){
				
				$tempPool1= array_diff($poolX, $poolR);
				$oldPoolN= array_diff($tempPool1, $newTerms);
				
				//renormalize feature weights
				$totalCount= count($poolR) + count($newTerms) + count($oldPoolN);
				for($i=0;$i<count($poolX);$i++){
					$featureWeights[$i] /=  $totalCount;
				}
				
				//update lexicon_topic table
				while($elem = current($poolI)) {
				    $lexicon_ID= $elem;	 
					$featureWeight= $featureWeights[$this->indexOf($lexicon_ID, $poolX)];
					
					$query= "select docCount from lexicon_topic where lexicon_ID = '$lexicon_ID' and topic_ID = '$topic_ID'";
					$docCount= $db->retrieveElement($query);
					$docCount++;
					
					$query= "update lexicon_topic set docCount = '$docCount', featureWeight = '$featureWeight' where lexicon_ID = '$lexicon_ID' and topic_ID = '$topic_ID'";
					$db->update($query);
					   
				    next($poolI);
				}
	
				for($i=0;$i<count($newTerms);$i++){
					$lexicon_ID= $newTerms[$i];
					$featureWeight= $featureWeights[$this->indexOf($lexicon_ID, $poolX)];
					
					$query = "insert into lexicon_topic (lexicon_ID, topic_ID, docType, docCount, featureWeight) values ('$lexicon_ID', '$topic_ID', '1', '1', '$featureWeight')";				
					$db->insert($query);
				}
				
				//convert the poolN elements in poolX to poolR		
				while($elem = current($oldPoolN)) {
				    $lexicon_ID= $elem;	 
					$featureWeight= $featureWeights[$this->indexOf($lexicon_ID, $poolX)];
							
					$query= "update lexicon_topic set docType = '1', docCount = '1', featureWeight = '$featureWeight' where lexicon_ID = '$lexicon_ID' and topic_ID = '$topic_ID'";
					$db->update($query);					   
				    next($oldPoolN);
				}
				
				//update topic table
				$query= "select * from topic where topic_ID = '$topic_ID'";
				$docCount= $db->retrieveElement($query);		
				$docCount++;
				
				$query= "update topic set docCount = '$docCount' where topic_ID = '$topic_ID'";
				$db->update($query);
			
			}
			else{
				$pass= false;
				//renormalize feature weights
				$totalCount= count($poolN) + count($newTerms);
				for($i=0;$i<count($poolX);$i++){
					$featureWeights[$i] /=  $totalCount;
				}
				
				$poolI= array_intersect($poolX, $poolN);
				
				while($elem = current($poolI)) {
				    $lexicon_ID= $elem;	 
					$featureWeight= $featureWeights[$this->indexOf($lexicon_ID, $poolX)];
					
					$query= "select docCount from lexicon_topic where lexicon_ID = '$lexicon_ID' and topic_ID = '$topic_ID'";
					$docCount= $db->retrieveElement($query);
					$docCount++;
					
					$query= "update lexicon_topic set docCount = '$docCount', featureWeight = '$featureWeight' where lexicon_ID = '$lexicon_ID' and topic_ID = '$topic_ID'";
					$db->update($query);
					   
				    next($poolI);
				}
	
				for($i=0;$i<count($newTerms);$i++){
					
					$lexicon_ID= $newTerms[$i];
					$featureWeight= $featureWeights[$this->indexOf($lexicon_ID, $poolX)];
					
					$query = "insert into lexicon_topic (lexicon_ID, topic_ID, docType, docCount, featureWeight) values ('$lexicon_ID', '$topic_ID', '0', '1', '$featureWeight')";				
					$db->insert($query);
				}
			}
		}
		return $pass;
	}
	
	public function classifyText($newText, $topic_ID, $totalDocCount){
		
		$tokens= $this->tokenize($newText);
		
		$db= new DB("kf_corpus");
		$query= "select * from lexicon_topic where topic_ID = '$topic_ID' and docType = '1'"; 
		$recs= $db->retrieveRows($query);
		
		$strongWords= array();
		for($i=0;$i<count($recs);$i++){
			$strength= $recs[$i]['docCount'] / $totalDocCount; 
			
			if($strength >= 0.8){
				$lexicon_ID= $recs[$i]['lexicon_ID'];
				
				$query= "select word from lexicon where lexicon_ID = '$lexicon_ID'"; 
				$word= $db->retrieveElement($query);
				
				$strongWords[]= $word;
			}
		}
		
		$strongWordCount= 0;
		for($i=0;$i<count($tokens);$i++){
			if($this->isMember($token[$i], $strongWords))
				$strongWordCount++;
		}
		
		$sufficiency= $strongWordCount / count($tokens);
		
		if($sufficiency >= 0.5)
			return 1;
		return 0;
	}
	
	public function isMember($element, $array){
		
		for($i=0;$i<count($array);$i++)
			if($element == $array[$i])
			 	return true;
		return false;
			
	}
	
	public function indexOf($element, $array){
		
		for($i=0;$i<count($array);$i++)
			if($element == $array[$i])
			 	return $i;
		return -1;
			
	}
}


?>