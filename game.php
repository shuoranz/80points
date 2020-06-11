<?php
// Initialize the session
session_start();
 
// Check if the user is logged in, if not then redirect him to login page
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<meta name="description" content="80 points">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="Shuoran">
	<title>丐版80分</title>
	<script>
	  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
	  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
	  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
	  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

	  ga('create', 'UA-41474117-3', 'auto');
	  ga('set', 'dimension1', '<?php echo $_SESSION["id"]; ?>');
	  ga('send', 'pageview');
	</script>
    <link rel="stylesheet" type="text/css" media="all" href="playingCards.ui.css"/>
	<link rel="stylesheet" type="text/css" media="all" href="playing80points.css"/>
</head>

<body>
  <div id="tableDesk">
  <div id="container">
  
    <div id="error"></div>
	<input type="hidden" id="trumpRank" value="0" />
	<input type="hidden" id="gameID" value="<?php echo $_GET['gid']; ?>" />
    <div class="info" style="display:none;">
		<p>账号: <?php echo $_SESSION["username"]; ?>, ID: <span id="pid"><?php echo $_SESSION["id"]; ?></span></p>
	</div>
	<h2>桌面牌, <span id="showPoint"></span><span id="showTrumpRank"></span></h2>
	<input type="hidden" id="shuffler" value="打散" />
	<!--
	<input type="button" id="orderByRank" value="按大小排序" />
    <input type="button" id="orderBySuit" value="按花色排序" />
	<input type="button" id="orderByTrump" value="按主牌排序" />
	<input type="hidden" id="draw" value="draw a card" />
    <input type="hidden" id="shuffleDraw" value="shuffle, then draw" />
    <input type="hidden" id="shuffleSpreadSlow" value="shuffle, then layout slowly" />
	-->
	
    <div id="theTable"></div>
	<br/><br/><br/><br/>
    
	
	<h2 id="handHeader"><?php echo $_SESSION["username"] . " "; ?>手中牌</h2>
	<div id="functionality" style="display:none;">
		<div id="cardOperation" style="display:inline-block;">
			<input type="button" id="grabCard" value="抓底牌" style="display:none;"/>
			<input type="button" id="hideCard" value="扣牌" style="display:none;"/>
			<input type="button" id="sendCard" value="出牌" />
			<input type="button" id="regretCard" value="悔牌" />
			<input type="hidden" id="addCard" value="把没出的牌放回手中" />
			
			<input type="button" id="orderAllInOne" value="码牌" />
			<input type="button" id="checkTable" value="刷新桌面" />
		</div>
		<div id="setTrumpDiv" style="display:inline-block;">
			<span>叫主花色:</span>
			<select name="setTrumpAmount" id="setTrumpAmount"></select>
			<input type="button" id="setTrumpDiamond" value="方片" />
			<input type="button" id="setTrumpClub" value="草花" />
			<input type="button" id="setTrumpHeart" value="红桃" />
			<input type="button" id="setTrumpSpade" value="黑桃" />
			<input type="button" id="setTrumpJoker" value="无主" />
			<input type="button" id="giveUpTrump" value="弃权" />
		</div>
	</div>
	
	
	<div id="yourHand"></div>
    <div id="cardDeck"></div>
	
	<br/><br/>
	
	
    
    
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
    <script type="text/javascript" src="playingCards.js"></script>
    <script type="text/javascript" src="playingCards.ui.js"></script>
    <script type="text/javascript" src="playing80points.js"></script>
	
  </div>
  </div>
</body>
</html>
