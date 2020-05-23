<?php
	require_once "config.php";
	$apiRequest = new EightyPointsApi($link);
	$apiRequest->setParameters();
	$apiRequest->processApi();
	
	
	class EightyPointsApi
	{
		public $gameID;
		public $playerID;
		public $apiAction;
		public $link;
		
		function __construct($link)
		{
			$this->link = $link;
		}
	
		public function setParameters()
		{
			if (!isset($_REQUEST['a'])) {
				exit("need method");
			}
			$this->action = $_REQUEST['a'];
			
			if (!isset($_REQUEST['gid'])) {
				// exit("no game id");
			}
			$this->gameID = $_REQUEST['gid'];
		}
	
		public function processApi()
		{
			switch ($this->action) {
				case "cng":
					$this->createNewGame();
					break;
				case "gtc":
					$this->getInitialCards();
					break;
				case "fcr":
					$this->finishCurrentRound();
					break;
				case "pdc":
					$this->playerDrawCards();
					break;
				case "gcr":
					$this->getCurrentRound();
					break;
				case "sts":
					$this->setTrumpSuit();
					break;
				case "rpc":
					$this->regretPostedCard();
					break;
				case "gst":
					$this->getStartTimestamp();
					break;
				default:
					exit("method not exist");
			}
		}
		
		
		/*
			Dealer action
		*/
		function createNewGame()
		{
			//input: playerNumber
			$playerInput = $_REQUEST['p'];
			$playerIDs = explode(",", $playerInput);
			$playerAmt = sizeof($playerIDs);
			if (!in_array($playerAmt, [4, 6, 8, 10])){
				exit("error");
			}
			
			//input: master id
			$masterID = $_REQUEST['m'];
			$trumpRank = $_REQUEST['r'];
			
			//shuffle
			$deck = $playerAmt / 2;
			$rank = ['A', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K'];
			$shape = ['C', 'D', 'H', 'S'];
			$pokers = [];
			for ($i = 0; $i < $deck; $i++) {
				foreach ($shape as $s) {
					foreach ($rank as $r) {
						$pokers[] = ['r' => $r, 's' => $s];
					}
				}
				$pokers[] = ['r' => 'N', 's' => '1'];
				$pokers[] = ['r' => 'N', 's' => '2'];
			}
			shuffle($pokers);
			
			$PokerSpread = [];
			
			//spread
			$pCount = 0;
			$handCardAmount = $playerAmt == "4" ? 25 : 26;
			foreach ($playerIDs as $pid) {
				if ($pid != $masterID) {
					$PokerSpread[$pid] = array_slice($pokers, $pCount * $handCardAmount, $handCardAmount);
					$pCount++;
				}
			}
			$masterRemaining = ['4'=>8,'6'=>6, '8'=>8, '10'=>10];
			$PokerSpread[$masterID] = array_slice($pokers, $pCount * $handCardAmount, $handCardAmount + (int)$masterRemaining[$playerAmt]);
			
			$pokerJson = json_encode($PokerSpread);
			//update game db
			//prepare SQL
			$sql = "UPDATE Games SET players = ?, cardsJson = ?, master = ?, points = 0, trumpRank = ?, trumpSuit = '', gameStartTimeStamp = NOW() WHERE id = ?";
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				mysqli_stmt_bind_param($stmt, "sssss", $playerInput, $pokerJson, $masterID, $trumpRank, $this->gameID);
				if(mysqli_stmt_execute($stmt)){
					echo "pass";
				} else {
					echo "error";
				}
			}
			
			$sql2 = "delete from Rounds where gameID = " . (int)$this->gameID;
			if ($this->link->query($sql2) === TRUE) {
				echo "Record deleted successfully";
				foreach ($playerIDs as $pid)
				{
					$sql3 = "INSERT INTO Rounds (pid, cards, gameID) VALUES (?, ?, ?)";
			 
					if($stmt = mysqli_prepare($this->link, $sql3)){
						// Bind variables to the prepared statement as parameters
						mysqli_stmt_bind_param($stmt, "sss", $param_pid, $param_cards, $gid);
						
						// Set parameters
						$param_pid = $pid;
						$param_cards = "";
						$gid = $this->gameID;
						mysqli_stmt_execute($stmt);	
						// Close statement
						mysqli_stmt_close($stmt);
					}
				}
				
			} else {
				echo "Error deleting record: " . $conn->error;
			}
		}
		
		function finishCurrentRound()
		{
			//input points
			$points = $_REQUEST["p"];
			$points = (int)$points;
			//ajax call by click button
			
			//check currentRound finish or not
			
			//update game points
			$sql = "UPDATE Games SET points = points + ? WHERE id = ?";
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				mysqli_stmt_bind_param($stmt, "is", $points, $this->gameID);
				if(mysqli_stmt_execute($stmt)){
					echo "pass";
				} else {
					echo "error";
				}
			}
			
			$sql2 = "UPDATE Rounds SET cards = '' WHERE 1";
			$this->link->query($sql2);
			
			//return success / false
		}
		
		
		/*
			Master action
		*/
		
		
		
		
		
		
		
		/*
			Player action
		*/
		function playerDrawCards()
		{
			//input1, playerID
			$pid = $_REQUEST["p"];
			$gid = $_REQUEST["gid"];
			//input2, card json [{'3','S'},{'3','S'},{'3','S'}]
			$cards = $_REQUEST["c"];
			//input3, rounds, valid purpose (TODO)
			
			
			
			// update card json to rounds db
			$sql = "UPDATE Rounds SET cards = ? WHERE pid = ? and cards = '' and gameID = ?";
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				mysqli_stmt_bind_param($stmt, "sss", $cards, $pid, $this->gameID);
				if(mysqli_stmt_execute($stmt)){
					if ($stmt->affected_rows == 1) {
						echo "success";
						// if success, delete the card in game db;
						
					}
				} else {
					echo "error";
				}
			}
		}
		
		function getCurrentRound()
		{
			//持续ajax 1秒多次 或 by button onclick
			//output1: current round number
			//output2: [{id:1,card:{R:'',S:''}},{id:2,card:{R:'',S:''}},{id:3,card:{R:'',S:''}}]
			$returnArray = array();
			
			
			$sql = "SELECT pid, cards FROM Rounds where gameID = " . (int)$this->gameID;
			$result = $this->link->query($sql);
			$playaerCards = array();
			$players = $points = "";
			while($row = $result->fetch_assoc()) {
				// echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
				$playaerCards[$row["pid"]] = $row["cards"];
			}
			
			
			$sql = "SELECT players, points, trumpRank, trumpSuit, gameStartTimeStamp FROM Games where id = " . (int)$this->gameID;
			$result = $this->link->query($sql);
			while($row = $result->fetch_assoc()) {
				$players = $row["players"];
				$points = $row["points"];
				$trumpRank = $row["trumpRank"];
				$trumpSuit = $row["trumpSuit"];
				$gameStartTimeStamp = $row["gameStartTimeStamp"];
			}
			
			
			$sql = "SELECT id, username FROM users where id in ($players)";
			$result = $this->link->query($sql);
			$playerNames = array();
			while($row = $result->fetch_assoc()) {
				// echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
				$playerNames[$row["id"]] = $row["username"];
			}
			
			
			if ($points == "" || empty($players) || empty($playaerCards)) {
				exit("error");
			}
			$playerArray = explode(",", $players);
			$counter = 0;
			foreach ($playerArray as $pid) {
				$returnArray["pl"][$counter] = array($pid => $playaerCards[$pid]);
				$returnArray["nm"][$counter] = array($pid => $playerNames[$pid]);
				$counter++;
			}
			
			$returnArray["pt"] = $points;
			$returnArray["tr"] = $trumpRank;
			$returnArray["ts"] = $trumpSuit;
			$returnArray["tm"] = $gameStartTimeStamp;
			//$returnArray["nm"] = $playerNames;
			echo json_encode($returnArray);
		}
		
		function getInitialCards()
		{
			$playerID = $_REQUEST['p'];
			$sql = "select players, master, cardsJson from Games where id = ?";
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				$gameID = $this->gameID;
				mysqli_stmt_bind_param($stmt, "s", $gameID);
				if(mysqli_stmt_execute($stmt)){
					// Store result
					mysqli_stmt_store_result($stmt);
					
					// Check if username exists, if yes then verify password
					if(mysqli_stmt_num_rows($stmt) == 1){                  
						// Bind result variables
						mysqli_stmt_bind_result($stmt, $players, $master, $cardsJson);
						if(mysqli_stmt_fetch($stmt)){
							$cardsArray = json_decode($cardsJson, true);
							echo json_encode($cardsArray[$playerID]);
						}
					}
				}
			}
		}
		
		
		function setTrumpSuit()
		{
			$trumpSuit = $_REQUEST["ts"];
			
			// update if empty
			// return failed if 0 affected_rows
			// update card json to rounds db
			$sql = "UPDATE Games SET trumpSuit = ? WHERE id = ? and trumpSuit = ''";
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				mysqli_stmt_bind_param($stmt, "ss", $trumpSuit, $this->gameID);
				if(mysqli_stmt_execute($stmt)){
					if ($stmt->affected_rows == 1) {
						echo "success";
					} else {
						echo "failed";
					}
				} else {
					echo "error";
				}
			}
		}
		
		
		function regretPostedCard()
		{
			// input player id
			// select cards from rounds by pid
			// if exist, return cards;
			// else if not exist, return "empty"
			$playerID = $_REQUEST["p"];
			$sql = "select cards from Rounds where pid = ? and cards <>'' and gameID = ?";
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				mysqli_stmt_bind_param($stmt, "ss", $playerID, $this->gameID);
				if(mysqli_stmt_execute($stmt)){
					// Store result
					mysqli_stmt_store_result($stmt);
					
					// Check if username exists, if yes then verify password
					if(mysqli_stmt_num_rows($stmt) == 1){                  
						// Bind result variables
						mysqli_stmt_bind_result($stmt, $cardsJson);
						if(mysqli_stmt_fetch($stmt)){
							// $cardsArray = json_decode($cardsJson, true);
							// echo json_encode($cardsArray[$playerID]);
							$sql2 = "UPDATE Rounds SET cards = '' WHERE pid = ? and gameID = ?";
							if($stmt2 = mysqli_prepare($this->link, $sql2)){
								// Bind variables to the prepared statement as parameters
								mysqli_stmt_bind_param($stmt2, "ss", $playerID, $this->gameID);
								if(mysqli_stmt_execute($stmt2)){
									if ($stmt2->affected_rows == 1) {
										echo $cardsJson;
									}
								} else {
									echo "error";
								}
							}
						}
					} else {
						echo "empty";
					}
				}
			}
		}
		
		function getStartTimestamp()
		{
			$gameID = (int)$this->gameID;
			$sql = "select gameStartTimeStamp from Games where id = ?";
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				mysqli_stmt_bind_param($stmt, "s", $gameID);
				if(mysqli_stmt_execute($stmt)){
					// Store result
					mysqli_stmt_store_result($stmt);
					
					// Check if username exists, if yes then verify password
					if(mysqli_stmt_num_rows($stmt) == 1){                  
						// Bind result variables
						mysqli_stmt_bind_result($stmt, $gameStartTimeStamp);
						if(mysqli_stmt_fetch($stmt)){
							//$cardsArray = json_decode($cardsJson, true);
							//echo json_encode($cardsArray[$playerID]);
							echo $gameStartTimeStamp;
						}
					}
				}
			}
		}
	}
?>