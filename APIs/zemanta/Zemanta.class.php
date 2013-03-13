<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Zemanta
 *
 * @author biancagotthart
 */
class Zemanta {
    private $output = "xml";
    private $api_link = "http://api.zemanta.com/services/rest/0.0/";
    private $api_key;
    private $params;
    private $fetchImages;
    private $fetchArticles;
    private $fetchCategories;
    private $fetchRDFLinks;
    public function __construct($api_key, $fetchCategories = 0, $fetchImages = 0, $fetchArticles = 0, $fetchRDFLinks = 0) {
            $this->api_key = $api_key;
            $this->fetchArticles = $fetchArticles;
            $this->fetchCategories = $fetchCategories;
            $this->fetchImages = $fetchImages;
            $this->fetchRDFLinks = $fetchRDFLinks;
    }
    
    public function parse($content) {
        $args = array(
                'method'=> 'zemanta.suggest',
                'api_key'=> $this->api_key,
                'text'=> $content,
                'format'=> $this->output,
                'return_rdf_links' => $this->fetchRDFLinks,
                'return_images' => $this->fetchImages,
                'return_articles' => $this->fetchArticles,
                'return_categories' => $this->fetchCategories
                );

            /* Here we build the data we want to POST to Zementa */
            $data = "";
            foreach($args as $key=>$value)
            {
                $data .= ($data != "")?"&":"";
                $data .= urlencode($key)."=".urlencode($value);
            }

            /* Here we build the POST request */
            $this->params = array('http' => array(
            'method' => 'POST',
            'Content-type'=> 'application/x-www-form-urlencoded',
            'Content-length' =>strlen( $data ),
            'content' => $data
            ));
	
        /* Here we send the post request */
        $ctx = stream_context_create($this->params); // We build the POST context of the* request
        $fp = @fopen($this->api_link, 'rb', false, $ctx); // We open a stream and send the request
        if ($fp)
        {
            /* Finaly, herewe get the response of Zementa */
            $response = @stream_get_contents($fp);
            if ($response === false)
            {
            $response = "Problem reading data from ".$this->api_link.", ".$php_errormsg;
            }
            fclose($fp); // We close the stream
        }
        else
        {
            $response = "Problem reading data from ".$this->api_link.", ".$php_errormsg;
        }

        $xml = new SimpleXMLElement($response);

        
        $tmpKeywords = (array)$xml->xpath("//keyword");
        $keywords = array();
        foreach ($tmpKeywords as $item) {
           
            $itemArray = (array)$item;
            if($itemArray['confidence'] > 0.05){
                array_push($keywords, (array)$item);
            }
            
        }
        
        $responseArray['keywords'] = $keywords;

        return $responseArray;
    }
    
 
}

?>
