function xml2obj(feed){
    var feedObj, end, topName;
    
    feedObj = {};
    feed    = processFeed(feed);

    //remove specifications string
    if(feed.substring(0,2) == '<?'){
        end             = feed.indexOf('?>') + 2;
        feedObj['spec'] = readAttributes(feed.substring(2,end-2));
        feed            = feed.substring(end);  
    }
    feed    = feed.trim();
    topName = findFirstName(feed);
    
    feedObj[topName] = xml2objRec(feed,0);
    return feedObj;
}

function xml2objRec(feed,depth){
    
    
    

    var list = [];
    var name = findFirstName(feed);
    var closed = findClosed(feed);
    var closedIndex = feed.indexOf('>');
    if(closed)
        var attributesString = feed.substring(name.length+2,feed.indexOf('/>'));
    else
        var attributesString = feed.substring(name.length+2,feed.indexOf('>'));
    var attributes = readAttributes(attributesString);
    if(closed)
        return attributes;
    if(Object.size(attributes)!=0)
        list.push(attributes);
    feed = feed.substring(closedIndex+1,feed.length - name.length - 3);
    feed = feed.trim();
    if(justText(feed)){
        return feed;
    }

    while(feed.length != 0){

        var obj = {};
        var childName = findFirstName(feed);
        var childString = findFirstChildString(feed,childName);


    
        feed = feed.substring(childString.length);
        feed = feed.trim();

        if( childName == 'cdata')
            obj[childName] = childString;       
        else
            obj[childName] = xml2objRec(childString,depth++);                   
        list.push(obj);
    }
    
    obj = {};
    obj = joinObjects(list);

    return obj;
}

function justText(feed){    
    return feed.indexOf('<') == -1 || feed.indexOf('>') == -1;         
 
}

function findFirstChildString(feed,childname){
    var i = 1;
    var close = 1;
    if(findClosed(feed))
        var childString = feed.substring(0,feed.indexOf('/>')+2);
    else{
        while(i != 0){
            var firstOpen = feed.substring(close).indexOf('<'+childname+'>');
            var firstClose = feed.substring(close).indexOf('</'+childname+'>');

            if(firstOpen == -1 || firstClose < firstOpen){
                i--;
                close += firstClose + childname.length + 3;
            }else{
                i++;
                close += firstOpen + childname.length + 2;
            }               
        }
        childString = feed.substring(0,close);
    }
    return childString;
}

function joinObjects(listObjects){
    var obj = {}
    for(var i=0;i<listObjects.length;++i){
        for(var key in listObjects[i]){
            if(listObjects[i].hasOwnProperty(key)){
                if(!obj.hasOwnProperty(key)){
                    obj[key] = listObjects[i][key];
                    continue;
                }
                if(Array.isArray(obj[key])){
                    obj[key].push(listObjects[i][key]);
                    continue;
                }
                var objTemp = obj[key];
                delete obj[key];
                obj[key] = [];
                obj[key].push(objTemp);
                obj[key].push(listObjects[i][key]);
            }
        }
    }
    return obj;
}
function findFirstName(feed){
    var firstSpace = feed.indexOf(' ');
    var firstArrow = feed.indexOf('>');
    if(firstSpace == -1 || firstSpace > firstArrow)
        var name = feed.split('>');
    else 
        var name = feed.split(' ');
    name = name[0];
    if(name.charAt(0)=='<') name = name.substring(1);
    return name;
}
function findClosed(feed,name){
    var firstArrow = feed.indexOf('>');
    var firstSlashArrow = feed.indexOf('/>');
    if(firstSlashArrow == -1 || firstArrow<firstSlashArrow)
        return false
    return true;
}
function readAttributes(attributesString){
    var attributes = {};
    var endkey;
    while(true){
        var endkey = attributesString.indexOf('=');
        if(endkey == -1) break;
        var key = attributesString.substring(0,endkey);
        var attributestart = endkey+1;
        var attributeend = attributesString.substring(attributestart+1).
            indexOf(attributesString.charAt(attributestart));
        var attribute = attributesString.substring(attributestart+1,
            attributeend+attributestart+1);
        attributes[key] = attribute;
        attributesString = attributesString.substring(attributeend+
            attributestart+3);
    }
    return attributes;
}

function processFeed(feed){
    feed = feed.replace(/\n/g,"");

    var re = new RegExp(decodeURI('%0D'),"g");
    feed = feed.replace(re,'');
    re = new RegExp(decodeURI('%09'),'g');
    feed = feed.replace(re,'');

    feed = feed.replace(/\<\!\[CDATA\[/g,'<cdata>');
    feed = feed.replace(/\]\]\>/g,'</cdata>');

    return feed;
}
