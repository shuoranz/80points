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
			
			$playersArray = $playerIDs;
			$winner = $masterID;
			$gameID = $this->gameID;
			
			$winnerPosition = 0;
			foreach($playersArray as $player){
				if($player == $winner){
					break;
				}
				$winnerPosition++;
			}
			$array1 = array_slice($playersArray, 0, $winnerPosition);
			$array2 = array_slice($playersArray, $winnerPosition, sizeof($playersArray) - $winnerPosition);
			$arrayResult = array_merge($array2, $array1);
			$newPosition = 1;
			foreach ($arrayResult as $elm)
			{
				$sql2 = "UPDATE Rounds SET cards = '', thisRoundOrder = ".$newPosition." WHERE pid = ".$elm." and gameID = ".(int)$gameID;
				$this->link->query($sql2);
				$newPosition++;
			}
		}
		
		function finishCurrentRound()
		{
			$roundResultArray = $this->settleRoundPoints();
			if ($roundResultArray["result"] == "auto") {
				$points = (int)$roundResultArray["points"];
				$winner = (int)$roundResultArray["winner"];
			} else if ($roundResultArray["result"] == "error") {
				exit("error");
			} else if ($roundResultArray["result"] == "manual") {
				if (!isset($_REQUEST["p"]) || !isset($_REQUEST["w"])) {
					exit("error");
				}
				$points = (int)$_REQUEST["p"];
				$winner = (int)$_REQUEST["w"];
			} else {
				exit("error");
			}
			
			// TODO:check currentRound finish or not
			
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
			
			$gameID = (int)$this->gameID;
			$sql = "select players from Games where id = ?";
			$playersArray = array();
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				mysqli_stmt_bind_param($stmt, "s", $gameID);
				if(mysqli_stmt_execute($stmt)){
					// Store result
					mysqli_stmt_store_result($stmt);
					
					// Check if username exists, if yes then verify password
					if(mysqli_stmt_num_rows($stmt) == 1){                  
						// Bind result variables
						mysqli_stmt_bind_result($stmt, $players);
						if(mysqli_stmt_fetch($stmt)){
							//$cardsArray = json_decode($cardsJson, true);
							//echo json_encode($cardsArray[$playerID]);
							$playersArray = explode(",", $players);
						}
					}
				}
			}
			$winnerPosition = 0;
			foreach($playersArray as $player){
				if($player == $winner){
					break;
				}
				$winnerPosition++;
			}
			$array1 = array_slice($playersArray, 0, $winnerPosition);
			$array2 = array_slice($playersArray, $winnerPosition, sizeof($playersArray) - $winnerPosition);
			$arrayResult = array_merge($array2, $array1);
			$newPosition = 1;
			foreach ($arrayResult as $elm)
			{
				$sql2 = "UPDATE Rounds SET cards = '', thisRoundOrder = ".$newPosition." WHERE pid = ".$elm." and gameID = ".(int)$gameID;
				$this->link->query($sql2);
				$newPosition++;
			}
			//return success / false
		}
		
		
		/*
			Player action
		*/
		function playerDrawCards()
		{
			//input1, playerID
			$pid = $_REQUEST["p"];
			//input2, card json [{'3','S'},{'3','S'},{'3','S'}]
			$cards = $_REQUEST["c"];
			//input3, rounds, valid purpose (TODO)
			
			// shoud the player play the card?
			$gameID = (int)$this->gameID;
			$sql = "select * from Rounds where cards = '' and thisRoundOrder < (select max(thisRoundOrder) from Rounds where cards = '' and pid = ? and gameID = ?)";
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				mysqli_stmt_bind_param($stmt, "ss", $gameID, $pid);
				if(mysqli_stmt_execute($stmt)){
					// Store result
					mysqli_stmt_store_result($stmt);
					
					// Check if username exists, if yes then verify password
					if(mysqli_stmt_num_rows($stmt) > 0){                  
						// Bind result variables
						/*
						mysqli_stmt_bind_result($stmt, $players);
						if(mysqli_stmt_fetch($stmt)){
							//$cardsArray = json_decode($cardsJson, true);
							//echo json_encode($cardsArray[$playerID]);
							$playersArray = explode(",", $players);
						}
						*/
						exit("notYourTurn");
					}
				}
			}
			
			
			// update card json to rounds db
			$sql = "UPDATE Rounds SET cards = ? WHERE pid = ? and cards = '' and gameID = ?";
			if($stmt = mysqli_prepare($this->link, $sql)){
				// Bind variables to the prepared statement as parameters
				mysqli_stmt_bind_param($stmt, "sss", $cards, $pid, $this->gameID);
				if(mysqli_stmt_execute($stmt)){
					if ($stmt->affected_rows == 1) {
						echo "success";
						// if success, delete the card in game db;
						$sqlSelect = "select * from Games where id = " . $this->gameID;
						$sqlResult = $this->link->query($sqlSelect);
						while($sqlRow = $sqlResult->fetch_assoc()) {
							$playersCards = $sqlRow["cardsJson"];
							$playersCardsArray = json_decode($playersCards, true);
							foreach(json_decode($cards, true) as $card)
							{
								$cardIndex = array_search($card, $playersCardsArray[$pid]);
								unset($playersCardsArray[$pid][$cardIndex]);
								$playersCardsArray[$pid] = array_values($playersCardsArray[$pid]);
							}
							$newCardsJson = json_encode($playersCardsArray);
							$sqlUpdate = "UPDATE Games SET cardsJson = ? WHERE id = ?";
							if($stmtUpdate = mysqli_prepare($this->link, $sqlUpdate)){
								// Bind variables to the prepared statement as parameters
								mysqli_stmt_bind_param($stmtUpdate, "ss", $newCardsJson, $this->gameID);
								mysqli_stmt_execute($stmtUpdate);
							}
						}
					}
				} else {
					echo "alreadyDarwn";
				}
			}
			
			
			/*
			 * Delete for now
			 * 
			 *
			//determine finish round or not
			$sql = "SELECT * FROM Rounds where card = '' and gameID = " . (int)$this->gameID;
			$result = $this->link->query($sql);
			if ($result = mysqli_query($con,$sql))
			{
				if (mysqli_num_rows($result) == 0)
				{
					$requestResult = file_get_contents('api.php?gid='.$this->gameID.'&a=fcr');
				}
			}
			*/
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
			$playerID = (int)$_REQUEST["p"];
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

										//after success regret, add cards back to game;
										$pid = (int)$playerID;
										$sqlSelect = "select * from Games where id = " . $this->gameID;
										$sqlResult = $this->link->query($sqlSelect);
										while($sqlRow = $sqlResult->fetch_assoc()) {
											$playersCards = $sqlRow["cardsJson"];
											$playersCardsArray = json_decode($playersCards, true);
											foreach(json_decode($cardsJson, true) as $card)
											{
												array_push($playersCardsArray[$pid],$card);
												$playersCardsArray[$pid] = array_values($playersCardsArray[$pid]);
											}
											$newCardsJson = json_encode($playersCardsArray);
											$sqlUpdate = "UPDATE Games SET cardsJson = ? WHERE id = ?";
											if($stmtUpdate = mysqli_prepare($this->link, $sqlUpdate)){
												// Bind variables to the prepared statement as parameters
												mysqli_stmt_bind_param($stmtUpdate, "ss", $newCardsJson, $this->gameID);
												mysqli_stmt_execute($stmtUpdate);
											}
										}
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
		
		
		function settleRoundPoints()
		{
			$returnResult = array();
			$returnResult["result"] = "error";
			
			$sql = "select players, master, trumpRank, trumpSuit from Games where id = ".(int)$this->gameID;
			$result = $this->link->query($sql);
			while($row0 = $result->fetch_assoc()) {
				$players = $row0['players'];
				$master = $row0['master'];
				$trumpRank = $row0['trumpRank'];
				$trumpSuit = $row0['trumpSuit'];
			}
			
			$sql = "select cards, thisRoundOrder, pid from Rounds where gameID = ".(int)$this->gameID." order by thisRoundOrder asc";
			$result = $this->link->query($sql);
			$winner = 0;
			$totalPoints = 0;
			while($row = $result->fetch_assoc()) {
				if ($row['cards'] == "") {
					// not all player played card.
					return $returnResult;
				}
				$cardsArray = json_decode($row['cards']);
				foreach ($cardsArray as $card) {
					if ($card->r == "5") {
						$totalPoints += 5;
					} else if ($card->r == "10" || $card->r == "K") {
						$totalPoints += 10;
					}
				}
				
				if ($row['thisRoundOrder'] == 1){
					$player2Cards = $row['cards'];
					$winner = $row['pid'];
				} else {
					$player1Cards = $player2Cards;
					$player2Cards = $row['cards'];
					$compareResult = $this->comparePlayerCards($player1Cards, $player2Cards, $trumpRank, $trumpSuit);
					//echo $compareResult . "<br>";
					if ($compareResult == "oneWin") {
						$player2Cards = $player1Cards;
					} else if ($compareResult == "twoWin") {
						$winner = $row['pid'];
					} else if ($compareResult == "notSure") {
						$returnResult["result"] = "manual";
						return $returnResult;
					}
				}
			}
			$returnResult["result"] = "auto";
			$returnResult["winner"] = $winner;
			$returnResult["points"] = $this->_isDealderWin($players, $master, $winner) ? 0 : $totalPoints;
			return $returnResult;
		}
		
		function comparePlayerCards($player1Cards, $player2Cards, $trumpRank, $trumpSuit)
		{
			//return "twoWin", "notSure", "oneWin"
			$baseCardsArray = json_decode($player1Cards);
			$newCardsArray = json_decode($player2Cards);
			
			$baseCardsArray = $this->_sortCards($baseCardsArray, $trumpRank, $trumpSuit);
			$newCardsArray = $this->_sortCards($newCardsArray, $trumpRank, $trumpSuit);
			
			//一共四种花色： 三普通 + 一主色 或五种花色：四普通+一主色
			$baseColor = $this->_isCardsSameColor($baseCardsArray, $trumpRank, $trumpSuit);
			$newColor = $this->_isCardsSameColor($newCardsArray, $trumpRank, $trumpSuit);
			
			
			if ($baseColor == 'F') {
				//如果基准牌是杂色，则手动
				return 'notSure';
			} else if ($baseColor != $newColor && $newColor != 'T'){
				//前后花色不一致且后牌不为主，不用比，前者赢
				return "oneWin";
			} else {
				//基准牌为纯色，前后牌颜色一致，或前后牌不一致，后牌为主
				//计算前后牌的牌型
				$baseType = $this->_getCardsTypeUnderSameColor($baseCardsArray, $trumpRank, $trumpSuit);
				$newType = $this->_getCardsTypeUnderSameColor($newCardsArray, $trumpRank, $trumpSuit);
				
				if ($baseType == 'NA') {
					//基本牌无牌型，则手动
					return 'notSure';
				} else if ($baseColor == $newColor && $baseType == $newType) {
					//若前后牌颜色一致,且牌型一致，比各第一张牌的大小
					if ($this->_compareTwoCardUnderSameColor($baseCardsArray[0], $newCardsArray[0], $trumpRank, $trumpSuit) >= 0) {
						return 'oneWin';
					} else {
						return 'twoWin';
					}
				} else if ($newColor == 'T' && $baseType == $newType) {
					//若前后花色不一致，且牌型一致，且后牌为主牌
					return 'twoWin';
				} else {
					return 'oneWin';
				}
			}
		}
		
		private function _isCardsSameColor($cardsArray, $trumpRank, $trumpSuit)
		{
			$baseColor = $cardsArray[0]->s;
			$baseRank = $cardsArray[0]->r;
			$sameColor = true;
			$trumpRound = true;
			
			if ($baseColor != $trumpSuit && $baseRank != $trumpRank && $baseRank != 'N') {
				$trumpRound = false;
			}
			if ($trumpRound)
			{
				foreach($cardsArray as $card)
				{
					if ($card->r != 'N' && $card->r != $trumpRank && $card->s != $trumpSuit){
						$sameColor = false;
						break;
					}
				}
			} else {
				foreach($cardsArray as $card)
				{
					if ($card->r == $trumpRank){
						$sameColor = false;
						break;
					}
					if ($card->s != $baseColor){
						$sameColor = false;
						break;
					}
				}
			}
			
			$returnResult = "";
			if ($trumpRound && $sameColor){
				$returnResult = "T";
			} else if (!$trumpRound && $sameColor){
				$returnResult = $baseColor;
			} else {
				$returnResult = "F";
			}
			return $returnResult;
		}
		
		private function getNormalCardPossibleTypes($cardLength)
		{
			switch ($cardLength) {
			case 1:
				return array('A');
				break;
			case 2:
				return array('AA');
				break;
			case 3:
				return array('AAA');
				break;
			case 4:
				return array('AAAA','AABB');
				break;
			case 5:
				return array('AAAAA');
				break;
			case 6:
				return array('AABBCC','AAABBB');
				break;
			case 8:
				return array('AABBCCDD','AAAABBBB');
				break;
			case 9:
				return array('AAABBBCCC');
				break;
			case 10:
				return array('AABBCCDDEE','AAAAABBBBB');
				break;
			case 12:
				return array('AABBCCDDEEFF');
				break;
			default:
				return array();
			}
		}
		
		private function _sortCards($baseCards, $trumpRank, $trumpSuit)
		{
			// sort order is very important
			usort($baseCards, function($a, $b){
				if ($a->r === $b->r)                       return 0;
				if ($a->r === "N")                          return 1;
				if ($b->r === "N")                          return -1;
				if ($a->r === "A")                          return 1;
				if ($b->r === "A")                          return -1;
				if (ctype_digit($a->r) &&  ctype_digit($b->r))    return $a->r - $b->r;
				if ($a->r == "K" && $b->r == "J")         return 1;
				if ($a->r == "J" && $b->r == "K")         return -1;
				if ($a->r == "K" && $b->r == "Q")         return 1;
				if ($a->r == "Q" && $b->r == "K")         return -1;
				if ($a->r == "Q" && $b->r == "J")         return 1;
				if ($a->r == "J" && $b->r == "Q")         return -1;
				if ($a->r == "K" && ctype_digit($b->r)) return 1;
				if ($a->r == "Q" && ctype_digit($b->r)) return 1;
				if ($a->r == "J" && ctype_digit($b->r)) return 1;
				if (ctype_digit($a->r) && $b->r == "K") return -1;
				if (ctype_digit($a->r) && $b->r == "Q") return -1;
				if (ctype_digit($a->r) && $b->r == "J") return -1;
			});
			usort($baseCards, function($a, $b){
				if ($a->s == $b->s)                       return 0;
				if ($a->s == "1")							return 1;
				if ($b->s == "1")							return -1;
				if ($a->s == "2")							return 1;
				if ($b->s == "2")							return -1;
				if ($a->s == "D")							return -1;
				if ($a->s == "S")							return 1;
				if ($b->s == "D")							return 1;
				if ($b->s == "S")							return -1;
				if ($a->s == "C" && $b->s == "H")         return -1;
				if ($a->s == "H" && $b->s == "C")         return 1;
			});
			usort($baseCards, function($a, $b) use ($trumpSuit) {
				if ($trumpSuit == "")							return 0;
				if ($trumpSuit == "N")							return 0;
				if ($a->s == $trumpRank)					return 1;
				if ($b->s == $trumpRank)					return -1;
			});
			usort($baseCards, function($a, $b) use ($trumpRank) {
				if ($trumpRank == 0)							return 0;
				if ($a->r == $trumpRank)					return 1;
				if ($b->r == $trumpRank)					return -1;
				
			});
			return $baseCards;
		}
		

		
		private function _getCardsTypeUnderSameColor($cards, $trumpRank, $trumpSuit)
		{
			$cardsLength = sizeof($cards);
			switch ($cardsLength) 
			{
				case 1:
					return 'A';
					break;
				case 2:
					if ($this->_isSameCard(array_slice($cards,0,2))){
						return 'AA';
					} else {
						return 'NA';
					}
					break;
				case 3:
					if ($this->_isSameCard(array_slice($cards,0,3))){
						return 'AAA';
					} else {
						return 'NA';
					}
					break;
				case 4:
					if ($this->_isSameCard(array_slice($cards,0,4))){
						return 'AAAA';
					} else if ($this->_isSameCard(array_slice($cards,0,2)) 
							&& $this->_isSameCard(array_slice($cards,2,2))
							&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[0], $cards[2])) {
						return 'AABB';
					} else {
						return 'NA';
					}
					break;
				case 5:
					if ($this->_isSameCard(array_slice($cards,0,5))){
						return 'AAAAA';
					} else {
						return 'NA';
					}
					break;
				case 6:
					if ($this->_isSameCard(array_slice($cards,0,2)) 
						&& $this->_isSameCard(array_slice($cards,2,2))
						&& $this->_isSameCard(array_slice($cards,4,2))
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[0], $cards[2])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[2], $cards[4])) {
						return 'AABBCC';
					} else if ($this->_isSameCard(array_slice($cards,0,3)) 
						&& $this->_isSameCard(array_slice($cards,3,3))
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[0], $cards[3])) {
						return 'AAABBB';
					} else {
						return 'NA';
					}
					break;
				case 8:
					if ($this->_isSameCard(array_slice($cards,0,2)) 
						&& $this->_isSameCard(array_slice($cards,2,2))
						&& $this->_isSameCard(array_slice($cards,4,2))
						&& $this->_isSameCard(array_slice($cards,6,2))
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[0], $cards[2])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[2], $cards[4])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[4], $cards[6])) {
						return 'AABBCCDD';
					} else if ($this->_isSameCard(array_slice($cards,0,4)) 
						&& $this->_isSameCard(array_slice($cards,4,4))
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[0], $cards[4])) {
						return 'AAAABBBB';
					} else {
						return 'NA';
					}
					break;
				case 9:
					if ($this->_isSameCard(array_slice($cards,0,3)) 
						&& $this->_isSameCard(array_slice($cards,3,3))
						&& $this->_isSameCard(array_slice($cards,6,3))
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[0], $cards[3])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[3], $cards[6])) {
						return 'AAABBBCCC';
					} else {
						return 'NA';
					}
					break;
				case 10:
					if ($this->_isSameCard(array_slice($cards,0,2)) 
						&& $this->_isSameCard(array_slice($cards,2,2))
						&& $this->_isSameCard(array_slice($cards,4,2))
						&& $this->_isSameCard(array_slice($cards,6,2))
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[0], $cards[2])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[2], $cards[4])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[4], $cards[6])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[6], $cards[8])) {
						return 'AABBCCDDEE';
					} else if ($this->_isSameCard(array_slice($cards,0,5)) 
							&& $this->_isSameCard(array_slice($cards,5,5))
							&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[0], $cards[5])) {
						return 'AAAAABBBBB';
					} else {
						return 'NA';
					}
					break;
				case 12:
					if ($this->_isSameCard(array_slice($cards,0,2)) 
						&& $this->_isSameCard(array_slice($cards,2,2))
						&& $this->_isSameCard(array_slice($cards,4,2))
						&& $this->_isSameCard(array_slice($cards,6,2))
						&& $this->_isSameCard(array_slice($cards,8,2))
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[0], $cards[2])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[2], $cards[4])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[4], $cards[6])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[6], $cards[8])
						&& $this->_isCardDiffOne($trumpRank, $trumpSuit, $cards[8], $cards[10])) {
						return 'AABBCCDDEEFF';
					} else {
						return 'NA';
					}
					break;
				default:
					return 'NA';
			}
		}
		
		private function _isSameCard($cards)
		{
			$baseCard = $cards[0];
			for ($i = 1; $i < sizeof($cards); $i++) {
				if ($baseCard != $cards[$i]) {
					return false;
				}
			}
			return true;
		}
		
		private function _isCardDiffOne($trumpRank, $trumpSuit, $card1, $card2)
		{

			return $this->_compareTwoCardUnderSameColor($card1, $card2, $trumpRank, $trumpSuit) == -1 ? true : false;

		}
		
		
		private function _compareTwoCardUnderSameColor($card1, $card2, $trumpRank, $trumpSuit)
		{
			if ($card1 == $card2) {
				return 0;
			}
			$trumpRound = ($card1->r == $trumpRank || $card1->r == 'N' || $card1->s == $trumpSuit) ? true : false;
			$cardOrder = array('2','3','4','5','6','7','8','9','10','J','Q','K','A');
			if (($key = array_search($trumpRank, $cardOrder)) !== false) {
				unset($cardOrder[$key]);
			}
			if ($trumpRound) {
				array_push($cardOrder,$trumpRank,'N');
			}
			$rank1 = array_search($card1->r, $cardOrder);
			$rank2 = array_search($card2->r, $cardOrder);
			if ($card1->r == 'N' && $card1->s == '1') {
				//大王rank再加一
				$rank1 += 1;
			}
			if ($card2->r == 'N' && $card2->s == '1') {
				//大王rank再加一
				$rank2 += 1;
			}
			return $rank1 - $rank2;
		}
		
		private function _isDealderWin($players, $master, $winner)
		{
			$playerArray = explode(",", $players);
			$masterIndex = array_search($master, $playerArray);
			$winnerIndex = array_search($winner, $playerArray);
			return (boolean)(abs($masterIndex - $winnerIndex - 1) % 2);
		}
	}
?>