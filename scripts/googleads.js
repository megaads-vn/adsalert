var ACCOUNT = "TEST123";
var SERVICE_URL = "http://adsalert.agoz.me/ads/unapprove";

function main() {
    var accountSelector = MccApp.accounts();//.withIds(["235-905-9992"]);
    accountSelector.executeInParallel("run", "finish");
}

function run() {
    var retval = 0;
    var campIter = AdWordsApp.campaigns().withCondition('Status = ENABLED').get();
    var disapprovedList = [];
    while (campIter.hasNext()) {
        var camp = campIter.next();
        adsIter = camp.ads().withCondition('Status = ENABLED').withCondition('CombinedApprovalStatus NOT_IN [APPROVED, APPROVED_LIMITED, UNDER_REVIEW]').get();
        retval += adsIter.totalNumEntities();
        while (adsIter.hasNext()) {
            var ad = adsIter.next();
            Logger.log(camp.getName() + ' ' + ad.getAdGroup().getName() + ' ' + ad.getHeadline() + ' ' + ad.getDescription1() + ' ' + ad.getDescription2() + ' ' + ad.getDisapprovalReasons());
        }
    }
    Logger.log("retval= " + retval);
    return JSON.stringify(retval);
}
function finish(results) {
    var unApprovedAds = 0;
    for (var i = 0; i < results.length; i++) {
        unApprovedAds += parseInt(results[i].getReturnValue());
    }
    var options = {
        "method": "post",
        "payload": {
            "count": unApprovedAds,
            "account": ACCOUNT
        }
    };
    var response = UrlFetchApp.fetch(SERVICE_URL, options);
    Logger.log("unApprovedAds= " + unApprovedAds);
}