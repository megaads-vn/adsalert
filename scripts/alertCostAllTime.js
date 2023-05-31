var USERNAME = 'Megaads MCC - Khoan';
var SERVICE_URL = "http://adsalert.agoz.me/ads/cost-all-time";
var SERVICE_PAUSE_URL = "http://adsalert.agoz.me/ads/paused";
var MAIL_TO = "phult.contact@gmail.com,khoan.mega@gmail.com";
var CALL_TO = "";

function main() {
    var accountSelector = MccApp.accounts().withIds(["495-513-6985","447-986-8039","788-720-8852","326-362-2447","566-750-8895","406-953-6438"]);
    accountSelector.executeInParallel("run", "finish");
}

function getDate(date) {
    var year = date.getFullYear();
    var month = date.getMonth() + 1;
    month = month > 9 ? month : '0' + month;
    var day = date.getDate() > 9 ? date.getDate() : '0' + date.getDate();
    return year + '' + month + '' + day;
}

function campNameToDate(campName) {
    var retval = [];
    if (campName.indexOf('active') >= 0) {
        var str = campName.substr(campName.indexOf('active') + 7, 10);
        if (str.length = 10) {
            retval = str.split('.');
        }
    }

    return retval;
}

function run() {
    var retval = [];
    var accountName = AdWordsApp.currentAccount().getName();
    var accountId = AdWordsApp.currentAccount().getCustomerId();
    var campIter = AdWordsApp.campaigns()
        .withCondition('Status = ENABLED')
        .get();
    var pausedCamp = [];
    while (campIter.hasNext()) {
        try {
            var camp = campIter.next();
            var campName = camp.getName();
            var oldCampName = campName;
            var cost = 0;
            campName = campName.toLowerCase();
            var regex = /\[([^\[\]]*)(ok)([^\[\]]*)\]/gm;
            campName = campName.replace(regex, '');
            if (campName.indexOf('active') >= 0) {
                var toDate = '';
                var fromDate = '';
                var dateArr = campNameToDate(campName);
                if (dateArr.length > 2) {
                    if (dateArr[2].length == 2) {
                        dateArr[2] = '20' + dateArr[2];
                    }
                    var fromDateObj = new Date(dateArr[2], dateArr[1] - 1, dateArr[0]);
                    fromDate = dateArr[2] + dateArr[1] + dateArr[0];
                    fromDateObj.setDate(fromDateObj.getDate() + 31);
                    toDate = getDate(fromDateObj);
                }
                if (fromDate && toDate) {
                    cost = camp.getStatsFor(fromDate, toDate).getCost();
                } else {
                    Logger.log('Error active: ' + campName);
                }
            } else {
                cost = camp.getStatsFor('ALL_TIME').getCost();
            }
            var item = {
                "accountName": accountName,
                "accountId": accountId,
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
        } catch (error) {
            Logger.log('error: ' + error);
        }
    }

    if (pausedCamp.length > 0) {
        var options = {
            "method": "post",
            "payload": {
                'username': USERNAME,
                "accounts": JSON.stringify(pausedCamp),
                "mailTo": MAIL_TO,
                "callTo": CALL_TO,
                "file": "alertCostAllTime"
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
