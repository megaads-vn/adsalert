var USERNAME = 'Megaads MCC - Khoan';
var SERVICE_URL = "http://adsalert.agoz.me/ads/cost";
var SERVICE_PAUSE_URL = "http://adsalert.agoz.me/ads/paused";
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

function addZero(num) {
    return num > 9 ? num : '0' + num;
}

function run() {
    var retval = [];
    var accountName = AdWordsApp.currentAccount().getName();
    var campIter = AdWordsApp.campaigns()
        .withCondition('Status = ENABLED')
        .get();
    var pausedCamp = [];
    var myRegex = /(active)\s([0-9]{2}.[0-9]{2}.[0-9]{4})/gm;
    while (campIter.hasNext()) {
        var camp = campIter.next();
        var toDate = '';
        var fromDate = '';

        var matches = myRegex.exec(camp.getName());
        if (matches && matches.length > 2) {
            var dateStr = matches[2];
            var dateArr = dateStr.split('.');
            if (dateArr.length == 3) {
                    if (dateArr[2].length == 2) {
                    dateArr[2] = '20' + dateArr[2];
                }
                var fromDateObj = new Date(dateArr[2], dateArr[1] - 1, dateArr[0]);
                fromDate = dateArr[2] + addZero(dateArr[1]) + addZero(dateArr[0]);
                fromDateObj.setDate(fromDateObj.getDate() + 31);
                toDate = getDate(fromDateObj);
            }
        }
        if (fromDate && toDate) {
            var cost = camp.getStatsFor(fromDate, toDate).getCost();
            var campName = camp.getName();
            var oldCampName = campName;
            campName = campName.toLowerCase();
            var regex = /\[([^\[\]]*)(ok)([^\[\]]*)\]/gm;
            campName = campName.replace(regex, '');
            if (cost >= 200000 && campName.toLowerCase().indexOf('ok') < 0) {
                Logger.log("Camp paused: " + oldCampName);
                pausedCamp.push(item);
                camp.pause();
            }
            if (
                campName.indexOf('chua ok') >= 0 ||
                campName.indexOf('ok') < 0
            ) {
                var item = {
                    "accountName": accountName,
                    "campaignName": camp.getName(),
                    "campaignId": camp.getId(),
                    "cost": cost
                };
                retval.push(item);
            }
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
