<?php
include_once("MainController.php");
session_start();
?>

<?php
//unset($_SESSION['controller']);
require_once 'processAction.php';
?>


<!DOCTYPE html>
<html>
    <head>
        <title></title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <LINK href="css/styles.css" rel="stylesheet" type="text/css">

        <script type="text/javascript" src="js/jquery-1.8.2.js"></script>
        <script type="text/javascript" src="js/jquery-ui-1.9.0.custom.min.js"></script>
        <!--main script -->
        <script type="text/javascript">
            
            $(document).ready(function(){
                           
            });


        </script>
    </head>
    <body>
        <div id="container">
            <div id="left_content">

            <h1>News Feed</h1>
            <?php
                $feeds = processAction_printFeedsForUser();

                foreach ($feeds as $feed) {
                    echo "<div class='feeditem content-container'>";
                    
                    /*******print headline + content*******/
                    /*$textArray = processAction_printTextOfURL($feed['url']);
                    echo "<h2>". $textArray['title'] . "</h2>";
                    echo "<p>". substr($textArray['text'], 0, 50). "...</p>";
                    */
                    /*******print link*******/                   
                    echo '<p class="item-link">';
                    //echo "'>".$textArray['url']."</a></p>";
                    echo '<a href="'.$feed['url'].'">'.$feed['url'].'</a></p>';

                    /*******print saved concepts*******/
                    if(isset($feed['concept'])){
                        //print_r($feed['concept']);
                        $last = end($feed['concept']);
                        echo "<div class='item-concepts'><span>Linked Concepts: ";
                        foreach($feed['concept'] as $concept){
                            echo $concept['name'];
                            if($last != $concept){
                                echo ", ";
                            }
                        }
                        echo ("</span></div>");
                    }
                        
                     echo "</div>";

                }

                
            ?>
        </div>

        <div id="right_content">
            <div id="interests">

                <h1>Interests</h1>
                <?php
                    $userInterests = processAction_printInterests();
                    
                    echo "<ul>";

                    foreach ($userInterests as $topic) {
                        echo "<li>";
                        if(isset($topic['name']))
                            echo $topic['name'];   
                        if(isset($topic['count']))
                            echo " (count: ". $topic['count'].")";
                        echo "</li>";
                    }

                    echo "</ul>";

                ?>
            </div>
        </div>
            </div>
    </body>
</html>
