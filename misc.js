
function objectDump(objObject){
	/* 
	Dumps al methods of objObject as a string

	Parameters
		@obj 	objObject	: the object to dump

	returns:
		@str 	the string representing the object

	*/
	var str = "";
	for (var key in objObject){
		if (objObject.hasOwnProperty(key)){
			str += key + ":"+objObject[key]+", ";
		}
	}
	return "{"+str+"}";
}

Object.size = function(obj) {
    var size = 0, key;
    for (key in obj) {
        if (obj.hasOwnProperty(key)) size++;
    }
    return size;
}

Object.keys = function(obj){
	keys = [];
	for (var key in obj){
		if(obj.hasOwnProperty(key))	
			keys.push(key);
	}
	return keys;
}

function capitalise(string) {
    return string.charAt(0).toUpperCase() + string.slice(1).toLowerCase();
}

function allCapitalise(txt){
	var lWords = txt.split(' ');
	txt = "";
	for(var i=0;i<lWords.length;++i)
		txt += capitalise(lWords[i]) + ' ';
	return txt;
}

function makeFloat(strNumber){
	var lastPoint = strNumber.lastIndexOf('.');
	var lastComma = strNumber.lastIndexOf(',');
	if(lastPoint > lastComma)
		strNumber = strNumber.replace(/[^.\d]/g, "");
	else
		strNumber = strNumber.replace(/[^,\d]/g, "");
	return parseFloat(strNumber);
}

function throwError(msg){
	Logger.log(msg);
	throw msg;
}

function strFit(txt,maxLength,sep){
	
	var words = txt.split(sep);
	var txt = '';
	for(var i=0;i<words.length;i++){
   		if(txt.length + words[i].length > maxLength) break;
   		txt += words[i] + sep;
	}
	return txt.substring( 0,txt.length-1 );
}
function mergeArrays(array1,array2){
  for(var i=0;i<array2.length;++i){
     array1.push(array2[i]);
  }
  return array1;
}

function removeA(arr) {
    var what, a = arguments, L = a.length, ax;
    while (L > 1 && arr.length) {
        what = a[--L];
        while ((ax= arr.indexOf(what)) !== -1) {
            arr.splice(ax, 1);
        }
    }
    return arr;
}

function time(){
	return 1800 - AdWordsApp.getExecutionInfo().getRemainingTime();
}

function stringify(object){
	if(object.constructor === String)
		return '\"' + object.replace(/\"/g,'') + '\"';
	else if(object.constructor === Array){
		var string = '[';
		for(var i=0;i<object.length;++i){
			string += stringify(object[i]) + ',';
		}
		return string.slice(0,-1) + ']';
	}
	else if(object.constructor === Object){
		var string = '{';
		for(var key in object){
			if(object.hasOwnProperty(key)){
				string += '\"' + key.replace(/\"/g,'') + '\":';
				string += stringify(object[key]) + ',';
			}
		}
		return string.slice(0,-1) + '}'; 
	}
}

String.prototype.removeTxt = function() {
	var text = this;
	for(var i=0;i<arguments.length;++i){
		var re = new RegExp(arguments[i],'g');
		text = text.replace(re,'');
	}
	return text;
};


Logger.log('   -> misc.js loaded')
