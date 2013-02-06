<?php include_once("MainController.php");
session_start(); ?>

<?php

//unset($_SESSION['controller']);
require_once 'processAction.php';

?>


<!DOCTYPE html>
<html>
    <head>
        <title></title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <script type="text/javascript" src="jquery-1.8.2.js"></script>
        <script type="text/javascript" src="jquery-ui-1.9.0.custom.min.js"></script>

        <script type="text/javascript">
            $(document).ready(function(){


                 


            });


        </script>
    </head>
    <body>
        <div>
            <h1>Interests of Bianca Gotthart</h1>
                <?php

                 //echo $controller->printUserInterests();
                echo processAction_printInterests();
                ?>
            
        </div>
        <div>

            <!--
            <form action="processAction.php" method="post" id="addInterestForm">
                <input id="interestText" type="text" name="interestText" />
                <input id="addInterestButton" type="submit" value="Add Interest" />
                <input type="hidden" value="addInterestText" name="function" />
            </form>
            -->
        </div>
    </body>
</html>
