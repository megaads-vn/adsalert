var ACCOUNT = "TEST123";
var SERVICE_URL = "http://adsalert.agoz.me/ads/unapprove";
var MAIL_TO = "abc@gmail.com,xxx@gmail.com";
var CALL_TO = "+84123456789,+84123456780";

function main() {
    var accountSelector = MccApp.accounts();//.withIds(["744-728-6416"]);
    accountSelector.executeInParallel("run", "finish");
}

function run() {
    var retval = {
        disapprovedCount: 0,
        disapprovedList: []
    };
    var accountName = AdWordsApp.currentAccount().getName();
    var campIter = AdWordsApp.campaigns().withCondition('Status = ENABLED').get();
    while (campIter.hasNext()) {
        var camp = campIter.next();
        adsIter = camp.ads().withCondition('Status = ENABLED').withCondition('CombinedApprovalStatus NOT_IN [APPROVED, APPROVED_LIMITED, UNDER_REVIEW]').get();
        if (adsIter.totalNumEntities() > 0) {
            retval.disapprovedCount += adsIter.totalNumEntities();
            retval.disapprovedList.push({
                "accountName": accountName,
                "campaignName": camp.getName(),
                "adsCount": adsIter.totalNumEntities()
            });
        }
        // while (adsIter.hasNext()) {
        //     var ad = adsIter.next();
        //     Logger.log(camp.getName() + ' ' + ad.getAdGroup().getName() + ' ' + ad.getHeadline() + ' ' + ad.getDescription1() + ' ' + ad.getDescription2() + ' ' + ad.getDisapprovalReasons());
        // }
    }
    return JSON.stringify(retval);
}
function finish(results) {
    var unApprovedAds = 0;
    var disapprovedList = [];
    for (var i = 0; i < results.length; i++) {
        var returnValue = JSON.parse(results[i].getReturnValue());
        unApprovedAds += parseInt(returnValue.disapprovedCount);
        disapprovedList = disapprovedList.concat(returnValue.disapprovedList);
    }
    var message = "<div>";
    message += "<table>";
    message += "<tr>";
    message += "<th>";
    message += "Account";
    message += "</th>";
    message += "<th>";
    message += "Campaign";
    message += "</th>";
    message += "<th>";
    message += "Disapproved Ads";
    message += "</th>";
    message += "</tr>";
    for (var i = 0; i < disapprovedList.length; i++) {
        var element = disapprovedList[i];
        message += "<tr>";
        message += "<td>";
        message += element.accountName;
        message += "</td>";
        message += "<td>";
        message += element.campaignName;
        message += "</td>";
        message += "<td>";
        message += element.adsCount;
        message += "</td>";
        message += "</tr>";
    }
    message += "</table>";
    message += "</div>";
    var options = {
        "method": "post",
        "payload": {
            "account": ACCOUNT,
            "count": unApprovedAds,
            "message": message,
            "mailTo": MAIL_TO,
            "callTo": CALL_TO
        }
    };
    var response = UrlFetchApp.fetch(SERVICE_URL, options);
    Logger.log("UrlFetchApp response: " + response);
}