<?php
include("PHPCrawl_081/libs/PHPCrawler.class.php");

/*
$myarray = array();
$myarray['test'] = array();
if(isset($myarray['test'])){
    echo("yes");
}else{
    echo("no");
}
var_dump($myarray);

return;
*/

class MyCrawler extends PHPCrawler {

    private $pages_array;

    public function __construct() {
        parent::__construct();
        $this->pages_array = array();
    }

    public function getPagesArray() {
        return $this->pages_array;
    }

    function handleDocumentInfo($DocInfo) {
        // Just detect linebreak for output ("\n" in CLI-mode, otherwise "<br>").
        if (PHP_SAPI == "cli")
            $lb = "\n";
        else
            $lb = "<br />";

        $referer = $DocInfo->referer_url;
        $url = $DocInfo->url;
        
        if ($DocInfo->http_status_code == "200" && strlen($referer) > 0) {
            
            if(isset($this->pages_array[$referer])){
                array_push($this->pages_array[$referer], $url);
            }else{
                $this->pages_array[$referer] = array();
                array_push($this->pages_array[$referer], $url);
            }
           
        }
        /*
          // Print the URL and the HTTP-status-Code
          echo "Page requested: ".$DocInfo->url." (".$DocInfo->http_status_code.")".$lb;

          // Print the refering URL
          echo "Referer-page: ".$DocInfo->referer_url.$lb;

          // Print if the content of the document was be recieved or not
          if ($DocInfo->received == true)
          echo "Content received: ".$DocInfo->bytes_received." bytes".$lb;
          else
          echo "Content not received".$lb;

          // Now you should do something with the content of the actual
          // received page or file ($DocInfo->source), we skip it in this example

          echo $lb;
         */
        flush();
    }

}

$crawler = new MyCrawler();
//getAllFeeds
$crawler->setURL("http://techcrunch.com/");
// Only receive content of files with content-type "text/html"
$crawler->addContentTypeReceiveRule("#text/html#");

// Ignore links to pictures, dont even request pictures
$crawler->addURLFilterRule("#\.(jpg|jpeg|gif|png)$# i");

// Store and send cookie-data like a browser does
$crawler->enableCookieHandling(true);

// Set the traffic-limit to 1 MB (in bytes,
// for testing we dont want to "suck" the whole site)
$crawler->setTrafficLimit(1000 * 1024);
// Thats enough, now here we go
$crawler->go();

// At the end, after the process is finished, we print a short
// report (see method getProcessReport() for more information)
//$report = $crawler->getProcessReport();

echo("\n");

var_dump($crawler->getPagesArray());

if (PHP_SAPI == "cli")
    $lb = "\n";
else
    $lb = "<br />";

$lb;
/*
echo "Summary:" . $lb;
echo "Links followed: " . $report->links_followed . $lb;
echo "Documents received: " . $report->files_received . $lb;
echo "Bytes received: " . $report->bytes_received . " bytes" . $lb;
echo "Process runtime: " . $report->process_runtime . " sec" . $lb;
*/
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

function getNewsFeed() {
    echo("test newsfeed ");
}

function getInterests() {
    echo("test interests ");
}

function getAllNewsFeeds() {
    
}


?>

<html>
    <head><title>My personal Newsfeed</title></head>

    <body>
        <h1>Welcome Person A</h1>
        <section>
            <h2>Newsfeeds:</h2>
            <?php getAllNewsFeeds(); ?>
        </section>
        <section>
            <h2>News</h2>
            <?php
            getNewsFeed();
            ?>
        </section>
        <section>
            <h2>Interests</h2>
            <?php
            getInterests();
            ?>
        </section>
    </body>
</html>