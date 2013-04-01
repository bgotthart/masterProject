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
require_once("DBpediaDatabase.php");
require_once("DBpediaDatabase_Local.php");
require_once("DBpediaDatabase_Online.php");
require_once("TermItem.php");

class DatabaseClass {

    private $arc_store;
    private $dbpedia_store;

    const EX_URL = "http://www.example.org/";
    const PREFIX_EX = "PREFIX ex: <http://www.example.org#>";
    const PREFIX_RDF = "PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>";
    const PREFIX_OX = "PREFIX owl: <http://www.w3.org/2002/07/owl#>";

    private $dbpedia_database;
    // private $dbpedia_database2;
    private $user_id = "http://www.example.org#BiancaGotthart";

    public function __construct() {

        $this->initDBStore();
        //$this->initDBpediaDump();

        $this->dbpedia_database = new DBpedia_DatabaseClass();
        //$this->dbpedia_database2 = new DBpedia_DatabaseOnlineClass($this->dbpedia_store);

        $this->loadConfigFile();
    }

    public function deleteUserQuery() {

        $config_xml = $this->loadConfigFile();


        $insert = ' DELETE  {
                   <http://dbpedia.org/resource/IPhone> <http://www.example.org#hasName> "IPhone3"  .
                   }
                  ';


        $result = $this->arc_store->query($insert, '', '', true);
        if ($errs = $this->arc_store->getErrors()) {
            echo("error");
            print_r($errs);
            $errors = true;
        }
        print_r($result);
        //print_r($this->selectQuery());
    }

    public function updateUserQuery($result) {

        $config_xml = $this->loadConfigFile();


        //update
        $arr = array_keys($result);
        $key = $arr[0];

        foreach ($result as $key => $value) {

            if (isset($value['http://www.example.org#hasCount'])) {
                $count = $value['http://www.example.org#hasCount'][0]['value'];
            } else {
                $count = 0;
            }

            $newCount = $count + 1;

            $name = $value['http://www.example.org#hasName'][0]['value'];

            $insert = ' DELETE  {
                           <' . $key . '> <http://www.example.org#hasCount> ' . $count . ' .
                         }';

            $result = $this->arc_store->query($insert, 'raws', '', true);
            $errors = false;
            if ($errs = $this->arc_store->getErrors()) {
                echo("error in delete");
                print_r($errs);
                $errors = true;
            }
            $insert = ' INSERT INTO <' . $config_xml->fileBaseURL . '/config/Profile_v1.owl> {
                            <' . $key . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl#NamedIndividual> ;
                                <http://www.example.org#hasCount> ' . $newCount . ' . }';

            $result = $this->arc_store->query($insert, 'raws', '', true);

            if ($errs = $this->arc_store->getErrors()) {
                echo("error in insert");
                print_r($errs);
                $errors = true;
            }

            if (!$errors) {

                return '{"response": [{ "status": 200, "function":"updateQuery" ,"message":"Update query successfully done."}]}';
            }
        }
    }

    public function saveKeywordsToUserQuery($keywords) {


        $saveConcepts = $this->dbpedia_database->getCategories($keywords);

        if ($saveConcepts == null) {
            return '{"response": [{ "status": 400, "function":"insertUserQuery" ,"message":"Not saved"}]}';
        }

        $errors = false;

        $config_xml = $this->loadConfigFile();


        $resultJson = '"results": [{';
        $lastConcept = end($saveConcepts);

        foreach ($saveConcepts as $conceptKey => $conceptValue) {

            $keyword = reset($conceptValue)->getUri();
            $lastKeyword = end($conceptValue)->getUri();

            $identifier = $this->dbpedia_database->cleaningCategoryTerm($conceptKey);

            $resultJson .= '"' . $identifier . '": [';
            $mainTopics = array();

            //categories from firstUri
            foreach ($conceptValue as $category) {
                $categoryURI = $category->getUri();

                $name = $this->dbpedia_database->cleaningCategoryTerm($categoryURI);

                $stringName = $this->dbpedia_database->convertNameToGetLiteral($name);

                if ($lastKeyword == $categoryURI) {
                    $resultJson .= '{"name": "' . $stringName . '"}';
                } else {
                    $resultJson .= '{"name": "' . $stringName . '"},';
                }

                $insert = ' DESCRIBE <' . $categoryURI . '>';

                $checkResult = $this->arc_store->query($insert, 'raws', '', true);

                if ($errs = $this->arc_store->getErrors()) {
                    echo("error in check for result");
                    print_r($errs);
                    $errors = true;
                }

                if (count($checkResult['result']) > 0) {
                    $this->updateUserQuery($checkResult['result']);
                } else {
                    //insert

                    $insert = ' INSERT INTO <' . $config_xml->fileBaseURL . '/config/Profile_v1.owl> {
                  <' . $categoryURI . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl#NamedIndividual>;
                  <http://www.example.org#hasName> "' . $stringName . '" ;
                      <http://www.example.org#hasCount> "1"^^xsd:integer ;
                  <http://www.example.org#hasConnectionToUser> <' . $this->user_id . '> ;
                  <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>    <http://www.example.org#Concept> .';

                    if ($keyword != $categoryURI) {
                        $insert .= '<' . $keyword . '> <http://www.example.org#hasConnectionTo> <' . $categoryURI . '> . ';
                        //$insert .= '<' . $categoryURI . '>  <http://www.example.org#hasConnectionTo> <' . $keyword . '> . ';

                        $dataName = $category->getName();
                        $dataWeight = $category->getWeight();
                        $connectionUri = "" . self::EX_URL . $conceptKey . "-" . $dataName;
                        $connectionName = $conceptKey . "-" . $dataName;
                        $insert .= '<' . $connectionUri . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl#NamedIndividual>;
                                <http://www.example.org#hasConnectionWeight> "' . $dataWeight . '" ;
                                <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>    <http://www.example.org#Connection> ;
                                <http://www.example.org#connectionHasConcept> <' . $keyword . '> ;
                                <http://www.example.org#connectionHasConcept> <' . $categoryURI . '> ;
                                    <http://www.example.org#connectionHasName> "' . $connectionName . '" .
                                ';

                        if (!in_array($categoryURI, $mainTopics) && $category->getIsMainTopic()) {

                            $insert .= '<' . $keyword . '> <http://www.example.org#hasMainTopic> <' . $categoryURI . '> .';
                            $insert .= '<' . $categoryURI . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>    <http://www.example.org#MainTopic> ;
                            <http://www.example.org#hasName> "' . $dataName . '" .';

                            array_push($mainTopics, $categoryURI);
                        }
                    } else {
                        $insert .= '<' . $categoryURI . '> <http://www.example.org#isKeyword> "true"^^xsd:boolean .';
                    }

                    /*
                      foreach ($conceptValue as $node2) {
                      $data2 = $node2->getUri();
                      if ($data2 != $categoryURI) {
                      //$insert .= '<' . $categoryURI . '> <http://www.example.org#hasConnectionTo> <' . $data2 . '> . ';
                      //$insert .= '<' . $data2 . '>  <http://www.example.org#hasConnectionTo> <' . $categoryURI . '> . ';


                      $data2name = $node2->getName();
                      $data2weight = $node2->getWeight();
                      $connectionUri = "" .self::EX_URL. $name. "-".$data2name."";

                      // $insert .=  '<' . $connectionUri . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl#NamedIndividual>;
                      //        <http://www.example.org#hasConnectionWeight> "' . $data2weight . '" ;
                      //       <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>    <http://www.example.org#Connection> ;
                      //      <http://www.example.org#connectionHasConcept> <' . $data2 . '> ;
                      //        <http://www.example.org#connectionHasConcept> <' . $categoryURI . '> .
                      //  ';


                      }
                      }
                     */
                    $insert .= '}';




                    $result = $this->arc_store->query($insert, '', '', true);
                    if ($errs = $this->arc_store->getErrors()) {
                        echo("error");
                        print_r($errs);
                        $errors = true;
                    }
                }
            }

            if ($lastConcept[0] == $conceptValue[0]) {
                $resultJson .= "]";
            } else {
                $resultJson .= "],";
            }
        }

        $resultJson .= "}]";

        if (!$errors) {
            $response = '{"response": [{ "status": 200, "function":"insertUserQuery" ,"message":"Entities are successfully added to User Profile", ' . $resultJson . '}]}';
            return $response;
        } else {
            $response = '{"response": [{ "status": 400, "function":"insertUserQuery" , "message":"ERROR: Entities NOT added to User Profile" }]}';
            return $response;
        }
    }

    public function insertFeedQuery($entry, $keywords) {

        $saveConcepts = $this->dbpedia_database->getCategories($keywords);


        $feedtimestamp = $entry->pubDate;
        $feedlink = (string) $entry->link;

        if ($saveConcepts == null) {
            return '{"response": [{ "status": 400, "function":"insertFeedQuery" ,"message":"Not saved"}]}';
        }

        $errors = false;

        $config_xml = $this->loadConfigFile();

        //add concept keyword
        $insert = ' INSERT INTO <' . $config_xml->fileBaseURL . '/config/Profile_v1.owl> {
                  <' . $feedlink . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl#NamedIndividual>;
                  <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>    <http://www.example.org#FeedURI> .';
//                  <http://www.example.org#hasTimestamp> "' . $feedtimestamp . '" ;

        $resultJson = '"results": [{';
        $lastConcept = end($saveConcepts);

        foreach ($saveConcepts as $conceptValue) {
            foreach ($conceptValue as $concept) {

                $item = $concept;
                $resultJson .= $item->getName() . ", ";
                $insert .= '<' . $item->getUri() . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2002/07/owl#NamedIndividual>;
                  <http://www.example.org#hasName> "' . $item->getName() . '" ;
                  <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>    <http://www.example.org#Concept> . ';

                $insert .= '<' . $feedlink . '> <http://www.example.org#hasConnectionTo> <' . $item->getUri() . '> . ';
                $insert .= '<' . $item->getUri() . '> <http://www.example.org#hasConnectionTo> <' . $feedlink . '> . ';
                $insert .= '<' . $feedlink . '> <http://www.example.org#hasConnectionTo> <' . $item->getUri() . '> . ';
                $insert .= '<' . $item->getUri() . '> <http://www.example.org#hasConnectionToFeed> <' . $feedlink . '> . ';
                $insert .= '<' . $item->getUri() . '> <http://www.example.org#hasConnectionWeight> "' . $item->getWeight() . '". ';

                $mainTopics = array();

                if (!in_array($item->getUri(), $mainTopics) && $item->getIsMainTopic()) {

                    $insert .= '<' . $feedlink . '> <http://www.example.org#hasMainTopic> <' . $item->getUri() . '> .';
                    $insert .= '<' . $item->getUri() . '> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type>    <http://www.example.org#MainTopic> ;
                                <http://www.example.org#hasName> "' . $item->getName() . '" .';

                    array_push($mainTopics, $item->getUri());
                }
            }

            if ($lastConcept[0]->getUri() == $conceptValue[0]->getUri()) {
                $resultJson .= "]";
            } else {
                $resultJson .= "],";
            }
        }
        $insert .= '}';
        $result = $this->arc_store->query($insert, '', '', true);
        if ($errs = $this->arc_store->getErrors()) {
            echo("error:");
            print_r($errs);
            $errors = true;
        }



        $resultJson .= "}]";

        if (!$errors) {
            $response = '{"response": [{ "status": 200, "function":"insertUserQuery" ,"message":"Entities are successfully added to User Profile", ' . $resultJson . '}]}';
            echo($response);
            return $response;
        } else {
            $response = '{"response": [{ "status": 400, "function":"insertUserQuery" , "message":"ERROR: Entities NOT added to User Profile" }]}';
            echo($response);
            return $response;
        }
    }

    private function getConnectionWeightOfTerms() {
        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT *
                    WHERE
                    {
                        ?connection rdf:type ex:Connection .
                        ?concept rdf:type ex:Concept .
                        ?connection ex:connectionHasConcept ?concept .
                        ?connection ex:hasConnectionWeight ?weight .
                        ?connection ex:connectionHasName ?conName .
                        ?concept ex:hasName ?name .

                    } ';

        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectQuery" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
            print_r($errs);
        }

        $topics = array();
        $connections = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {


            foreach ($rows as $row) {

                if (!isset($connections[$row['connection']])) {
                    $connections[$row['connection']] = array();
                }
                if (!isset($connections[$row['connection']]['concept'])) {
                    $connections[$row['connection']]['concept'] = array();
                }
                $concept = array();
                $concept['name'] = $row['name'];
                $concept['uri'] = $row['concept'];
                $connections[$row['connection']]['weight'] = $row['weight'];
                $connections[$row['connection']]['connectionName'] = $row['conName'];
                array_push($connections[$row['connection']]['concept'], $concept);
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectQuery" ,"message":"No data returned"}]}';
            print_r($errs);
        }


        return $connections;
    }

    public function selectFeedsForUser($concepts) {

        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT *
                    WHERE
                    {
                        ?feed rdf:type ex:FeedURI .
                        ?concept rdf:type ex:Concept .
                        ?concept ex:hasName ?name .
                        ?concept ex:hasConnectionToFeed ?feed . 
                        
                        FILTER (';

                        $last = end($concepts);
                        foreach ($concepts as $concept) {
                            
                            $q .= ' ?concept = <' . $concept['concept'] . '>';
                            
                            if ($last['concept'] == $concept['concept']) {
                                $q .= ' ) ';
                            } else {
                                $q .= ' || ';
                            }
                        }
                        $q .= '}  LIMIT 25';

    
        if ($errs = $this->arc_store->getErrors()) {
            print_r($errs);
            return '{"response": [{ "function":"selectFeedQuery" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
        }

        $feeds = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {
                $concept['uri'] = $row['concept'];
                $concept['name'] = $row['name'];
                if (isset($feeds[$row['feed']])) {  
                    
                    if(!in_array($concept, $feeds[$row['feed']]['concept']))
                        $feeds[$row['feed']]['concept'][] = $concept;
                } else {
                    $feeds[$row['feed']]['url'] = $row['feed'];
                    $feeds[$row['feed']]['concept'] = array();
                    $feeds[$row['feed']]['concept'][] = $concept;
                }
                                
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectFeedQuery" ,"message":"No data returned"}]}';
            print_r($errs);
        }

        return $feeds;
    }

    public function selectTopUserInterests($version = 1) {

        switch ($version) {
            case 1:
                return $this->getTopRankedConcepts();

                break;
            case 2:
                return $this->getMainTopicsOfUser();
                break;
            case 3:
                return $this->getKeywordsOfUser();

                break;
            default:
                break;
        }
        /*
          $q = self::PREFIX_EX . ' ' .
          self::PREFIX_OX . ' ' .
          self::PREFIX_RDF . ' ' .
          'SELECT DISTINCT ?concept ?name ?count ?connection ?isKeyword (COUNT(?connection) as ?weight)
          WHERE
          {
          ?concept rdf:type ex:Concept .
          ?concept  <http://www.example.org#hasName> ?name .
          OPTIONAL {
          ?connection <http://www.example.org#hasConnectionTo> ?concept .
          }
          OPTIONAL
          {
          ?concept <http://www.example.org#isKeyword> ?isKeyword .

          }
          OPTIONAL {
          ?concept  <http://www.example.org#hasCount> ?count .
          }
          } GROUP BY ?concept ORDER BY DESC(xsd:integer(?weight))';

          if ($errs = $this->arc_store->getErrors()) {
          echo '{"response": [{ "function":"selectQuery" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
          print_r($errs);
          }

          $topics = array();
          if ($rows = $this->arc_store->query($q, 'rows')) {

          foreach ($rows as $row) {
          if (isset($row['name'])) {
          $topics[$row['concept']]['name'] = $row['name'];
          }

          if (isset($row['count'])) {
          $topics[$row['concept']]['count'] = $row['count'];
          }

          $topics[$row['concept']]['uri'] = $row['concept'];
          if (isset($row['connection'])) {
          $connection = array();
          $connection['uri'] = $row['connection'];
          //$topics[$row['concept']]['connection'] = $connection;
          }
          if (isset($row['isKeyword'])) {
          $connection = array();
          $topics[$row['concept']]['isKeyword'] = $row['isKeyword'];
          //$topics[$row['concept']]['connection'] = $connection;
          }

          if (isset($row['weight']) && $row['weight'] > 0) {
          if (!isset($topics[$row['concept']]['weight'])) {
          $topics[$row['concept']]['weight'] = $row['weight'];
          } else {
          $weight = $topics[$row['concept']]['weight'];
          $weight += $row['weight'];
          $topics[$row['concept']]['weight'] = $weight;
          }
          }
          }
          } else {
          echo '{"response": [{ status: 200, "function":"selectQuery" ,"message":"No data returned"}]}';
          print_r($errs);
          }
         * 
         */
        /*
          echo("----------debug information----------");

          $connections = $this->getConnectionWeightOfTerms();

          echo("<h1>Connections with weight</h1><ul>");
          foreach ($connections as $key => $value) {
          echo("<li>" . $value['connectionName'] . ": " . $value['weight'] . "</li>");
          }
          echo("</ul>");


          echo("<h1>Main Topics </h1><ul>");
          foreach ($this->getMainTopicsOfUser() as $topic) {
          echo("<li>" . $topic['concept'] . ": " . $topic['topic'] . "</li>");
          }
          echo("</ul>");

          echo("----------end debug information----------");
         *
         */
//        return $topics;
    }

    private function getTopRankedConcepts() {
        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT ?name ?concept ?count
                    WHERE
                    {
                        ?concept rdf:type ex:Concept .
                        ?concept ex:hasName ?name .
                        ?concept ex:hasCount ?count .
                    } GROUP BY ?concept ORDER BY DESC(xsd:integer(?count)) LIMIT 20';

        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectQueryTopRankedConcepts" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
            print_r($errs);
        }

        $mainConcepts = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {

                $concept = array();
                $concept['name'] = $row['name'];
                $concept['concept'] = $row['concept'];
                $concept['count'] = $row['count'];
                array_push($mainConcepts, $concept);
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectQueryTopRankedConcepts" ,"message":"No data returned"}]}';
            print_r($errs);
        }
        return $mainConcepts;
    }

    private function getMainTopicsOfUser() {

        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT ?name ?topic ?count
                    WHERE
                    {
                        ?topic rdf:type ex:MainTopic .
                        ?concept ex:hasMainTopic ?topic .
                        ?topic ex:hasName ?name .
                        ?topic ex:hasCount ?count .
                    } GROUP BY ?topic ORDER BY DESC(xsd:integer(?count))';
        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectQueryMainTopics" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
            print_r($errs);
        }

        $mainTopics = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {

                $mainTopic = array();
                $mainTopic['name'] = $row['name'];
                $mainTopic['concept'] = $row['topic'];
                $mainTopic['count'] = $row['count'];
                array_push($mainTopics, $mainTopic);
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectQueryMainTopics" ,"message":"No data returned"}]}';
            print_r($errs);
        }
        return $mainTopics;
    }

    private function getKeywordsOfUser() {
        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT ?concept ?name ?count 
                    WHERE
                    {
                        ?concept rdf:type ex:Concept .
                        ?concept  <http://www.example.org#hasName> ?name .
                         ?concept <http://www.example.org#isKeyword> ?bool .

                        OPTIONAL {
                            ?concept  <http://www.example.org#hasCount> ?count .

                        }
                    } GROUP BY ?concept ORDER BY DESC(xsd:integer(?count)) ';


        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectQueryKeywordsOfUser" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
            print_r($errs);
            die("stop because of error in getKeywordsOfUser");
        }
        $topics = array();
        $connections = array();
        $concepts = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {
                //print_r($row);
                $concept = array();
                $concept['concept'] = $row['concept'];
                $concept['name'] = $row['name'];
                if (isset($row['count'])) {
                    $concept['count'] = $row['count'];
                }
                array_push($concepts, $concept);
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectQueryKeywordsOfUser" ,"message":"No data returned"}]}';
            print_r($errs);
        }

        return $concepts;
    }

    private function initDBStore() {

        $config_xml = $this->loadConfigFile();

        $db_config = array(
            'db_host' => (string) $config_xml->database->db_host,
            'db_name' => (string) $config_xml->database->db_name,
            'db_user' => (string) $config_xml->database->db_user,
            'db_pwd' => (string) $config_xml->database->db_pwd,
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

            $this->arc_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/Profile_v1.owl>');
        }
        //$this->arc_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/Profile_v1.owl>');

        if ($errs = $this->arc_store->getErrors()) {
            echo("ERROR in INIT DB: ");
            print_r($errs);
        }
    }

    public function initDBpediaDump() {
        $config_xml = $this->loadConfigFile();

        $dbpedia_config = array(
            'db_host' => (string) $config_xml->database->db_host,
            'db_name' => (string) $config_xml->database->db_name,
            'db_user' => (string) $config_xml->database->db_user,
            'db_pwd' => (string) $config_xml->database->db_pwd,
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

            $this->dbpedia_store->query('LOAD <' . (string) $config_xml->fileBaseURL . '/config/skos_categories_en.nt>');
        }

        if ($errs = $this->dbpedia_store->getErrors()) {
            echo("ERROR in INIT DBpedia Dump: ");
            print_r($errs);
        }
    }

    private function loadConfigFile() {
        return simplexml_load_file("../config/config.xml");
    }

    /*     * **** debugging methods **** */

    public function selectAllUserInterests() {

        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT ?concept ?name ?count ?connection ?isKeyword (COUNT(?connection) as ?weight)
                    WHERE
                    {
                        ?concept rdf:type ex:Concept .
                        ?concept  <http://www.example.org#hasName> ?name .
                        OPTIONAL {
                            ?connection <http://www.example.org#hasConnectionTo> ?concept .
                        }
                        OPTIONAL
                        {
                           ?concept <http://www.example.org#isKeyword> ?isKeyword .

                        }
                        ?concept <http://www.example.org#hasConnectionToUser> <' . $this->user_id . '> .
                        OPTIONAL {
                            ?concept  <http://www.example.org#hasCount> ?count .
                        }
                    } GROUP BY ?concept ORDER BY DESC(xsd:integer(?weight))';

        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectQueryAllUserInterests" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
            print_r($errs);
        }

        $topics = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {
//print_r($row);
                if (isset($row['name'])) {
                    $topics[$row['concept']]['name'] = $row['name'];
                }

                if (isset($row['count'])) {
                    $topics[$row['concept']]['count'] = $row['count'];
                }

                $topics[$row['concept']]['uri'] = $row['concept'];
                if (isset($row['connection'])) {
                    $connection = array();
                    $connection['uri'] = $row['connection'];
                    //$topics[$row['concept']]['connection'] = $connection;
                }
                if (isset($row['isKeyword'])) {
                    $connection = array();
                    $topics[$row['concept']]['isKeyword'] = $row['isKeyword'];
                    //$topics[$row['concept']]['connection'] = $connection;
                }

                if (isset($row['weight']) && $row['weight'] > 0) {
                    if (!isset($topics[$row['concept']]['weight'])) {
                        $topics[$row['concept']]['weight'] = $row['weight'];
                    } else {
                        $weight = $topics[$row['concept']]['weight'];
                        $weight += $row['weight'];
                        $topics[$row['concept']]['weight'] = $weight;
                    }
                }
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectQueryAllUserInterests" ,"message":"No data returned"}]}';
            print_r($errs);
        }
        /*
          echo("----------debug information----------");

          $connections = $this->getConnectionWeightOfTerms();

          echo("<h1>Connections with weight</h1><ul>");
          foreach ($connections as $key => $value) {
          echo("<li>" . $value['connectionName'] . ": " . $value['weight'] . "</li>");
          }
          echo("</ul>");


          echo("<h1>Main Topics </h1><ul>");
          foreach ($this->getMainTopicsOfUser() as $topic) {
          echo("<li>" . $topic['concept'] . ": " . $topic['topic'] . "</li>");
          }
          echo("</ul>");

          echo("----------end debug information----------");
         *
         */
        return $topics;
    }

    public function selectAllFeedQuery() {

        $q = self::PREFIX_EX . ' ' .
                self::PREFIX_OX . ' ' .
                self::PREFIX_RDF . ' ' .
                'SELECT DISTINCT *
                    WHERE
                    {
                        ?feed rdf:type ex:FeedURI .

                        ?concept rdf:type ex:Concept .
                        ?concept  <http://www.example.org#hasName> ?name .
                        ?concept  <http://www.example.org#hasConnectionToFeed> ?feed .
                        OPTIONAL 
                        {
                        ?concept  <http://www.example.org#hasConnectionWeight> ?weight .
                        }
                    } GROUP BY ?concept';


        if ($errs = $this->arc_store->getErrors()) {
            echo '{"response": [{ "function":"selectFeedQuery" ,"message":"Error in SELECT", "error:"' . $errs . '}]}';
            print_r($errs);
        }

        $feeds = array();
        if ($rows = $this->arc_store->query($q, 'rows')) {

            foreach ($rows as $row) {
                //print_r($row);
                if (isset($row['feed']))
                    $feeds[$row['feed']]['uri'] = $row['feed'];
                if (isset($row['concept'])) {
                    if (!isset($feeds[$row['feed']]['concept'])) {
                        $feeds[$row['feed']]['concept'] = array();
                    }
                    $feeds[$row['feed']]['concept'][] = $row['concept'];
                }
                /*  if(isset($row['concept']))
                  $feeds[$row['concept']]['uri'] = $row['concept'];
                  if(isset($row['name']))
                  $feeds[$row['concept']]['name'] = $row['name'];
                 */
            }
        } else {
            echo '{"response": [{ status: 200, "function":"selectFeedQuery" ,"message":"No data returned"}]}';
            print_r($errs);
        }

        return $feeds;
    }

}

?>
