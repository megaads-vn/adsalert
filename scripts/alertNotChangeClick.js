var ACCOUNT = "TEST123";
var SERVICE_URL = "http://adsalert.agoz.me/ads/not-increase-click";
var MAIL_TO = "abc@gmail.com,xxx@gmail.com";
var CALL_TO = "+84123456789,+84123456780";

function main() {
    var accountSelector = MccApp.accounts();//.withIds(["744-728-6416"]);
    accountSelector.executeInParallel("run", "finish");
}

function run() {
    var retval = [];
    var accountName = AdWordsApp.currentAccount().getName();
    var campIter = AdWordsApp.campaigns().withCondition('Status = ENABLED').get();
    while (campIter.hasNext()) {
        var camp = campIter.next();
        var click = camp.getStatsFor("TODAY").getClicks();
        retval.push({
            "accountName": accountName,
            "campaignName": camp.getName(),
            "count": click
        });
    }
    return JSON.stringify(retval);
}
function finish(results) {
    var campains = [];
    for (var i = 0; i < results.length; i++) {
        var returnValue = JSON.parse(results[i].getReturnValue());
        if (returnValue.length > 0) {
            campains.push(returnValue[0]);
        }
    }
    var options = {
        "method": "post",
        "payload": {
            "account": ACCOUNT,
            "campaigns": JSON.stringify(campains),
            "mailTo": MAIL_TO,
            "callTo": CALL_TO
        }
    };
    var response = UrlFetchApp.fetch(SERVICE_URL, options);
    Logger.log("UrlFetchApp response: " + response);
}