<?php

	if (!isset($_REQUEST['a'])) {
		exit("need method");
	}
	$action = $_REQUEST['a'];
	require_once "config.php";
	switch ($action) {
		case "cng":
			createNewGame($link);
			break;
		case "gtc":
			getInitialCards($link);
			break;
		case "fcr":
			finishCurrentRound($link);
			break;
		case "pdc":
			playerDrawCards($link);
			break;
		case "gcr":
			getCurrentRound($link);
			break;
		case "sts":
			setTrumpSuit($link);
			break;
		case "rpc":
			regretPostedCard($link);
			break;
		case "gst":
			getStartTimestamp($link);
			break;
		default:
			exit("method not exist");
	}
	
	/*
		Dealer action
	*/
	function createNewGame($link)
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
		$sql = "UPDATE Games SET players = ?, cardsJson = ?, master = ?, points = 0, trumpRank = ?, trumpSuit = '', gameStartTimeStamp = NOW() WHERE id = 1";
		if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ssss", $playerInput, $pokerJson, $masterID, $trumpRank);
			if(mysqli_stmt_execute($stmt)){
				echo "pass";
			} else {
				echo "error";
			}
		}
		
		$sql2 = "delete from Rounds where 1";
		if ($link->query($sql2) === TRUE) {
			echo "Record deleted successfully";
			foreach ($playerIDs as $pid)
			{
				$sql3 = "INSERT INTO Rounds (pid, cards) VALUES (?, ?)";
         
				if($stmt = mysqli_prepare($link, $sql3)){
					// Bind variables to the prepared statement as parameters
					mysqli_stmt_bind_param($stmt, "ss", $param_pid, $param_cards);
					
					// Set parameters
					$param_pid = $pid;
					$param_cards = "";
					mysqli_stmt_execute($stmt);	
					// Close statement
					mysqli_stmt_close($stmt);
				}
			}
			
		} else {
			echo "Error deleting record: " . $conn->error;
		}
	}
	
	function finishCurrentRound($link)
	{
		//input points
		$points = $_REQUEST["p"];
		$points = (int)$points;
		//ajax call by click button
		
		//check currentRound finish or not
		
		//update game points
		$sql = "UPDATE Games SET points = points + ? WHERE id = 1";
		if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "i", $points);
			if(mysqli_stmt_execute($stmt)){
				echo "pass";
			} else {
				echo "error";
			}
		}
		
		$sql2 = "UPDATE Rounds SET cards = '' WHERE 1";
		$link->query($sql2);
		
		//return success / false
	}
	
	
	/*
		Master action
	*/
	
	
	
	
	
	
	
	/*
		Player action
	*/
	function playerDrawCards($link)
	{
		//input1, playerID
		$pid = $_REQUEST["p"];
		//input2, card json [{'3','S'},{'3','S'},{'3','S'}]
		$cards = $_REQUEST["c"];
		//input3, rounds, valid purpose (TODO)
		
		
		
		// update card json to rounds db
		$sql = "UPDATE Rounds SET cards = ? WHERE pid = ? and cards = ''";
		if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ss", $cards, $pid);
			if(mysqli_stmt_execute($stmt)){
				if ($stmt->affected_rows == 1) {
					echo "success";
				}
			} else {
				echo "error";
			}
		}
	}
	
	function getCurrentRound($link)
	{
		//持续ajax 1秒多次 或 by button onclick
		//output1: current round number
		//output2: [{id:1,card:{R:'',S:''}},{id:2,card:{R:'',S:''}},{id:3,card:{R:'',S:''}}]
		$returnArray = array();
		
		
		$sql = "SELECT pid, cards FROM Rounds";
		$result = $link->query($sql);
		$playaerCards = array();
		$players = $points = "";
		while($row = $result->fetch_assoc()) {
			// echo "id: " . $row["id"]. " - Name: " . $row["firstname"]. " " . $row["lastname"]. "<br>";
			$playaerCards[$row["pid"]] = $row["cards"];
		}
		
		
		$sql = "SELECT players, points, trumpRank, trumpSuit, gameStartTimeStamp FROM Games where id = 1";
		$result = $link->query($sql);
		while($row = $result->fetch_assoc()) {
			$players = $row["players"];
			$points = $row["points"];
			$trumpRank = $row["trumpRank"];
			$trumpSuit = $row["trumpSuit"];
			$gameStartTimeStamp = $row["gameStartTimeStamp"];
		}
		
		
		$sql = "SELECT id, username FROM users where id in ($players)";
		$result = $link->query($sql);
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
	
	function getInitialCards($link)
	{
		$playerID = $_REQUEST['p'];
		$sql = "select players, master, cardsJson from Games where id = ?";
		if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
			$gameID = 1;
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
	
	
	function setTrumpSuit($link)
	{
		$trumpSuit = $_REQUEST["ts"];
		
		// update if empty
		// return failed if 0 affected_rows
		// update card json to rounds db
		$sql = "UPDATE Games SET trumpSuit = ? WHERE id = 1 and trumpSuit = ''";
		if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $trumpSuit);
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
	
	
	function regretPostedCard($link)
	{
		// input player id
		// select cards from rounds by pid
		// if exist, return cards;
		// else if not exist, return "empty"
		$playerID = $_REQUEST["p"];
		$sql = "select cards from Rounds where pid = ? and cards <>''";
		if($stmt = mysqli_prepare($link, $sql)){
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "s", $playerID);
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
						$sql2 = "UPDATE Rounds SET cards = '' WHERE pid = ?";
						if($stmt2 = mysqli_prepare($link, $sql2)){
							// Bind variables to the prepared statement as parameters
							mysqli_stmt_bind_param($stmt2, "s", $playerID);
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
	
	function getStartTimestamp($link)
	{
		$gameID = 1;
		$sql = "select gameStartTimeStamp from Games where id = ?";
		if($stmt = mysqli_prepare($link, $sql)){
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
	
	
?>