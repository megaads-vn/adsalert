var USERNAME = 'TEST123';
var SERVICE_URL = "http://adsalert.agoz.me/ads/cost";
var MAIL_TO = "abc@gmail.com,xxx@gmail.com";
var CALL_TO = "+84123456789,+84123456780";

function main() {
    var results = run();
    finish(results);
}

function run() {
    var retval = [];
    var accountName = AdWordsApp.currentAccount().getName();
    var campIter = AdWordsApp.campaigns()
        .withCondition('Status = ENABLED')
        .get();
    while (campIter.hasNext()) {
        var camp = campIter.next();
        var cost = camp.getStatsFor("LAST_30_DAYS").getCost();
        retval.push({
            "accountName": accountName,
            "campaignName": camp.getName(),
            "campaignId": camp.getId(),
            "cost": cost
        });
    }
    return retval;
}

function finish(results) {
    var accounts = [];
    for (var i = 0; i < results.length; i++) {
        var returnValue = results[i];   
        accounts.push({
          accountName: returnValue.accountName,
          campaignName: returnValue.campaignName,
          campaignId: returnValue.campaignId,
          cost: returnValue.cost
        });
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