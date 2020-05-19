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

    <title>丐版80分</title>

    <link rel="stylesheet" type="text/css" media="all" href="playingCards.ui.css"/>
    <style type="text/css">
        body {
          margin-top: 1.0em;
          background-color: #fff;
          font-family: "Helvetica,Arial,FreeSans";
          color: #000000;
        }
        #container {
          margin: 0 auto;
          width: 1060px;
        }
        h1 { font-size: 40px; color: #64052a; margin-bottom: 3px; }
        h1 .small { font-size: 0.4em; }
        h1 a { text-decoration: none }
        h2 { font-size: 1.5em; color: #64052a; }
        h3 { text-align: center; color: #64052a; }
        a { color: #64052a; }
        .description { font-size: 1.2em; margin-bottom: 30px; margin-top: 30px; font-style: italic;}
        .download { float: right; }
            pre { background: #000; color: #fff; padding: 15px;}
        hr { border: 0; width: 80%; border-bottom: 1px solid #aaa}
        .footer { text-align:center; padding-top:30px; font-style: italic; }
        h2{
            clear:both;
        }
        #error{
            display:none;color:#f00;border:1px solid #f60;padding:5px;margin:5px;
        }
		.tablePlayer{
			border:1px dashed #f60;
			height: 6.4em;
			width: 16%;
			float: left;
			position: relative;
			margin: 14px;
			/* padding-left: 65px; */
			padding-left: 0;
			/*
			background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' version='1.1' height='50px' width='170px'><text x='0' y='15' fill='gray' font-size='20'></text></svg>");
			*/
		}
		#yourHand{
			height: 6.4em;
			border:1px dashed #f60;
			margin: 5px;
			/* padding-left: 65px; */
			padding-left: 0;
		}
		#cardDeck{
			/* padding-left: 70px; */
			padding-left: 5px;
		}
		.playingCard{
			/* margin-left: -65px; */
			margin-left: 0;
		}
		
		#theTable .playingCard{
			margin-left: -65px;
		}
		
    </style>

</head>

<body>
  <div id="container">
  
    <div id="error"></div>
	<input type="hidden" id="trumpRank" value="0" />
    <input type="hidden" id="draw" value="draw a card" />
    <input type="hidden" id="shuffleDraw" value="shuffle, then draw" />
    <input type="hidden" id="shuffleSpreadSlow" value="shuffle, then layout slowly" />
    <div class="info">
		<p>账号: <?php echo $_SESSION["username"]; ?>, ID: <span id="pid"><?php echo $_SESSION["id"]; ?></span></p>
	</div>
	<h2>桌面牌, <span id="showPoint"></span><span id="showTrumpRank"></span></h2>
	<input type="hidden" id="shuffler" value="打散" />
	<!--
	<input type="button" id="orderByRank" value="按大小排序" />
    <input type="button" id="orderBySuit" value="按花色排序" />
	<input type="button" id="orderByTrump" value="按主牌排序" />
	-->
	
    <div id="theTable"></div>
	<br/><br/>
	
	
	
	<!--<h2>要出的牌</h2>-->
	
	<br/><br/>
    
	
	<h2>手中牌</h2>
	<input type="button" id="hideCard" value="扣牌" style="display:none;"/>
	<input type="button" id="sendCard" value="出牌" />
	<input type="button" id="regretCard" value="悔牌" />
	<input type="hidden" id="addCard" value="把没出的牌放回手中" />
	
	<input type="button" id="orderAllInOne" value="码牌" />
	<input type="button" id="checkTable" value="刷新桌面" />
	<span>叫主花色:</span>
	<input type="button" id="setTrumpDiamond" value="方片" />
	<input type="button" id="setTrumpClub" value="草花" />
	<input type="button" id="setTrumpHeart" value="红桃" />
	<input type="button" id="setTrumpSpade" value="黑桃" />
	<div id="yourHand"></div>
    <div id="cardDeck"></div>
	
	<br/><br/>
	
	
    
    
    <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
    <script type="text/javascript" src="playingCards.js"></script>
    <script type="text/javascript" src="playingCards.ui.js"></script>
    <script type="text/javascript" src="playing80points.js"></script>
	
  </div>

</body>
</html>
