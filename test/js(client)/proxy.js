

const PROXY_URL_HEADER = 'X-Proxy-Dist-Url';

function proxyFetch(url, init={}, proxy_url=proxyFetch.proxyUrl){
    var [url, init, proxy_url] = proxyFetch.solveRequest(url, init, proxy_url);
    return fetch(proxy_url, init).then(response => {
        return proxyFetch.solveResponse(response);
    });
}
function _defaultInit(init){
    _setDefault(init, "headers", {});
    _setDefault(init["headers"], {
        'user-agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:86.0) Gecko/20100101 Firefox/86.0',
    });
    _setDefault(init, {
        cache: 'no-cache', // *default, no-cache, reload, force-cache, only-if-cached
        credentials: 'same-origin', // include, same-origin, *omit
        method: 'GET', // *GET, POST, PUT, DELETE, etc.
        mode: 'cors', // no-cors, cors, *same-origin
        redirect: 'follow', // manual, *follow, error
        referrer: 'no-referrer', // *client, no-referrer
    });
    return init;
}
proxyFetch.solveRequest = function(url, init, proxy_url){
    _defaultInit(init);
    init['headers']['X-Proxy-Dist-Url'] = url;
    init['headers'] = new Headers(init["headers"]);
    return [url, init, proxy_url];
};
function _setDefault(obj, key, val){
    if(key instanceof Array){
        for(var i=0;i<key.length;i++){
            if(val instanceof Array) _setDefault(obj, key[i], val[i]);
            else _setDefault(obj, key[i], val);
        }
    }else if(typeof key === 'object'){
        for(var k in key){
            _setDefault(obj, k, key[k]);
        }
    }else{
        if(obj[key]==null) obj[key] = val;
    }
}
function _getResponseLength(buffer){
    buffer = new Uint8Array(buffer);
    var len = buffer.length;
    //var decoder = new TextDecoder("utf-8");
    var res = "";
    for(let i=0;i<len;i++){
        let txt = String.fromCharCode(buffer[i])//decoder.decode(buffer[i]);
        let num = Number.parseInt(txt);
        if(Number.isNaN(num)) return Number.parseInt(res);
        else res += txt;
    }
}
proxyFetch.solveResponse = function(response){
    return response;
    return response.arrayBuffer().then(buffer =>{
        var len = _getResponseLength(buffer);
        var startPos = String(len).length;
        var jsonBuffer = buffer.slice(startPos, startPos + len);
        var json = JSON.parse(new TextDecoder("utf-8").decode(jsonBuffer));
        var body = buffer.slice(startPos + len);
        return new Response(body, json);
    });
};

proxyFetch.proxyUrl = "";


function proxyGet(url, init={}, proxy_url=proxyGet.proxyUrl){
    _defaultInit(init);
    init["url"] = url;
    var getParam = "";
    for(let key in init){
        if(typeof init[key] === "object"){
            for(let k in init[key]){
                getParam += `${encodeURIComponent(key)}[${encodeURIComponent(k)}]=${encodeURIComponent(init[key][k])}&`;
            }
        }else getParam += `${encodeURIComponent(key)}=${encodeURIComponent(init[key])}&`;
    }
    getParam = getParam.substr(0, getParam.length-1);
    proxy_url = proxy_url + '?' + getParam;
    return proxy_url;
}

proxyGet.proxyUrl = "";
