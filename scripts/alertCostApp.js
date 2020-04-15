var USERNAME = 'TEST123';
var SERVICE_URL = "http://adsalert.agoz.me/ads/cost";
var MAIL_TO = "abc@gmail.com,xxx@gmail.com";
var CALL_TO = "+84123456789,+84123456780";

function main() {
    var results = run();
    finish(results);
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