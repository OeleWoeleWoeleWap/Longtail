
<?php 
	/*$client 	= $_GET['client'];
	$campaign 	= $_GET['campaign'];
	if(file_exists('feeds/'.$client.'/'.$campaign.'.txt')){
		echo "Logger.log('    Feed already read today');";
		$file = fopen("feeds/".$client.'/'.$campaign.'.txt', "r");
		$feed = fread($file,filesize("feeds/".$client.'/'.$campaign.'.txt'));
	}else{
		echo "Logger.log('    Feed not yet read today');";	
	}*/
?>

function Items(){
	Logger.log('   -> Read items ('+time()+')')
	this.readFeed = function(sFeedURL,sItemSpec,oLabel){
		/*
		Reads a xml Feed from sFeedURL into an object
		Makes a list based on sItemSpec; . split
		Filters based on listFilters
		Makes items unique based on lUniqueBy

		returns:
			list of items;
		*/
		<?php
			/*if(isset($feed)){
				echo "this.items = ".$feed;
			}
			else{
				echo "var feedStr = UrlFetchApp.fetch(sFeedURL).getContentText();";
	  			echo "this.items = xml2obj(feedStr);";

	  			echo "var payload = {sFeed:stringify(this.items)};";
	  			echo 'var options =  {"method" : "post","payload" : payload};';
	  			
	  			echo "Logger.log(UrlFetchApp.fetch(sUrlBase + 'storefeed.php?client=".$client."&campaign=".$campaign."',options).getContentText())";
	  		}*/
	  	?>

	  	// lees de feed in
	  	var feedStr = UrlFetchApp.fetch(sFeedURL).getContentText();
		this.items = xml2obj(feedStr);
	  	Logger.log('      -> Items read ('+time()+')')

		// converteer het feedobject in een lijst van producten
	  	if(typeof sItemSpec !== 'undefined'){
	  		var items = [];
	  		var specs = sItemSpec.split(';');
	  		// allow for multiple item paths
	  		for(var j=0;j<specs.length;++j){
		  		lItemSpec = specs[j].split('.');
		  		itemsTemp = this.items;
		  		for(var i=0;i<lItemSpec.length;i++)
		  			itemsTemp = itemsTemp[lItemSpec[i]];
		  		items = mergeArrays(items,itemsTemp);
		  	}
		  	
		  	this.items = items;
	  	}
	  	Logger.log('      -> ItemSpec done (items: '+ this.items.length +',time: '+time()+')');
	  	
	  	// filter producten
	  	if(typeof lFilters !== 'undefined')
	  		this.filterItems(lFilters);
		Logger.log('      -> Items filtered (items: '+ this.items.length +',time: '+time()+')');

	  	// slice of items already processed in previous itterations
	  	this.items = this.items.slice(parseInt(oLabel.getDescription()));


	  	return true;
	}

	this.hasNext = function(){
		return typeof this.items[0] != 'undefined';
	}
	
	this.nextItem = function(){
		this.activeItem = this.items.shift();
		return true;
	}

	this.filterItems = function(lFilters){
		/* 
		filters lItems based on lFilters

		Returns:
			@list 	listItems 	: a filterd list of items
		*/
		lItemsFiltered = [];

		// voor elk item
		while(this.hasNext()){
			this.nextItem();
			valid = true;
			//voor elk filter
			for(var j=0;j<lFilters.length;++j){
				var key = this.parseKeySimple(lFilters[j]['k']);
				var value = lFilters[j]['v'];
				var operator = lFilters[j]['o'];

				if(key === false){
					// veld bestaat niet in item
					valid = false;
					break;
				}

				// de filters. Als een item een filter niet matcht dan word de variabele check false.
				check = this.filter(operator,value,key);

				if(!check){
					// item is niet door de filters gekomen.
					valid = false;
					break;
				}
			}
			// voeg item toe aan gefilterde items.
			if(valid)
				lItemsFiltered.push(this.activeItem);
			
		}
		this.items = lItemsFiltered;
		return true;
	}

	this.filter = function(operator, value, key){
		if(operator == '=' || operator == '==')
			var check = value.toLowerCase() == key.toLowerCase();
		else if(operator == 'contains')
			var check = key.toLowerCase().indexOf(value.toLowerCase())  != -1;
		else if( operator == 'does_not_contain' )
			var check = key.toLowerCase().indexOf(value.toLowerCase()) == -1;
		else if(operator == '<')
			var check = makeFloat(key) < makeFloat(value);
		else if( operator == '>' )
			var check = makeFloat(key) > makeFloat(value);
		else if( operator == '>=')
			var check = makeFloat(key) >= makeFloat(value);
		else if( operator == '<=' )
			var check = makeFloat( key ) <= makeFloat( value );
		else{
			Logger.log('Filter '+stringify(lFilters[j]) + ' is invalid. Continuing');
			var check = false;
		}
		return check;
	}

	this.parseKeySimple = function( key ){
		/* 
		Parses a string to its corrosponding value in the item object.
		
		Parameters:
			@string 	key   	the key to parse. Multiple levels in the xml are seperated by dots

		Returns:
			@string 			the string in the item object corrosponding to the key.

		*/
		var txt 	= this.activeItem;
		var subKeys = key.split( '.' );
		// loop through the subkeys
		for( var j=0; j<subKeys.length; ++j ){
			// if undefined, return false. 
			if( typeof txt[subKeys[j]] === undefined){
				Logger.log('Error in Key: ' + subKeys[j] + ' is undefined.');
				return false;
			}
			// save the new level

			txt = txt[subKeys[j]];
		}
		// if the result is not a string, return false;
		if( typeof txt != 'string' ){
			//Logger.log('Error in Key "' + key + '": does not refer to a string, it refers to a ' + typeof txt);
			return false;
		}
		return txt;
	}

	this.parseKeyFunctions = function(key){
		/* 
		Parses a string and its functions to its corrosponding value in the item object.
		
		Parameters:
			@string 	key   	the key to parse. Multiple levels in the xml are seperated by dots.
								Functions to parse are also included in key, seperated by a dollar sign '$'

		Returns:
			@string 			the string in the item object corrosponding to the key, after functions.
		*/

		var keysplit = key.split('$');
		// parse the key
		var txt = this.parseKeySimple(keysplit[0]);
		if( !txt ) return false;

		// execute the functions
		if ( typeof keysplit[1] != 'undefined' ){
			txt = this.functions(txt,keysplit[1]);
		}
		return txt; 
	}

	this.parseKey = function(text){

		var keystart, keyend;
		// if a key is located in text
		if((keystart = text.indexOf('{')) != -1){
			// determine the end of the key
			keyend = keystart + text.substring(keystart).indexOf('}');

			// save key and text-to-replace
			var texttoreplace = text.substring(keystart,keyend+1);
			var sleutel = texttoreplace.substring(1,texttoreplace.length-1);

			// replace the key
			text = text.replace(texttoreplace,this.parseKeyFunctions(sleutel));

			// recurse

			return this.parseKey(text);
		}

		return text;
	}

	this.functions = function(txt,functionsString){
		var functions = functionsString.split(';');
		for(var i=0;i<functions.length;++i){
			var func = functions[i].split('(')[0];
			var arguments = functions[i].split('(')[1].replace(')','').split(',');

			txt = this[func](txt,arguments);
		}
		return txt;
	}

	this.lowerCase = function(txt,arguments){
		return txt.toLowerCase();
	}

	this.upperCase = function(txt,arguments){
		return txt.toUpperCase();
	}

	this.capitaliseFirst = function(string,arguments) {
	    return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
	}

	this.capitalise = function(txt,arguments){
		var lWords = txt.split(' ');
		txt = "";
		for(var i=0;i<lWords.length;++i)
			txt += this.capitaliseFirst(lWords[i]) + ' ';
		return txt;
	}

	this.substring = function(txt,arguments){
		var begin 	= parseInt(arguments[0]);
		var end		= typeof arguments[1] == 'undefined' ? txt.length : parseInt(arguments[1]);

		return txt.substring(begin,end);
	}

	this.strFit = function(txt,arguments){
		return strFit(txt,parseInt(arguments[0]),' ');
	}

	this.words = function(txt,arguments){
		var words = txt.split(' ');
		var begin = parseInt(arguments[0]);
		var end   = typeof arguments[1] == 'undefined' ? words.length : parseInt(arguments[1]);
		var result = '';
		
		for(var j=begin;j<end;++j)
			result += words[j]+' ';
		return result;
	}

	this.removeTxt = function(txt,arguments){
		for(var i=0;i<arguments.length;++i){
			
			txt = txt.replace(arguments[i],'');
		}
		return txt;
	}

	this.replaceText = function(txt,arguments){
		var key = arguments[0];
		var replaceby = arguments[1];

		var re = new RegExp(key,"g");
		return txt.replace(re,replaceby);
	}
	
}


Logger.log('   -> readItems.js loaded')