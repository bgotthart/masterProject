<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Wikipedia
 *
 * @author biancagotthart
 */
class Wikipedia {

    private $api_link = "http://en.wikipedia.org/w/api.php";
    private $params;
    private $limits = 10;
    private $terms;
    
    public function __construct() {
        
//        http://en.wikipedia.org/w/api.php?action=query&prop=categories&format=xml&clshow=!hidden&cllimit=10&generator=search&gsrsearch=apple%20iMac%20snowleopard%22&gsrnamespace=0&gsrprop=titlesnippet&gsrredirects=&gsrlimit=10
        //
        //
        //http://en.wikipedia.org/w/api.php
        //?action=query&prop=categories&format=xml&clshow=!hidden&cllimit=10&generator=search
        ////&gsrsearch=apple%20iMac%20snowleopard%22&gsrnamespace=0&gsrprop=titlesnippet&gsrredirects=&gsrlimit=10
        //list of wiki-links:
        //api.php ? action=query & titles=Albert%20Einstein & prop=links
        //external link of item
        //api.php ? action=query & titles=Albert%20Einstein & prop=extlinks
        
       // $this->params = "?action=query&prop=categories&format=xml&clshow=!hidden&cllimit=10&generator=search&gsrsearch=".$this->terms."&gsrnamespace=0&gsrprop=titlesnippet&gsrredirects=&gsrlimit=".$this->limits;
        
    }
    private function buildRequestParams($termsArray){
        
        $tmpTerms = '';
        
        foreach($termsArray as $term){
            
            //print_r($term['name']);
            $tmpTerms .= $term['name'] ."%20";
                    
        }
         $this->params = "?action=query&prop=categories&format=xml&clshow=!hidden&cllimit=10&generator=search&gsrsearch=".$tmpTerms."&gsrnamespace=0&gsrprop=titlesnippet&gsrredirects=&gsrlimit=".$this->limits;
       /*
        $getdata = http_build_query(
        array(
            'action' => 'query',
            'prop' => 'categories',
            'format'=>'xml',
            'clshow'=>'!hidden',
            'cllimit'=>'10',
            'generator' => 'search',
            'gsrsearch'=> $tmpTerms,
            'gsrnamespace' => '0',
            'gsrprop' =>'titlesnippet',
            'gsrredirects' => '',
            'gsrlimit' => $this->limits
            
          )
        );

        return $getdata;
        
        */
    }
    public function callAPIWithData($termsArray){
        

        $this->buildRequestParams($termsArray);
        
        $url = $this->api_link.  $this->params;
        
        print_r($url);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, 'MyBot/1.0 (http://www.biancagotthart.at/)');

        $result = curl_exec($ch);

        if (!$result) {
          exit('cURL Error: '.curl_error($ch));
        }

        print_r($result);

        
        $xml = new SimpleXMLElement($result);


        $tmp = (array)$xml->xpath("/api/query/pages/page/categories");
        
        die("end");
        return $categories; 

    }
    
}

?>
