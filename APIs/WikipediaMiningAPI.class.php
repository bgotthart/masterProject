<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of WikipediaMiningAPI
 *
 * @author biancagotthart
 */
class WikipediaMiningAPI extends DBpedia_DatabaseClass{
    
    
    public function __construct() {

        try {
            $term = (string)(urlencode($term));
            $searchUrl = "http://wdm.cs.waikato.ac.nz/services/search?query=" . $term . "&labels=true&parentCategories=true&responseFormat=xml";
               
            $response = $this->sendExternalRequest($searchUrl);

            $xmlobj = new SimpleXMLElement($response);

            $alternativeTerms = $xmlobj->xpath("//sense");
            
            $highestPrio = 0.0;
            $newTerm = $term;
            
            foreach ($alternativeTerms as $alternativeTerm) {

                $prio = (float)$alternativeTerm->attributes()->priorProbability;
                
                if($highestPrio < $prio){
                    $highestPrio = $prio;
                    $newTerm = (string)$alternativeTerm->attributes()->title;
                }
                
            }
                                    
            return (string)$newTerm;
            
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }     
    
    
    public function searchForTerm($term){
        
        try {
            $term = (string)(urlencode($term));
            $searchUrl = "http://wdm.cs.waikato.ac.nz/services/search?query=" . $term . "&labels=true&parentCategories=true&responseFormat=xml";
               
            $response = $this->sendExternalRequest($searchUrl);

            $xmlobj = new SimpleXMLElement($response);

            $alternativeTerms = $xmlobj->xpath("//sense");
            
            $highestPrio = 0.0;
            $newTerm = $term;
            
            foreach ($alternativeTerms as $alternativeTerm) {

                $prio = (float)$alternativeTerm->attributes()->priorProbability;
                
                if($highestPrio < $prio){
                    $highestPrio = $prio;
                    $newTerm = (string)$alternativeTerm->attributes()->title;
                }
                
            }
                                    
            return (string)$newTerm;
            
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
        
        
    }

    public function getCategoryDetails($term){
        
        
        try {
            $term = urlencode($term);
            $searchUrl = "http://wdm.cs.waikato.ac.nz/services/exploreCategory?title=" . $term . "&labels=true&parentCategories=true&responseFormat=xml";

            $response = $this->sendExternalRequest($searchUrl);

            $xmlobj = new SimpleXMLElement($response);

            $tmp = array(($xmlobj->xpath("/message")));

            $categories = array();
            foreach ($tmp[0] as $obj) {

                if ($obj->xpath("//parentCategory") != null) {
                    $parentCategories = $obj->xpath("//parentCategory");
                    foreach ($parentCategories as $item) {
                        array_push($categories, (string) $item->attributes()->title);
                    }
                }
            }
            return($categories);
        } catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
    }
    
    public function getArticleDetails($term){
        
        
        try {
            $term = urlencode($term);
            $searchUrl = "http://wdm.cs.waikato.ac.nz/services/exploreArticle?title=".$term."&labels=true&parentCategories=true&responseFormat=xml";

           
            $response = $this->sendExternalRequest($searchUrl);

            $xmlobj = new SimpleXMLElement($response);
            
            $tmp = array(($xmlobj->xpath("/message")));

            $categories = array();
            foreach($tmp[0] as $obj){
                
                if($obj->xpath("//parentCategory") != null){
                    $parentCategories = $obj->xpath("//parentCategory");
                    foreach($parentCategories as $item){
                        array_push($categories, (string)$item->attributes()->title);
                    }

                }
            }
                          
            return($categories);
    }catch (SQLException $oException) {
            echo ("Caught SQLException: " . $oException->sError );
        }
        
    }
}



?>
