var USERNAME = 'Megaads MCC - Khoan';
var SERVICE_URL = "http://adsalert.agoz.me/ads/limited-budget";
var MAIL_TO = "phult.contact@gmail.com,khoan.mega";
var CALL_TO = "";

function main() {
    var accountSelector = MccApp.accounts(); //.withIds(["744-728-6416"]);
    accountSelector.executeInParallel("run", "finish");
}

function getDate(date) {
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
        var stats = camp.getStatsFor('TODAY');
        var cost = stats.getCost();
        var budget = stats.getBudget();
        var impressions = stats.getImpressions();
        if (cost >= budget) {
            var item = {
                "accountName": accountName,
                "campaignName": camp.getName(),
                "campaignId": camp.getId(),
                "impressions": impressions
            };
            retval.push(item);
        }
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
                    impressions: returnValue[j].impressions
                });
            }
        }
    }
    Logger.log("Accounts Limited Budget: " + JSON.stringify(accounts));

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
