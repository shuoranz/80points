<?php
    require_once "config.php";
    $apiRequest = new registerApi($link);
    $apiRequest->setParameters();
    $apiRequest->processApi();

    class registerApi {

        public $link;

        function __construct($link) {
            $this->link = $link;
        }

        public function setParameters() {
            if (!isset($_REQUEST['a'])) {
                exit("need method");
            }
            $this->action = $_REQUEST['a'];
        }

        public function processApi() {
            switch ($this->action) {
                case "checkExistance":
                    $this->checkUserExistance();
                    break;
                default:
                    exit("method not exist");
            }
        }

        function checkUserExistance() {
            if (!isset($_POST['username'])) {
                exit("error: username is null");
            }
            $this->username = $_POST['username'];
            $returnArray = array();
            $sql = "SELECT id FROM users WHERE username = ?";
            if($stmt = mysqli_prepare($this->link, $sql)) {
                // Bind variables to the prepared statement as parameters
                mysqli_stmt_bind_param($stmt, "s", $this->username);
                
                // Set parameters
                $param_username = trim($this->username);
                
                // Attempt to execute the prepared statement
                if(mysqli_stmt_execute($stmt)) {
                    /* store result */
                    mysqli_stmt_store_result($stmt);
                    
                    if(mysqli_stmt_num_rows($stmt) == 1) {
                        $returnArray["exist"] = true;
                    } else {
                        $returnArray["exist"] = false;
                    }
                } else {
                    exit("sql error");
                }
                // Close statement
                mysqli_stmt_close($stmt);
                echo json_encode($returnArray); 
            }
        }
    }
?>