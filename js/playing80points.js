$(document).ready(function(){
	var playerAmount;
	var playerID = $('#pid').html();
	var gameID = $('#gameID').val();
	var hideCardAmount;
	var tablePosition;
	var trumpRank, trumpSuit;
	var startTime = new Date();
	var gameStartTimeStamp;
	var playerNamesArray;
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
	
	function showAlertDialog(content)
	{
		$( "#alertDialog" ).html(content);
		$( "#alertDialog" ).dialog({
			height: 100,
			width: 250,
			modal: true,
			resizable: false,
			dialogClass: 'success-dialog',
			buttons: []
		});
	}
	
	function showAutoCalculateDialog()
	{
		$( "#alertDialog" ).html("本轮结束");
		$( "#alertDialog" ).dialog({
			dialogClass: "no-close success-dialog",
			height: 200,
			width: 350,
			modal: true,
			resizable: false,
			buttons: [{
				text: "点击算分",
				click: function() {
					$( this ).dialog( "close" );
					autoCalculatePointsAfterRounds().done(function(data){
						if (data == "pass") {
							
						} else {
							showManualCalculateDialog();
						}
					});
					function autoCalculatePointsAfterRounds(){
						return $.ajax({
							type: 'POST',
							url: "api.php?gid="+gameID+"&a=fcr",
							async:false
						});
					}
					
				}
			}]
		});
	}
	
	function showManualCalculateDialog()
	{
		var dialogHtml = "系统无法自动算分，请手动算分后提交结果<br>";
		dialogHtml += '本轮赢家： <select name="winPlayer" id="winPlayer">';
		dialogHtml += '<option value="null" selected disabled hidden>winner</option>';
		playerNamesArray.forEach(function(names, index) {
			Object.keys(names).forEach(function(key) {
				dialogHtml += '<option value="'+key+'">'+names[key]+'</option>';
			});
		});	
		dialogHtml += '</select>';
		dialogHtml += '<br>';
		dialogHtml += '闲家加分： <input type="number" id="winPoints" name="winPoints" min="0" max="500"/>';
		$( "#alertDialog" ).html(dialogHtml);
		$( "#alertDialog" ).dialog({
			dialogClass: "no-close success-dialog",
			height: 200,
			width: 350,
			modal: true,
			resizable: false,
			buttons: [{
				text: "点击提交",
				click: function() {
					var winPoints = $("#winPoints").val();
					var winID = $("#winPlayer").val();
					if (winPoints != "" && winID && parseInt(winPoints) >= 0 && parseInt(winPoints) <= 500 && parseInt(winPoints)%5 == 0)
					{
						manualCalculatePointsAfterRounds().done(function(data){
							if (data == "pass") {
								$( "#alertDialog" ).dialog( "close" );
							} else {
								$( "#alertDialog" ).dialog( "close" );
								alert("something wrong, plz call administrator");
							}
						});
						function manualCalculatePointsAfterRounds(){
							return $.ajax({
								type: 'POST',
								url: "api.php?gid="+gameID+"&a=fcr&p="+winPoints+"&w="+winID,
								async:false
							});
						}
					} else {
						alert("error! check your input");
					}

					
				}
			}]
		});
	}
	
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
			}
			if (inCardThisTrumpSuitAmount < trumpAmount) {
				showAlertDialog("你此花色主牌不够多");
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
				showAlertDialog("你王不够多");
				return false;
			}
			if (trumpAmount <= 1) {
				showAlertDialog("最少两个同色王能反主");
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
				showAlertDialog(alertSuit + "哦了~");
			} else {
				showAlertDialog("花色已经定了");
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
		
		//tell server this player already gave up trump setting
		//send to server
		tellServerAbandonTrump(playerID).done(function(data){
			if (data == "success") {
				$("#setTrumpDiv").hide();
			} else {
				showAlertDialog("系统错误，无法弃权。");
			}
		});
		function tellServerAbandonTrump(pid){
			return $.ajax({
				type: 'POST',
				url: "api.php?gid="+gameID+"&a=ats&p="+pid,
				async:false
			});
		}
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
			showAlertDialog('no more cards');
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
	var sortTheCards = function(theCards){
		theCards = cardDeck.orderCardsByRank(theCards);
		theCards = cardDeck.orderCardsBySuit(theCards);
		theCards = cardDeck.orderCardsByTrump(theCards);
		return theCards;
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
			showAlertDialog('都没牌了你退啥');
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
			showAlertDialog('恭喜你出完啦');
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
			showAlertDialog("扣的不对，应该扣" + hideCardAmount + "张");
			return false;
		}
		hide = hand;
		
		$('#hideCard').hide();
		$('#sendCard').show();
		
		var cardsParam = [];
		for(var i = 0; i < hand.length; i++){
			//el.append(hand[i].getHTML());
			//console.log(hand[i].rank + hand[i].suit);
			cardsParam[i] = {"r" : hand[i].rank, 's' : hand[i].suit};
		}
		
		//tell server the master which cards were hidden, and calculate points, save to server
		hideCardToServer(playerID,JSON.stringify(cardsParam)).done(function(data){
			if (data.includes("success:")) {
				var resultPointArray = data.split(":");
				$('#showHiddenPoint').html("[扣牌里有"+resultPointArray[1]+"分]");
				hand = [];
				showHand();
			} else {
				showAlertDialog("系统错误，无法扣牌");
			}
		});
		function hideCardToServer(pid, card){
			return $.ajax({
				type: 'POST',
				url: "api.php?gid="+gameID+"&a=mhc&p="+pid+"&c="+card,
				async:false
			});
		}
		
	}
	var grabCardAction = function (){
		askServerCanGrabCard(playerID).done(function(data){
			if (data == "success") {
				cardDeck.cards = cardDeck.cards.concat(hideCards);
				cardDeck.spread();
				$('#grabCard').hide();
				$('#hideCard').show();
			} else {
				showAlertDialog("别着急，先定主牌花色");
			}
		});
		function askServerCanGrabCard(pid){
			return $.ajax({
				type: 'POST',
				url: "api.php?gid="+gameID+"&a=cgc",
				async:false
			});
		}
	}
	var sendCard = function (){
		if (hand.length == 0){
			showAlertDialog("请先选牌");
			return false;
		}
		var cardsParam = [];
		hand = sortTheCards(hand);
		for(var i = 0; i < hand.length; i++){
			//el.append(hand[i].getHTML());
			//console.log(hand[i].rank + hand[i].suit);
			cardsParam[i] = {"r" : hand[i].rank, 's' : hand[i].suit};
		}
		//console.log(JSON.stringify(cardsParam));
		sendCardToServer(playerID,JSON.stringify(cardsParam)).done(function(data){
			if (data.includes("calculate")){
				putCardOntoTable(tablePosition, hand);
				hand = [];
				showHand();
				showAutoCalculateDialog();
			} else if (data.includes("success")) {
				putCardOntoTable(tablePosition, hand);
				hand = [];
				showHand();
			} else if (data.includes("notYourTurn")) {
				showAlertDialog("憋着急，还没到你呢");
			} else {
				showAlertDialog("你本轮已出过牌了");
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
				showAlertDialog("无牌可悔");
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
		var currentTableJson, points, cronJobTimeStamp, masterPlayerID, trumpSuitAmount;
		getCurrentTable().done(function(data){
			currentTableJson = data;
		});
		currentTableArray = JSON.parse(currentTableJson);
		points = currentTableArray['pt'];
		trumpRank = currentTableArray['tr'];
		trumpSuit = currentTableArray['ts'];
		trumpSuitAmount = currentTableArray['ta'];
		cronJobTimeStamp = currentTableArray['tm'];
		masterPlayerID = currentTableArray['ms'];
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
			trumpSuitWord = "黑桃x"+trumpSuitAmount;
		} else if (trumpSuit == "C") {
			trumpSuitWord = "草花x"+trumpSuitAmount;
		} else if (trumpSuit == "D") {
			trumpSuitWord = "方片x"+trumpSuitAmount;
		} else if (trumpSuit == "H") {
			trumpSuitWord = "红桃x"+trumpSuitAmount;
		} else {
			trumpSuitWord = "无主x"+trumpSuitAmount;
		}
		$('#showTrumpRank').html("[本局主牌：" + trumpRank + ", 主色" + trumpSuitWord + "]");
		$('#trumpRank').val(trumpRank);
		$('#trumpSuit').val(trumpSuit);
		
		
		
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
				var masterCss = masterPlayerID == key ? "yellow" : "silver";
				var masterLogo = masterPlayerID == key ? "庄" : "";
				// console.log(key + names + index);
				var imageUrl = "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' version='1.1' height='50px' width='240px'><text x='0' y='15' fill='"+masterCss+"' font-size='20' transform='translate(5,30) rotate(-10)'>player "+(index+1)+" "+masterLogo+" => "+names[key]+"</text></svg>";
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