<?php

/*
  OpenCalais: A wrapper for Thomson Reuters' OpenCalais semantic parser
  (http://www.opencalais.com/)
  Author: Vaughn Hagerty
  Usage: new OpenCalais(api_key, submitter[, relevance]), OpenCalais->parse(HTML_formatted_article)
  api_key and submitter are required and created when registering for OpenCalais account
  relevance is a decimal value between 0 and 1 optional and determines how strictly you want to parse
  HTML_formatted_article is the article to parse

  Returns an array of associative arrays where the keys are, among other things, entity types and topics

 */

class OpenCalais {
    /* OpenCalais config */

    private $paramsRaw = '<c:params xmlns:c="http://s.opencalais.com/1/pred/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
 
<c:processingDirectives c:contentType="{CONTENT TYPE}" c:enableMetadataType="GenericRelations,SocialTags" c:outputFormat="{OUTPUT}" 
c:docRDFaccesible="false" calculateRelevanceScore="true" c:omitOutputtingOriginalText="TRUE">
 
</c:processingDirectives>
 
<c:userDirectives c:allowDistribution="false" c:allowSearch="false" c:externalID="17cabs901" c:submitter="{SUBMITTER}">
 
</c:userDirectives>
 
<c:externalMetadata>
 
</c:externalMetadata>
 
</c:params>';
    private $params = '';
    private $content_type;
    private $output;
    private $submitter;
    private $data = '';
    private $ch;
    private $oc_api_link = "http://api.opencalais.com/enlighten/calais.asmx/Enlighten";
    private $response;
    private $json_obj;
    private $min_relevance;
    public $entityTypes = array();
    private $ocReturn;

//Create OpenCalais object by passing an api key and submitter (required by OpenCalais API
// and relevance. NOTE: changing content_type and output are not currently supported
    public function __construct($api_key, $submitter, $min_relevance = 0.2, $content_type = "TEXT/HTML", $output = "application/json") {

//Create the XML we'll submit to OpenCalais
        $this->submitter = $submitter;
        $this->content_type = $content_type;
        $this->min_relevance = $min_relevance;
        $this->content_type = $content_type;
        $this->data = "licenseID=" . urlencode($api_key);
        $this->output = $output;
        $this->params = $this->paramsRaw;
        $this->params = preg_replace('/{CONTENT TYPE}/m', $this->content_type, $this->params);
        $this->params = preg_replace('/{SUBMITTER}/m', $this->submitter, $this->params);
        $this->params = preg_replace('/{OUTPUT}/m', $this->output, $this->params);
    }

//Submit and parse the content, then return a hash with what we found

    public function parse($content) {
        $this->fetchContent($content);
        return $this->relevant();
    }

//Fetch the content and create an object out of the JSON that's returned	
    private function fetchContent($content) {
        $this->data .= "&paramsXML=" . urlencode($this->params);
        $this->data .= "&content=" . urlencode($content);
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, $this->oc_api_link);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_HEADER, 0);
        curl_setopt($this->ch, CURLOPT_POSTFIELDS, $this->data);
        curl_setopt($this->ch, CURLOPT_POST, 1);
        $this->response = curl_exec($this->ch);
        curl_close($this->ch);
        $this->response = preg_replace('%<[^>]+>%', '', $this->response); //remove some garbage from the response
        $this->json_obj = json_decode($this->response);
    }

//Build our return array based on the minimum relevance we've specified
    private function relevant() {
        $this->ocReturn = array();

        $this->entityTypes = array();
        $this->entityTypes['socialTags'] = array();
         $this->entityTypes['entities'] = array();

        foreach ($this->json_obj as $entity) {
            
            if (is_object($entity->info)) {
                continue;
            }
            if ($entity->_typeGroup == 'topics') {

                if (count($this->entityTypes['topics']) == 0 || $this->entityTypes['topics'][0]['score'] < $entity->score) {
                    $this->entityTypes['topics'] = array();
                    $topic['name'] = $entity->categoryName;
                    $topic['score'] = $entity->score;
                    $topic['calais_category'] = $entity->category;

                    array_push($this->entityTypes['topics'], $topic);

                    continue;
                }
            }

            if ($entity->_typeGroup == 'socialTag') {
                if ($entity->importance > 2) {
                    continue;
                }
                
                $socialTag = array();
                $socialTag['name'] = $entity->name;
                $socialTag['importance'] = $entity->importance;
                 $socialTag['calais_socialTag'] = $entity->socialTag;
                
                array_push($this->entityTypes['socialTags'], $socialTag);

                continue;
            }
            if ($entity->_typeGroup == 'entities') {
                if ($entity->relevance < $this->min_relevance) {
                    continue;
                }
                $ent = array();
                $ent['name'] = $entity->name;
                $ent['_typeReference'] = $entity->_typeReference;
                $ent['relevance'] = $entity->relevance;
                $ent['type'] = $entity->_type;

                array_push($this->entityTypes['entities'], $ent);
            }

        }
        
        return $this->entityTypes;
    }

}