<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Database
 *
 * @author biancagotthart
 */

require_once("DBpedia_Database.php");

class DatabaseClass {

    private $arc_store;

    const EX_URL = "http://www.semanticweb.org/ontologies/2013/0/ex.owl";
    const PREFIX_EX = "PREFIX ex: <http://www.semanticweb.org/ontologies/2013/0/ex.owl#>";
    const PREFIX_RDF = "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>";
    const PREFIX_OX = "PREFIX owl: <http://www.w3.org/2002/07/owl#>";

    private $dbpedia_database;
    
    private $user_id = "http://www.semanticweb.org/ontologies/2013/0/ex.owl#BiancaGotthart";

    public function __construct() {
    
        $this->dbpedia_database = new DBpedia_DatabaseClass();

        $this->loadConfigFile();

        $this->initDBStore();

        $this->selectQuery();
    }

    public function getCategories($keyword) {    
        
         $this->dbpedia_database->calculateSimilarity($keyword, "http://dbpedia.org/page/Category:Ball_games");

        //$this->dbpedia_database->getCategoriesOfArticleWithCategories($keyword);
    }
    
   
    
    public function queryReallyAll() {
        $q = 'SELECT DISTINCT ?subject ?property ?object WHERE { ?subject ?property ?object . }';
        $rows = $this->arc_store->query($q, 'rows');
        $r = '';
        if ($rows = $this->arc_store->query($q, 'rows')) {
            $r = '<table border=1>
            <th>Subject</th><th>Property</th><th>Object</th>' . "\n";
            foreach ($rows as $row) {
                $r .= '<tr><td>' . $row['subject'] .
                        '</td><td>' . $row['property'] .
                        '</td><td>' . $row['object'] . '</td></tr>' . "\n";
            }
            $r .='</table>' . "\n";
        } else {
            $r = '<em>No data returned</em>';
        }
        echo $r;
    }

    public function queryAllTriples() {
        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF .
                'SELECT * 
                WHERE { 
                {
                ?keyword rdf:type owl:NamedIndividual ;
                    rdf:type ex:Keyword ;
                    ex:hasName ?keywordname .
                }
                
                
            }';
        $rows = $this->arc_store->query($q, 'rows');
        if ($errs = $this->arc_store->getErrors()) {
            print_r($errs);
            $r .= "ERROR IN Topic";
        }
        $r = '<h1>All</h1>';
        if ($rows = $this->arc_store->query($q, 'rows')) {

            $r .= '<table border=1>';
            foreach ($rows as $row) {
                $r .= '<tr>';
                $r .= '<td>' . $row['topicname'] . '</td>';
                $r .= '<td>' . $row['keywordname'] . '</td>';
                $r .= '</tr>' . "\n";
            }
            $r .='</table>' . "\n";
        } else {
            $r .= '<em>No all data returned</em>';
        }



        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF .
                'SELECT * 
                WHERE { 
                ?topic rdf:type owl:NamedIndividual ;
                    rdf:type ex:Topic ;
                    ex:hasName ?topicname .
                OPTIONAL {?topic ex:hasWeight ?topicWeight .}
                
            }';
        $rows = $this->arc_store->query($q, 'rows');
        if ($errs = $this->arc_store->getErrors()) {
            print_r($errs);
            $r .= "ERROR IN Topic";
        }
        $r .= '<h1>Topics</h1>';
        if ($rows = $this->arc_store->query($q, 'rows')) {

            $r .= '<table border=1>';
            foreach ($rows as $row) {
                $r .= '<tr>';
                $r .= '<td>' . $row['topicname'] . '</td>';
                $r .= '<td>' . $row['topic'] . '</td>';
                $r .= '<td>' . $row['topicWeight'] . '</td>';
                $r .= '</tr>' . "\n";
            }
            $r .='</table>' . "\n";
        } else {
            $r .= '<em>No topic data returned</em>';
        }



        //KEYWORDS = SOCIALTAGS
        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF .
                'SELECT * 
                WHERE { 
                ?keyword rdf:type owl:NamedIndividual ;
                    rdf:type ex:Keyword ;
                    ex:hasName ?keywordname .
                OPTIONAL {
                    ?keyword ex:hasWeight ?keywordWeight .
                }
                
            }';
        /* before optional
         * ?topic rdf:type owl:NamedIndividual ;
          rdf:type ex:Topic ;
          ex:hasName ?topicname .
          {
          ?topic ex:topicHasKeyword ?keyword .
          }
          UNION{
          ?keyword ex:keywordHasTopic ?topic .
          }
         */
        $rows = $this->arc_store->query($q, 'rows');
        if ($errs = $this->arc_store->getErrors()) {
            print_r($errs);
            $r .= "ERROR IN Keywords";
        }
        $r .= '<h1>Keywords</h1>';
        if ($rows = $this->arc_store->query($q, 'rows')) {

            $r .= '<table border=1>';
            foreach ($rows as $row) {
                $r .= '<tr>';
                $r .= '<td>' . $row['keywordname'] . '</td>';
                $r .= '<td>' . $row['keyword'] . '</td>';
                $r .= '<td>' . $row['keywordWeight'] . '</td>';
                $r .= '<td>' . $row['topicname'] . '</td>';

                $r .= '</tr>' . "\n";
            }
            $r .='</table>' . "\n";
        } else {
            $r .= '<em>No entity returned</em>';
        }

        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF .
                'SELECT * 
                WHERE { 
                ?entity rdf:type owl:NamedIndividual ;
                    rdf:type ex:Entity ;
                    ex:hasName ?entityname ;
                    ex:hasType ?entitytype .
                OPTIONAL {
                    ?entity ex:hasWeight ?entityWeight .
                }
                
            }';

        /* before optional
          ?topic rdf:type owl:NamedIndividual ;
          rdf:type ex:Topic ;
          ex:hasName ?topicname .

          {
          ?topic ex:topicHasEntity ?entity .
          }
          UNION{
          ?entity ex:entityHasTopic ?topic .
          }
         */

        $rows = $this->arc_store->query($q, 'rows');
        if ($errs = $this->arc_store->getErrors()) {
            print_r($errs);
            $r .= "ERROR IN Entity";
        }
        $r .= '<h1>Entities</h1>';
        if ($rows = $this->arc_store->query($q, 'rows')) {

            $r .= '<table border=1>';
            foreach ($rows as $row) {
                $r .= '<tr>';
                $r .= '<td>' . $row['entityname'] . '</td>';
                $r .= '<td>' . $row['entity'] . '</td>';
                $r .= '<td>' . $row['entitytype'] . '</td>';
                $r .= '<td>' . $row['entityWeight'] . '</td>';
                $r .= '<td>' . $row['topicname'] . '</td>';

                $r .= '</tr>' . "\n";
            }
            $r .='</table>' . "\n";
        } else {
            $r .= '<em>No entity returned</em>';
        }


        echo $r;
    }

    public function insertUserQuery($data) {

        /* TO UPDATE */
        /*
         * http://www.w3.org/TR/sparql11-update/#deleteData
         * WITH <http://example/addresses>
          DELETE { ?person foaf:givenName 'Bill' }
          INSERT { ?person foaf:givenName 'William' }
          WHERE
          { ?person foaf:givenName 'Bill'
          }
         */
        $topic = $data['opencalais']['topics'][0]['name'];

        $entities = $data['opencalais']['entities'];

        //$socialTags = $data['opencalais']['socialTags'];

        $keywords = $data['zemanta']['keywords'];

        $errors = false;

        $config_xml = $this->loadConfigFile();

        if (strlen($topic) > 0) {
            $insert = self::PREFIX_EX . ' ' .
                    self::PREFIX_OX . ' ' .
                    self::PREFIX_RDF .
                    ' INSERT INTO <' . $config_xml->fileBaseURL . '/config/Profile_v2.owl>{
                    <' . self::EX_URL . '#' . $topic . '> rdf:type owl:NamedIndividual;
                        ex:hasName "' . $topic . '";
                        rdf:type    ex:Topic;
                        ex:topicIsInterestedBy ' . '<' . $this->user_id . '> .                            
               }';
            $result = $this->arc_store->query($insert, 'rows', '', true);
            if ($errs = $this->arc_store->getErrors()) {
                echo("TOPICS");
                print_r($errs);
                $errors = true;
            }
        }

        if (count($entities) > 0) {
            foreach ($entities as $entity) {
                $name = $entity['name'];
                $type = $entity['type'];
                $relevance = $entity['relevance'];

                $name = str_replace(" ", "_", $name);


                $insert = self::PREFIX_EX . ' ' .
                        self::PREFIX_OX . ' ' .
                        self::PREFIX_RDF .
                        ' INSERT INTO <' . $config_xml->fileBaseURL . '/config/Profile_v2.owl>{
                    <' . self::EX_URL . '#' . $name . '> rdf:type owl:NamedIndividual;
                        ex:hasName "' . $name . '";
                        ex:hasType "' . $type . '";
                        ex:entityIsInterestedBy ' . '<' . $this->user_id . '> ;    
                        rdf:type    ex:Entity ';

                if (strlen($topic) > 0) {
                    $insert .= '; ex:entityHasTopic ' . '<' . self::EX_URL . '#' . $topic . '> .} ';
                } else {
                    $insert .= ' . }';
                }

                $result = $this->arc_store->query($insert, 'rows', '', true);
                if ($errs = $this->arc_store->getErrors()) {
                    echo("ENTITIES");
                    print_r($errs);
                    $errors = true;
                }
            }
        }


        if (count($keywords) > 0) {
            foreach ($keywords as $keyword) {
                $name = $keyword['name'];
                $importance = $keyword['confidence'];

                $uriName = str_replace(" ", "_", $name);

                $insert = self::PREFIX_EX . ' ' .
                        self::PREFIX_OX . ' ' .
                        self::PREFIX_RDF .
                        ' INSERT INTO <' . $config_xml->fileBaseURL . '/config/Profile_v2.owl>{
                    <' . self::EX_URL . '#' . $uriName . '> rdf:type owl:NamedIndividual;
                        ex:hasName "' . $name . '";
                        ex:hasWeight "' . $importance . '";
                        ex:keywordIsInterestedBy ' . '<' . $this->user_id . '> ;    
                        rdf:type    ex:Keyword ';

                if (strlen($topic) > 0) {
                    $insert .= '; ex:keywordHasTopic ' . '<' . self::EX_URL . '#' . $topic . '> .} ';
                } else {
                    $insert .= ' . }';
                }
                $result = $this->arc_store->query($insert, 'rows', '', true);
                if ($errs = $this->arc_store->getErrors()) {
                    echo("KEYWORDS");
                    print_r($errs);
                    $errors = true;
                }
            }
        }

        if (!$errors) {
            return '{"response": [{ "function":"insertUserQuery" , 
                                    "message":"Entities are successfully added to User Profile", 
                                    "topic": "' . $topic . '", "entities": "' . count($entities) . '", "keywords": "' . count($keywords) . '", "socialTags: "' . count($socialTags) . '" }]}';
        } else {
            return '{"response": [{ "function":"insertUserQuery" , 
                                    "message":"ERROR: Entities NOT added to User Profile", 
                                    "topic": "' . $topic . '", "entities": "' . count($entities) . '", "keywords": "' . count($keywords) . '","socialTags: "' . count($socialTags) . '" }]}';
        }
    }

    public function selectQuery() {

        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                ' SELECT ?topic ?topicname ?topicWeight
                    WHERE
                {    
                
                    ?user rdf:type ex:User .
                    ?user ex:hasName ?name .
                    
                    ?topic rdf:type ex:Topic .
                    ?topic ex:hasName ?topicname .
                    OPTIONAL {?topic ex:hasWeight ?topicWeight . }
                    {?user ex:isInterestedInTopic ?topic .}
                    UNION
                    {
                       ?topic ex:topicIsInterestedBy ?user .

                    }
    
                }';


        $rows = $this->arc_store->query($q);
        if ($errs = $this->arc_store->getErrors()) {
            echo("ERROR in SELECT TOPIC");
            print_r($errs);
            return;
        }

        $topics = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {
                $topics[$row['topic']]['name'] = $row['topicname'];
                $topics[$row['topic']]['weight'] = $row['topicWeight'];

                $qKeywords = self::PREFIX_EX . ' ' .
                        self::PREFIX_OX . ' ' .
                        self::PREFIX_RDF . ' ' .
                        ' SELECT ?keyword  ?keywordname ?keywordWeight
                          WHERE
                            {    
                                ?topic rdf:type ex:Topic .
                                ?topic ex:hasName "' . $row['topicname'] . '".
                                ?keyword rdf:type ex:Keyword ;
                                    ex:hasName ?keywordname .
                                OPTIONAL {?keyword ex:hasWeight ?keywordWeight . }
                                {?keyword ex:keywordHasTopic ?topic .}
                                UNION{
                                    ?topic ex:topicHasKeyword ?keyword .
                                } 
                        }';

                $keywordRows = $this->arc_store->query($qKeywords);
                if ($errs = $this->arc_store->getErrors()) {
                    echo("ERROR in SELECT KEYWORDS");
                    print_r($errs);
                    return;
                }

                if ($keywordRows = $this->arc_store->query($qKeywords, 'rows')) {

                    $topics[$row['topic']]['keywords'] = array();
                    $keywords = array();
                    foreach ($keywordRows as $keywordRow) {

                        $keywords[$keywordRow['keyword']]['name'] = $keywordRow['keywordname'];
                        $keywords[$keywordRow['keyword']]['weight'] = $keywordRow['keywordWeight'];
                    }

                    $topics[$row['topic']]['keywords'] = $keywords;
                } else {
                    echo( '<em>No data returned</em>');
                }
            }

            //$r .='</table>'."\n";
        } else {
            echo( '<em>No data returned</em>');
        }

        /*
          $q = self::PREFIX_EX . ' ' .
          'CONSTRUCT  { ?x rdf:type ex:User . ?x ex:hasName  "Bianca Gotthart" }
          WHERE {?x rdf:type ex:User . ?x ex:hasName ?name } ';
         */
        
        
        return $topics;
    }

    private function initDBStore() {

        echo("init database");
        
        
        $config_xml = $this->loadConfigFile();

        $db_config = array(
            'db_host' => (string)$config_xml->database->db_host,
            'db_name' => (string)$config_xml->database->db_name,
            'db_user' => (string)$config_xml->database->db_user,
            'db_pwd' => (string)$config_xml->database->db_pwd,
            /* SPARQL endpoint settings */
            'endpoint_features' => array(
                'select', 'construct', 'ask', 'describe', // allow read
                'load', 'insert', 'delete', // allow update
                'dump' // allow backup
            ),
        );

        $this->arc_store = ARC2::getStore($db_config);
        if (!$this->arc_store->isSetUp()) {
            $this->arc_store->setUp();

            $this->arc_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/Profile_v2.owl>');

        }    
        
    /* 
    $dbpedia_config = array(
            'db_host' => (string)$config_xml->database->db_host,
            'db_name' => (string)$config_xml->database->db_name,
            'db_user' => (string)$config_xml->database->db_user,
            'db_pwd' => (string)$config_xml->database->db_pwd,
            'store_name' => 'dbpedia_store',
            'endpoint_features' => array(
                'select', 'construct', 'ask', 'describe', // allow read
                'load', 'insert', 'delete', // allow update
                'dump' // allow backup
            ),
        );
        $this->dbpedia_store = ARC2::getStore($dbpedia_config);
        if (!$this->dbpedia_store->isSetUp()) {
            $this->dbpedia_store->setUp();

            // $this->dbpedia_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/article_categories_en.nt>');

        } 

        $this->dbpedia_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/article_categories_en.nt>');
        
        print_r($this->dbpedia_store->getErrors());
        
        print_r($this->arc_store->getErrors());
        
        die("finished");
*/
        print_r($this->arc_store->getErrors());
    }
    
    private function loadConfigFile() {
        return simplexml_load_file("../config/config.xml");
    }

}

?>
