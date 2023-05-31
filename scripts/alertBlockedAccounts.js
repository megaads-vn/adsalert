var USERNAME = 'TEST123';
var SERVICE_URL = "http://adsalert.agoz.me/ads/blocked";
var MAIL_TO = "abc@gmail.com,xxx@gmail.com";
var CALL_TO = "+84123456789,+84123456780";

function main() {
    var accountSelector = MccApp.accounts();//.withIds(["744-728-6416"]);
    accountSelector.executeInParallel("run", "finish");
}

function run() {
    var retval = [];
    var accountName = AdWordsApp.currentAccount().getName();
    var accountId = AdWordsApp.currentAccount().getCustomerId();
    var campIter = AdWordsApp.campaigns()
        .withCondition('Status = ENABLED')
        .get();
    while (campIter.hasNext()) {
        var camp = campIter.next();
        var impressions = camp.getStatsFor("TODAY").getImpressions();
        retval.push({
            "accountName": accountName,
            "accountId": accountId,
            "campaignName": camp.getName(),
            "impressions": impressions
        });
    }
    return JSON.stringify(retval);
}
function finish(results) {
    var accounts = [];
    for (var i = 0; i < results.length; i++) {
        var returnValue = JSON.parse(results[i].getReturnValue());
        if (returnValue.length > 0) {
            var impressions = 0;
            for (var j = 0; j < returnValue.length; j++) {
                impressions += returnValue[j].impressions;
            }
            accounts.push({
                accountName: returnValue[0].accountName,
                impressions: impressions
            })
        }
    }
    Logger.log("Accounts: " + accounts);
    var options = {
        "method": "post",
        "payload": {
            'username': USERNAME,
            "accounts": JSON.stringify(accounts),
            "mailTo": MAIL_TO,
            "callTo": CALL_TO
        }
    };
    var response = UrlFetchApp.fetch(SERVICE_URL, options);
    Logger.log("UrlFetchApp response: " + response);
}