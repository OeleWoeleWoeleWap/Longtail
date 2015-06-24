
Logger.log('Load:');
var urlsToFetch = []
	urlsToFetch.push(sUrlBase+"xml2obj.js");
	urlsToFetch.push(sUrlBase+"readItems.php?client=<?php echo $_GET['client'];?>&campaign=<?php echo $_GET['campaign'];?>");
	urlsToFetch.push(sUrlBase+"misc.js");


for(var i=0;i<urlsToFetch.length;++i)
	eval(UrlFetchApp.fetch(urlsToFetch[i]).getContentText());

function feedBasedCampaign() {
	this.run = function() {
		/*	
		main function that controls action

		future:
			- 
		returns:
			true when suceeded
		*/
		
		// init label and campaign
		Logger.log('Start:');
		this.init();
		
		//If campaign label has value Done the campaign has already run today.
		if(this.label.getDescription() == 'Done') return true;

		// read and filter items
		this.items = new Items();
		this.items.readFeed(this.sFeedURL,this.sPathToItem,this.label);

		Logger.log('   -> Process items');
		// for each item in this.items:
		while(this.items.hasNext()){
			// select next item
			this.items.nextItem();

			try{
				this.initAdGroup();
				this.initAdvertisement();
				this.createKeyWords();
				this.labelIncrement();
			}catch(err){
				// log the error and continue
				Logger.log('!! ' + err + ' (linenumber '+err.linenumber+')');
			}
			// check quotas.
			this.checkScriptQuotas(30,10,10);

		}
		this.finished();
		return true;
	}

	this.init = function(){
		Logger.log('   -> Initiate');
		this.date = new Date;

		// store all required variables

		if(typeof sFeedURL !== 'string') throw "sFeedURL not a string";
		this.sFeedURL        = sFeedURL;

		if(typeof sItemSpecifier !== 'string') throw "sItemSpecifier not a string";
		this.sPathToItem  	 = sItemSpecifier;

		if(typeof sCampaignName !== 'string') throw "sCampaignName not a string";
		this.sCmpgnName  	 = sCampaignName;

		if(typeof fMaxCpc !== 'number') throw "fMaxCpc nan";
		this.fMaxCPC         = fMaxCpc;

		if(typeof oAdvKey !== 'object') throw "oAdvKey not an object";
		this.oAdvKey 		 = oAdvKey;

		if(typeof lKeyWords == 'list') throw "lKeyWords not a list";
		this.lKeyKeys		 = lKeyWords;

		// set and/or store optional parameters.
		this.oSynonyms 		= typeof oSynonyms == 'undefined' 		? {} : oSynonyms;
		this.lBidRules 		= typeof lBidRules == 'undefined' 		? [] : lBidRules;
		this.iRefreshHour 	= typeof iRefreshHour == 'undefined' 	? 2 : iRefreshHour;
		this.forceAd		= typeof forceAd == 'undefined' 		? false : forceAd;
		if(typeof fCampaignBudget === 'number')	this.fCmpgnBudget = fCampaignBudget;

		Logger.log('      -> Variables initiated');

		// initialize campaign and label.
		this.initCampaign();
		this.initLabel();

		// if it is refreshtime, set this.label to 0 and pause all adgroups. This to
		// circumvent the 30 minute runtime limit. Feeds that require more than 30 mins
		// can run hourly, and only refresh at this.iRefreshour
		if(this.date.getUTCHours() + 1 == this.iRefreshHour || this.iRefreshHour == 'now'){
			Logger.log('   -> Refresh');
			this.label.setDescription("0");
			Logger.log('      -> Starting pausing adgroups ('+time()+')');
			this.pauseAllAdGroups();
			Logger.log('      -> All adgroups paused ('+time()+')');
			// check quotas to see if pauseAllAdgroups took to much time.
			this.checkScriptQuotas(1500,1,1);
		}		
		return true;
	}

	this.initCampaign = function(){
		/*  
		Tries to find the campaign given by the parameter campaignName.
 		If the campaign is not found, throws an error.
 		Sets campagin budget to this.fCmpgnBudget
 		Stores campaign in this.campaign
	 	*/
		var campaignIterator = AdWordsApp.campaigns()
	       .withCondition('Name = "'+this.sCmpgnName+'"')
	       .get();
	    // check if campaign exists. Campaign creation is not possible from
	    // scripts atm 
	    if (!campaignIterator.hasNext())
		    throw "Campain does not exist";       
		
		var campaign = campaignIterator.next();
		if( this.fCmpgnBudget )
			campaign.getBudget().setAmount( this.fCmpgnBudget );

		this.campaign = campaign;
		Logger.log('      -> Campaign initiated');

		return true;
	}
	
	this.initLabel = function(){
		/*
		Reads or creates a label on this.campaign
		If the hour is this.iRefreshHour sets the value of label to 0
		Stores the label in this.label
		*/
		var label = this.campaign.labels().withCondition("Name = 'State "+
			this.campaign.getName()+"'").get();
		
		if(!label.hasNext()){
			// label does not exist: create and apply
			AdWordsApp.createLabel("State " + this.campaign.getName());
			this.campaign.applyLabel("State " + this.campaign.getName());

			// store the new label in this.label
			var label = this.campaign.labels().withCondition("Name = 'State "+this.campaign.getName()+"'").get();
			this.label = label.next();
			this.label.setDescription("0");
		}else{
			// label exists, store in this.label
			this.label = label.next();
			
		}
		Logger.log('      -> Label initiated');

		return true;
	}

	this.labelIncrement = function(){
		/*
		Increments this.label by 1
		*/
		var newLabel = parseInt(this.label.getDescription())+1;
		this.label.setDescription(newLabel.toString());
		return true;
	}
	

	this.pauseAllAdGroups = function(){
		/*		
		Pauses all AdGroups in this.campaign
		*/
	 	var adGroupIterator = this.campaign.adGroups().get();
	 	// for each adgroup in the this.campaign:
	 	while(adGroupIterator.hasNext()){
	 		adGroup = adGroupIterator.next();
	 		// pause enabled adgroups.
	 		if(!adGroup.isEnabled()){continue;}
	 		adGroup.pause();
	 	}
	 	return true;
	}

	
	this.checkScriptQuotas = function(iTimeQuota,iCreateQuota,iGetQuota){
		/*
		Checks the status of the script with respect to the parameter quotas

		Parameters:
			@iTimeQuota		: How much time should be remaining to return true;
			@iCreateQuota 	: How much create should be remaining to return true;
			@iGetQuota		: How much get should be remaining to return true;

		Returns:
			true: 	if all quota's are met
			false:  else;
		*/
		var intRemTime = AdWordsApp.getExecutionInfo().getRemainingTime();
		var intRemCreate = AdWordsApp.getExecutionInfo()
			.getRemainingCreateQuota();
		var intRemGet = AdWordsApp.getExecutionInfo().getRemainingGetQuota();

		if(intRemTime < iTimeQuota || intRemCreate < iCreateQuota || 
				intRemGet < iGetQuota)
			throwError('Quota Reached, see you next hour');
		return true;
	}

	this.initAdGroup = function(){
		// create adgroup name
		var name = "";
		for(var i=0; i<lUniqueBy.length; ++i)
			name += this.items.parseKeySimple( lUniqueBy[i] ) + ' ';
		var adGroupSelector = this.campaign
		    .adGroups()
		    .withCondition( "Name = '" + name.trim() + "'" );

		Logger.log('      -> Adgroup "' + name + '"');

		var adGroupIterator = adGroupSelector.get();
		if( adGroupIterator.hasNext() ){
			this.oAdGrp = adGroupIterator.next();
			this.oAdGrp.enable();
			this.pauseKeywords();
		}
		else
			this.createAdGroup(name.trim());
		
		return true;
	}

	this.pauseKeywords = function(){
		// pauses all keywords in this.oAdGrp
		var keywords = this.oAdGrp.keywords().get();
		while(keywords.hasNext())
			keywords.next().pause();
	}

	this.createAdGroup = function(name){
		/* 
		Creates an adGroup based on information given by parameters
		*/
		Logger.log('         -> Creating adgroup "' + name + '"');

		var adGroup = this.campaign.newAdGroupBuilder()
			.withName(name)
			.withCpc(this.fMaxCPC)
			.create();
		this.oAdGrp = adGroup;
		this.createAdvertisement();
		return true;
	}

	this.initAdvertisement = function(){
		Logger.log('         -> Initiating advertisement' );

		var ad = this.oAdGrp.ads().withCondition("Status = ENABLED" ).get();
		if(!ad.hasNext()){
			this.createAdvertisement();
		}
		else{
			ad = ad.next();
			Logger.log(this.forceAd)
			if( this.forceAd &&
				(ad.getHeadline() != strFit(this.items.parseKey(this.oAdvKey['adTitle']),25,' ').trim() ||
				ad.getDescription1() != strFit(this.items.parseKey(this.oAdvKey['adDesc1']),35,' ').trim() ||
				ad.getDescription2() != strFit(this.items.parseKey(this.oAdvKey['adDesc2']),35,' ').trim() ||
				ad.getDestinationUrl() != this.items.parseKey(this.oAdvKey['adLoc']).trim() ||
				ad.getDisplayUrl() != strFit(this.items.parseKey(this.oAdvKey['displayURL']),35,'/').trim().replace(/ /g,'').toLowerCase())) {

				ad.remove();
				this.createAdvertisement();
			}
		}
	}

	this.createAdvertisement = function(){
		/*
		Creates an advertisment coupled to this.adGrp defined by 
		this.oAdvKey and this.item
		*/
		Logger.log('         -> Creating advertisment');

     	var oAd = {};
		for (var key in this.oAdvKey)
			oAd[key] = this.items.parseKey(this.oAdvKey[key]).removeTxt('�');	

		var lims = {'adTitle':25,'adDesc1':35,'adDesc2':35};
		for( var key in lims ){
			if( lims.hasOwnProperty( key ) )
				oAd[key] = strFit( oAd[key],lims[key], ' ' );
		}

		oAd['displayURL'] = strFit(oAd['displayURL'],35,'/');
		this.oAdGrp.createTextAd(oAd['adTitle'],
			oAd['adDesc1'],oAd['adDesc2'],
			oAd['displayURL'].replace(/ /g,'').toLowerCase(),
			oAd['adLoc']);

		return true;
	}

	this.createKeyWords = function(){
		/*
		Creates keywords coupled to this.adGrp defined by 
		this.keyKeys and this.item
		*/
		Logger.log('         -> Creating keywords');

		for( var i=0; i < this.lKeyKeys.length; ++i ){
			var sPhrase = this.items.parseKey( this.lKeyKeys[i].key );
			
			if (sPhrase === false)
				continue;
			
			//create synonyms
			keywds = this.createSynonyms( sPhrase );

			var sType 	= this.lKeyKeys[i].matchType.toLowerCase();
			if(typeof this.lKeyKeys[i]['cpc'] == 'undefined')
				var fMaxCPC = this.fMaxCPC;
			else
				var fMaxCPC = this.lKeyKeys[i]['cpc'];		

			for(var j=0;j<keywds.length;++j){
				keywds[j] = keywds[j].removeTxt('[^\\w\\s]');
				if(sType == "phrase")
					keywds[j] = "\"" + keywds[j] + "\"";
				else if(sType == "exact")
					keywds[j] = "[" + keywds[j] + "]";
				else if(sType == "broad"){}					
				else if(sType == "modified")
					keywds[j] = '+' + keywds[j].split(" ").join(" +");
				else{
					Logger.log('!! Matchtype ' + sType + ' not recognized. ' + 
						'Should be phrase, exact, broad or modified'); 
					continue;
				}
				
				if(keywds[j].length > 80){
					Logger.log('!! Keyword "' + keywds[j] + '" exeeds 80 char limit');
					continue;
				}
				if( keywds[j].split(' ').length > 9 ){
					Logger.log('!! Keyword "' + keywds[j] + '" exeeds 10 word limit');
					continue;
				}
		
				keyw = this.oAdGrp.newKeywordBuilder()
						.withText(keywds[j])
						.withCpc(fMaxCPC)
						.withDestinationUrl(this.items.parseKey(this.oAdvKey['adLoc']))
						.build()
						.getResult();
				//this.bidRules(keyw);
			}
		}
		return true;
	}

	// this.createKeyWords = function(){
	// 	var activeKeywords = this.getActiveKeywords();
	// 	var wantedKeywds  = this.getWantedKeywords();

	// 	for(var i=0;i<activeKeywords.length;i++){
	// 		if(wantedKeywds.indexOf(activeKeywords[i]) > -1){
	// 			wantedKeywds = removeA(wantedKeywds,activeKeywords[i]);
	// 			activeKeywords.splice(i, 1);
	// 		}
	// 	}

	//  for(var i=0;i<activeKeywords;i++){

	//	}

	//  for(var i=0;i<wantedKeywords;i++){
			
	//	}
	// 	Logger.log(activeKeywords);
	// 	Logger.log(wantedKeywds);

		


	// }

	this.getActiveKeywords = function(){
		var activeKeywords = [];
		var keywds = this.oAdGrp.keywords()
						.withCondition("Status = ENABLED")
						.withCondition("LabelNames CONTAINS_ALL ['Longtail keyword']")
						.get();

		while(keywds.hasNext())
			activeKeywords.push(keywds.next().getText());
		return activeKeywords;
	}

	this.getWantedKeywords = function(){
		var wantedKeywds = [];
		for(var i=0;i<this.lKeyKeys.length;i++){
			sPhrase = this.items.parseKey( this.lKeyKeys[i].key ); 
			wantedKeywds = mergeArrays(wantedKeywds,this.createSynonyms(sPhrase));
		}
		for(var i=0;i<wantedKeywds.length;i++)
			wantedKeywds[i] = this.parseMatchType(wantedKeywds[i],this.lKeyKeys[i].matchtype);
		return wantedKeywds;		
	}

	this.parseMatchType = function(text,matchtype){
		if(matchtype == "phrase")
			text = "\"" + text + "\"";
		else if(matchtype == "exact")
			text = "[" + text + "]";
		else if(matchtype == "broad"){}					
		else if(matchtype == "modified")
			text = text.split(" ").join(" +");
		else{
			Logger.log('!! Matchtype ' + matchtype + ' not recognized. ' + 
				'Should be phrase, exact, broad or modified'); 
			text = false;
		}
		return text;
	}

	this.createSynonyms = function(keywd){
		var keywords = [keywd];
		for(var key in this.oSynonyms){
			if(this.oSynonyms.hasOwnProperty(key) && keywd.indexOf(key) != -1){
				// allow for both strings and arrays
				if(typeof this.oSynonyms[key] == 'string'){
					var re = new RegExp(key,"g");
					keywords.push(keywd.replace(re,this.oSynonyms[key]));
				}
				else if(Array.isArray(this.oSynonyms[key])){
					for(var j=0;j<this.oSynonyms[key].length;++j){
						var re = new RegExp(key,"g");
						keywords.push(keywd.replace(re,this.oSynonyms[key][j]));
					}
				}
			}
		}
		return keywords;
	}

	this.createSynonyms2 = function(keywd){
		var keywords = {keywd:true};
		for(var key in this.oSynonyms){
			if(this.oSynonyms.hasOwnProperty(key) && keywd.indexOf(key) != -1){
				// allow for both strings and arrays
				if(typeof this.oSynonyms[key] == 'string'){
					var re = new RegExp(key,"g");
					var synonym = keywd.replace(re,this.oSynonyms[key])
					keywords[synonym] = true;
				}
				else if(Array.isArray(this.oSynonyms[key])){
					for(var j=0;j<this.oSynonyms[key].length;++j){
						var re = new RegExp(key,"g");
						synonym = keywd.replace(re,this.oSynonyms[key][j])
						keywords[synonym] = true;
					}
				}
			}
		}
		return keywords;
	}


	this.finished = function(){
		/* 
		function that executes when script is finished.
        Sets label description to Done.
        Logs Finished status.
        Deletes the stored version of the feed.
	    */
		this.label.setDescription("Done");
		Logger.log('Finished');
		//UrlFetchApp.fetch(sUrlBase + "finished.php?client="+<?php echo "'".$_GET['client']."'";?>+"&campaign="+this.sCmpgnName);
	}


/*	this.bidRules = function(keyw){
		for(var i=0;i<this.lBidRules.length;++i)
			this.bidRuleSingle(this.lBidRules[i],keyw);
	}

	this.bidRuleSingle = function(sRule,keyw){
		var bidWhen = sRule['when'];
		var bidDo 	= sRule['do'];

		if(this.bidWhen(bidWhen.split(';'),keyw))
			this.bidDo(bidDo.split(';'),keyw);
	}

	this.bidWhen = function(lBidWhen,keyw){
		return true;
	}

	this.bidDo = function(lBidDo,keyw){
		for(var i=0;i<lBidDo.length;++i){
			var rule = lBidDo[i];
			var commands = rule.split(' ');
			if(commands[0] == 'PAUSE'){
				if(commands[1] == 'keyword')
					keyw.pause();
				if(commands[1] == 'adgroup')
					keyw.getAdGroup().pause();
			}
		}
	}*/
}

Logger.log('   -> main.php loaded');


var FBC = new feedBasedCampaign();
FBC.run();