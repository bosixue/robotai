var pendingRequests = {};
jQuery.ajaxPrefilter(function( options, originalOptions, jqXHR ) {
    var key = options.url;
    console.log(key);
    if (!pendingRequests[key]) {
        pendingRequests[key] = jqXHR;
    }else{
        jqXHR.abort();    //放弃后触发的提交
        // pendingRequests[key].abort();   // 放弃先触发的提交
    }

    var complete = options.complete;
    options.complete = function(jqXHR, textStatus) {
        pendingRequests[key] = null;
        if (jQuery.isFunction(complete)) {
        complete.apply(this, arguments);
        }
    };
});