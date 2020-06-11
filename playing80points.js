$(document).ready(function(){
	var playerAmount;
	var playerID = $('#pid').html();
	var gameID = $('#gameID').val();
	var hideCardAmount;
	var tablePosition;
	var trumpRank, trumpSuit;
	var startTime = new Date();
	var gameStartTimeStamp;
	var setGameStartTime = function() {
		getGameStartTime().done(function(data){
			gameStartTimeStamp = data;
		});
		function getGameStartTime(){
			return $.ajax({
				type: 'POST',
				url: "api.php?gid="+gameID+"&a=gst&id=1",
				async:false
			});
		}
	}
	setGameStartTime();
	
	var cardDeck = $("#cardDeck").playingCards();
	updateCurrentTable();
	
	var cronJob = setInterval(cronJobUpdateTable, 3000);
	
	var hideCards;
	function SendOrHideCard()
	{
		var cardCount = cardDeck.count();
		if (cardCount > 26) {
			var hideCardAmountWord = "";
			hideCardAmount = 0;
			if (playerAmount == 4) {
				hideCardAmountWord = "8张";
				hideCardAmount = 8;
			} else if (playerAmount == 6) {
				hideCardAmountWord = "6张";
				hideCardAmount = 6;
			} else if (playerAmount == 8) {
				hideCardAmountWord = "8张";
				hideCardAmount = 8;
			} else if (playerAmount == 10) {
				hideCardAmountWord = "10张";
				hideCardAmount = 10;
			}
			hideCards = cardDeck.cards.slice(cardCount - hideCardAmount);
			cardDeck.cards = cardDeck.cards.slice(0, cardCount - hideCardAmount);
			$('#hideCard').val("扣" + hideCardAmountWord +"牌");
			//$('#hideCard').show();
			$('#grabCard').show();
			$('#sendCard').hide();
		}
	}
	SendOrHideCard();
	
	var sendTrumpSuit = function(suit){
		// validation cards have trump suit and number
		var trumpAmount = $("#setTrumpAmount").val();
		theCards = cardDeck.cards;

		if (suit != 'N') {
			var inCardThisTrumpSuitAmount = 0;
			for (var i = 0; i < theCards.length; i++) {
				if (theCards[i]["suit"] == suit && theCards[i]["rank"] == trumpRank) {
					inCardThisTrumpSuitAmount++;
				}
				/*
				if (i == theCards.length - 1) {
					alert("你貌似没有此花色主牌");
					return false;
				}
				*/
			}
			if (inCardThisTrumpSuitAmount < trumpAmount) {
				alert("你此花色主牌不够");
				return false;
			}
		} else {
			var jokerBig = jokerSmall = 0;
			for (var i = 0; i < theCards.length; i++) {
				if (suit == theCards[i]["rank"] && theCards[i]["suit"] == "1"){
					jokerBig++;
				} else if (suit == theCards[i]["rank"] && theCards[i]["suit"] == "2"){
					jokerSmall++;
				}
			}
			if (Math.max(jokerBig, jokerSmall) < trumpAmount || Math.max(jokerBig, jokerSmall) <= 1) {
				alert("你王不够多");
				return false;
			}
			if (trumpAmount <= 1) {
				alert("最少两个同色王能反主");
				return false;
			}
		}
		
		
		//send to server
		sendSuitToServer(suit, trumpAmount).done(function(data){
			if (data == "success") {
				//putCardOntoTable(tablePosition, hand);
				var alertSuit = "";
				if (suit == "C") {
					alertSuit = "草花，";
				} else if (suit == "D") {
					alertSuit = "方片，";
				} else if (suit == "H") {
					alertSuit = "红桃，";
				} else if (suit == "S") {
					alertSuit = "黑桃，";
				} else {
					alertSuit = "无主! ";
				}
				alert(alertSuit + "哦了~");
			} else {
				alert("花色已经定了");
			}
		});
		function sendSuitToServer(suit, trumpAmount){
			return $.ajax({
				type: 'POST',
				url: "api.php?gid="+gameID+"&a=sts&ts="+suit+"&ta="+trumpAmount,
				async:false
			});
		}
	}
	$('#setTrumpClub').click(function(){sendTrumpSuit('C');});
	$('#setTrumpDiamond').click(function(){sendTrumpSuit('D');});
	$('#setTrumpHeart').click(function(){sendTrumpSuit('H');});
	$('#setTrumpSpade').click(function(){sendTrumpSuit('S');});
	$('#setTrumpJoker').click(function(){sendTrumpSuit('N');});
	$('#giveUpTrump').click(function(){
		$("#setTrumpDiv").hide();
		//tell server this player already gave up trump setting
	});
	
	cardDeck.spread(null, true); // show it
	
	var hand = [];
	var showError = function(msg){
		$('#error').html(msg).show();
		setTimeout(function(){
			$('#error').fadeOut('slow');
		},8000);
	}
	var showHand = function(){
		var el = $('#yourHand')
		el.html('');
		for(var i=0;i<hand.length;i++){
			el.append(hand[i].getHTML());
		}
	}
	var doShuffle = function(){
		cardDeck.shuffle();
		cardDeck.spread(); // update card table
	}
	var doDrawCard = function(){
		var c = cardDeck.draw();
		if(!c){
			alert('no more cards');
			return;
		}
		hand[hand.length] = c;
		cardDeck.spread();
		showHand();
	}
	var doOrderByRank = function(){
		cardDeck.orderByRank();
		cardDeck.spread(); // update card table
	}
	var doOrderBySuit = function(){
		cardDeck.orderBySuit();
		cardDeck.spread(); // update card table
	}
	var doOrderByTrump = function(){
		cardDeck.orderByTrump();
		cardDeck.spread(); // update card table
	}
	var doOrderAllInOne = function(){
		cardDeck.orderByRank();
		cardDeck.orderBySuit();
		cardDeck.orderByTrump();
		cardDeck.spread();
	}
	$('#shuffler').click(doShuffle);
	$('#draw').click(doDrawCard);
	$('#shuffleDraw').click(function(){
		doShuffle();
		doDrawCard();
	});
	$('#shuffleSpreadSlow').click(function(){
		doShuffle();
		cardDeck.spread(null, true); // update card table
	});
	$('#addCard').click(function(){
		if(!hand.length){
			alert('都没牌了你退啥');
			return;
		}
		var c = hand.pop();
		showHand();
		cardDeck.addCard(c);
		cardDeck.spread();
	});
	$('#orderByRank').click(doOrderByRank);
	$('#orderBySuit').click(doOrderBySuit);
	$('#orderByTrump').click(doOrderByTrump);
	$('#orderAllInOne').click(doOrderAllInOne);
	
	$(document).on("click", "#cardDeck .playingCard" , function() {
		var c = cardDeck.drawByIndex($(this).index());
		if(!c){
			alert('恭喜你出完啦');
			return;
		}
		hand[hand.length] = c;
		cardDeck.spread();
		showHand();
	});
	
	$(document).on("click", "#yourHand .playingCard" , function() {
		if (hand.length <= 0) {
			return false;
		}
		var removed = hand.splice($(this).index(),1);
		var c = removed[0];
		showHand();
		cardDeck.addCard(c);
		doOrderAllInOne();
		cardDeck.spread();
	});
	
	var hide = [];
	var hideCardAction = function (){
		if (hand.length != hideCardAmount){
			alert ("扣的不对，应该扣" + hideCardAmount + "张");
			return false;
		}
		hide = hand;
		hand = [];
		showHand();
		$('#hideCard').hide();
		$('#sendCard').show();
		//tell server the master which cards were hidden, and calculate points, save to server
	}
	var grabCardAction = function (){
		cardDeck.cards = cardDeck.cards.concat(hideCards);
		cardDeck.spread();
		$('#grabCard').hide();
		$('#hideCard').show();
	}
	var sendCard = function (){
		if (hand.length == 0){
			alert("请先选牌");
			return false;
		}
		var cardsParam = [];
		for(var i = 0; i < hand.length; i++){
			//el.append(hand[i].getHTML());
			console.log(hand[i].rank + hand[i].suit);
			cardsParam[i] = {"r" : hand[i].rank, 's' : hand[i].suit};
		}
		console.log(JSON.stringify(cardsParam));
		sendCardToServer(playerID,JSON.stringify(cardsParam)).done(function(data){
			if (data == "success") {
				putCardOntoTable(tablePosition, hand);
				hand = [];
				showHand();
			} else {
				alert("你本轮已出过牌了");
			}
		});
		function sendCardToServer(pid, card){
			return $.ajax({
				type: 'POST',
				url: "api.php?gid="+gameID+"&a=pdc&p="+pid+"&c="+card,
				async:false
			});
		}
	}
	var regretCard = function() {
		var regretCardsArray;
		regretPostedCard(playerID).done(function(data){
			if (data == "empty" || data == "error") {
				alert("无牌可悔");
			} else {
				regretCardsArray = JSON.parse(data);
				for (var i = 0; i < regretCardsArray.length; i++) {
					var r = regretCardsArray[i]["r"];
					var s = regretCardsArray[i]["s"];
					var c = cardDeck.createCardByRankSuit(r, s);
					cardDeck.addCard(c);
					doOrderAllInOne();
					cardDeck.spread();
				}
				$('#tablePlayer_'+tablePosition).html('');
			}
		});
		
		function regretPostedCard(pid){
			return $.ajax({
				type: 'POST',
				url: "api.php?gid="+gameID+"&a=rpc&p="+pid,
				async:false
			});
		}
	}
	var checkTable = function  (){
		updateCurrentTable();
	}
	$('#hideCard').click(hideCardAction);
	$('#grabCard').click(grabCardAction);
	$('#sendCard').click(sendCard);
	$('#regretCard').click(regretCard);
	$('#checkTable').click(checkTable);
	$(document).keydown(function(e) {
		var currentPos = parseInt($("#cardDeck").css('padding-left'));
		if(currentPos < 70 && e.keyCode == 37){
			cardMarginAdjustment("left", currentPos);
		} else if (currentPos > 0 && e.keyCode == 39){
			cardMarginAdjustment("right", currentPos);
		}
	});
	
	function cardMarginAdjustment(direction, currentPos){
		
		if (direction == "left"){
			$("#cardDeck").css('padding-left', (currentPos + 1)+'px');
			$("#yourHand").css('padding-left', (currentPos - 4)+'px');
			$("#cardDeck .playingCard").css('margin-left',(4 - currentPos)+'px');
			$("#yourHand .playingCard").css('margin-left',(4 - currentPos)+'px');
		}
		if (direction == "right"){
			$("#cardDeck").css('padding-left', (currentPos - 1)+'px');
			$("#yourHand").css('padding-left', (currentPos - 6)+'px');
			$("#cardDeck .playingCard").css('margin-left',(6 - currentPos)+'px');
			$("#yourHand .playingCard").css('margin-left',(6 - currentPos)+'px');
		}
		
	}
	
	function cronJobUpdateTable() {
		updateCurrentTable();
		if (new Date().getTime() - startTime.getTime() > 1000 * 60* 60 * 3) {
			clearTimeout(cronJob);
		}
	}
	
	function updateCurrentTable() {
		$('#theTable').html('');
		var currentTableJson, points, playerNamesArray, cronJobTimeStamp;
		getCurrentTable().done(function(data){
			currentTableJson = data;
		});
		currentTableArray = JSON.parse(currentTableJson);
		points = currentTableArray['pt'];
		trumpRank = currentTableArray['tr'];
		trumpSuit = currentTableArray['ts'];
		cronJobTimeStamp = currentTableArray['tm'];
		if (cronJobTimeStamp != gameStartTimeStamp) {
			/*
			cardDeck.init();
			cardDeck.spread(null, true);
			$("#orderAllInOne").show();
			SendOrHideCard();
			gameStartTimeStamp = cronJobTimeStamp;
			*/
			location.reload();
		}
		$('#showPoint').html("[现在分数：" + points + " ] ");

		if (trumpSuit == "") {
			trumpSuitWord = "未定";
		} else if (trumpSuit == "S") {
			trumpSuitWord = "黑桃";
		} else if (trumpSuit == "C") {
			trumpSuitWord = "草花";
		} else if (trumpSuit == "D") {
			trumpSuitWord = "方片";
		} else if (trumpSuit == "H") {
			trumpSuitWord = "红桃";
		} else {
			trumpSuitWord = "无主";
		}
		$('#showTrumpRank').html("[本局主牌：" + trumpRank + ", 花色" + trumpSuitWord + "]");
		$('#trumpRank').val(trumpRank);
		
		
		
		playersCurrentCards = currentTableArray['pl'];
		playerAmount = playersCurrentCards.length;
		var tablePlayerCss = "width: 16%;margin: 13px;padding-left:65px;";
		if (playerAmount == 6) {
			tablePlayerCss = "width: 24%;margin: 13px;padding-left:65px;";
		} else if (playerAmount == 10) {
			tablePlayerCss = "width: 12%;margin: 7px;padding-left:65px;";
		}
		for (var i = 0; i < playerAmount; i++) {
			$('#theTable').append('<div class="tablePlayer" style="'+tablePlayerCss+'" id="tablePlayer_' + i + '"></div>');
		}
		if ($("#setTrumpAmount").html() == ""){
			for (var i = 0; i < playerAmount; i++) {
				$("#setTrumpAmount").append( '<option value="' + (i+1) + '">' + (i+1) + '</option>' );
			}
		}
		
		playersCurrentCards.forEach(updateCurrentTableUI);
		
		
		playerNamesArray = currentTableArray['nm'];
		playerNamesArray.forEach(updateTableCSS);
		
		function getCurrentTable(){
			return $.ajax({
				type: 'POST',
				url: "api.php?gid="+gameID+"&a=gcr",
				async:false
			});
		}
		function updateCurrentTableUI(playerCards, index) {
			var tableCards;
			Object.keys(playerCards).forEach(function(key) {
				tableCards = [];
				if (key == playerID) {
					tablePosition = index;
				}
				if (playerCards[key]) {
					var tabCardArray = JSON.parse(playerCards[key]);
					for (var i = 0; i < tabCardArray.length; i++) {
						tableCards[tableCards.length] = cardDeck.createCardByRankSuit(tabCardArray[i]['r'],tabCardArray[i]['s']);
					}
					
				}
				putCardOntoTable(index, tableCards)
			});
		}
		function updateTableCSS(names, index) {
			Object.keys(names).forEach(function(key) {
				// console.log(key + names + index);
				var imageUrl = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' version='1.1' height='50px' width='240px'><text x='0' y='15' fill='silver' font-size='20' transform='translate(5,30) rotate(-10)'>player "+(index+1)+" => "+names[key]+"</text></svg>";
				$("#tablePlayer_"+index).css("background-image", "url(\"" + imageUrl + "\")");
			});
		}
	}
	
	function putCardOntoTable(position, tableCards) {
		for(var i = 0; i < tableCards.length; i++){
			var tableCardHTML = tableCards[i].getHTML();
			$('#tablePlayer_'+position).append(tableCardHTML);
		}
	}
	
	
});
        /*
        // if we weren't using jquery to handle the document ready state, we would do this:
        if (window.addEventListener) {
            window.addEventListener("load",initPlayingCards,false);
        } else if (window.attachEvent) {
            window.attachEvent("onload",initPlayingCards);
        } else {
            window.onload = function() {initPlayingCards();}
        }
        function initPlayingCards() {
            cardDeck = new playingCards();
        }
        */