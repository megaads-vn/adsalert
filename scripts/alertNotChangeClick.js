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
    var campIter = AdWordsApp.campaigns()
        .withCondition('Status = ENABLED')
        .withCondition('CombinedApprovalStatus IN [APPROVED, APPROVED_LIMITED, UNDER_REVIEW]')
        .get();
    while (campIter.hasNext()) {
        var camp = campIter.next();
        var clicks = camp.getStatsFor("TODAY").getClicks();
        retval.push({
            "accountName": accountName,
            "campaignName": camp.getName(),
            "clicks": clicks
        });
    }
    return JSON.stringify(retval);
}
function finish(results) {
    var campaigns = [];
    for (var i = 0; i < results.length; i++) {
        var returnValue = JSON.parse(results[i].getReturnValue());
        if (returnValue.length > 0) {
            campaigns = campaigns.concat(returnValue);
        }
    }
    var options = {
        "method": "post",
        "payload": {
            "campaigns": JSON.stringify(campaigns),
            "mailTo": MAIL_TO,
            "callTo": CALL_TO
        }
    };
    var response = UrlFetchApp.fetch(SERVICE_URL, options);
    Logger.log("UrlFetchApp response: " + response);
}