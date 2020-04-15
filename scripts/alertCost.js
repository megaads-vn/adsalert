var USERNAME = 'TEST123';
var SERVICE_URL = "http://adsalert.agoz.me/ads/cost";
var MAIL_TO = "abc@gmail.com,xxx@gmail.com";
var CALL_TO = "+84123456789,+84123456780";

function main() {
    var accountSelector = MccApp.accounts();//.withIds(["744-728-6416"]);
    accountSelector.executeInParallel("run", "finish");
}

function getDate(date)
{
    var year = date.getFullYear();
    var month = date.getMonth() + 1;
    month = month > 9 ? month : '0' + month;
    var day = date.getDate() > 9 ? date.getDate() : '0' + date.getDate();
    return year + '' + month + '' + day;
}

function run() {
    var retval = [];
    var accountName = AdWordsApp.currentAccount().getName();
    var campIter = AdWordsApp.campaigns()
        .withCondition('Status = ENABLED')
        .get();
    while (campIter.hasNext()) {
        var camp = campIter.next();
        var date = new Date();
        date.setDate(date.getDate() + 1);
        var today = getDate(date);
        date.setDate(date.getDate() - 1);
        date.setDate(date.getDate() - 30);
        var lastMonth = getDate(date);
        var cost = camp.getStatsFor(lastMonth, today).getCost();
        retval.push({
            "accountName": accountName,
            "campaignName": camp.getName(),
            "campaignId": camp.getId(),
            "cost": cost
        });
    }
    return JSON.stringify(retval);
}

function finish(results) {
    var accounts = [];
    for (var i = 0; i < results.length; i++) {
        var returnValue = JSON.parse(results[i].getReturnValue());
        if (returnValue.length > 0) {
            for (var j = 0; j < returnValue.length; j++) {
                accounts.push({
                    accountName: returnValue[j].accountName,
                    campaignName: returnValue[j].campaignName,
                    campaignId: returnValue[j].campaignId,
                    cost: returnValue[j].cost
                });
            }
        }
    }
    Logger.log("Accounts: " + JSON.stringify(accounts));

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
