var USERNAME = 'Megaads MCC - Khoan';
var SERVICE_URL = "http://adsalert.agoz.me/ads/cost-all-time";
var SERVICE_PAUSE_URL = "http://adsalert.agoz.me/ads/paused";
var MAIL_TO = "phult.contact@gmail.com,khoan.mega@gmail.com";
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
    var pausedCamp = [];
    while (campIter.hasNext()) {
        var camp = campIter.next();
        var date = new Date();
        date.setDate(date.getDate() + 1);
        var today = getDate(date);

        var cost = camp.getStatsFor('20201001', today).getCost();
        var campName = camp.getName();
        var oldCampName = campName;
        campName = campName.toLowerCase();
        var regex = /\[([^\[\]]*)(ok)([^\[\]]*)\]/gm;
        campName = campName.replace(regex, '');
        var item = {
            "accountName": accountName,
            "campaignName": camp.getName(),
            "campaignId": camp.getId(),
            "cost": cost
        };
        if (cost >= 200000 && campName.toLowerCase().indexOf('ok') < 0) {
            Logger.log("Camp paused: " + oldCampName);
            pausedCamp.push(item);
            camp.pause();
        }
        if (
            campName.indexOf('chua ok') >= 0 ||
            campName.indexOf('ok') < 0
        ) {
            retval.push(item);
        }
    }

    if (pausedCamp.length > 0) {
        var options = {
            "method": "post",
            "payload": {
                'username': USERNAME,
                "accounts": JSON.stringify(pausedCamp),
                "mailTo": MAIL_TO,
                "callTo": CALL_TO
            }
        };
        var response = UrlFetchApp.fetch(SERVICE_PAUSE_URL, options);
        Logger.log("UrlFetchApp response: " + response);
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
