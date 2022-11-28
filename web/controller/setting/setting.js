angular.module('App').controller('SettingController', function ($rootScope, $scope, $http, $mdToast, $mdDialog, $route, $timeout, request) {

	var self = $scope;
	var root = $rootScope;

	root.closeAndDisableSearch();
	root.toolbar_menu = null;
	$rootScope.pagetitle = 'Setting';

	self.tab_index = root.getCurSettingTab();
	self.onTabSelected = function(index) {
        root.setCurSettingTab(index);
    };

});


angular.module('App').controller('SettingGeneralController', function ($rootScope, $scope, $http, $mdToast, $route, $timeout, request) {
    var self = $scope;
    var root = $rootScope;
    var original;
    var new_value;
    self.selected_currency = {};

    request.getAllConfigByGroup('GENERAL').then(function (resp) {
        self.conf_general = resp.data;
        original = JSON.stringify(self.conf_general);
        self.conf_general.GENERAL = JSON.parse(self.conf_general.GENERAL);
        request.getAllCurrency().then(function (resp) {
            self.arr_currency = resp.data;
            self.arr_currency.forEach(function(item, index) {
                if(item.code == self.conf_general.GENERAL.currency) {
                    self.selected_currency = item;
                    //console.log(JSON.stringify(item));
                    return null;
                }
            });
        });
    });

    self.submit = function () {
        self.loading_conf_general = true;
        getNewValue();
        $timeout(function () { // give delay for good UI
            //console.log(JSON.stringify(new_value));
            request.updateAllConfig(new_value).then(function(resp){
                self.loading_conf_general = false;
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', resp.msg, function(){
                        window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', resp.msg);
                }
            });
        }, 1000);
    };

    var getNewValue = function() {
        new_value = angular.copy(self.conf_general);
        if(!new_value) return;
        new_value.GENERAL.currency = self.selected_currency.code;
        new_value.GENERAL = JSON.stringify(new_value.GENERAL);
    };

    self.isReadySubmit = function () {
        getNewValue();
        var is_clean = angular.equals(original, JSON.stringify(new_value));
        return !is_clean;
    };

});

angular.module('App').controller('SettingNotifController', function ($rootScope, $scope, $http, $mdToast, $route, $timeout, request) {
    var self = $scope;
    var root = $rootScope;
    var original;
    var new_value;

    request.getAllConfigByGroup('NOTIF').then(function (resp) {
        self.conf_notif = resp.data;
        original = JSON.stringify(self.conf_notif);
        self.conf_notif.NOTIF_KEY      = JSON.parse(self.conf_notif.NOTIF_KEY);
        self.conf_notif.NOTIF_TITLE    = JSON.parse(self.conf_notif.NOTIF_TITLE);
    });

    self.submit = function () {
        self.loading_conf_notif = true;
        getNewValue();
        $timeout(function () { // give delay for good UI
            //console.log(JSON.stringify(new_value));
            request.updateAllConfig(new_value).then(function(resp){
                self.loading_conf_notif = false;
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', resp.msg, function(){
                        window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', resp.msg);
                }
            });
        }, 1000);
    };

    var getNewValue = function() {
        new_value = angular.copy(self.conf_notif);
        if(!new_value) return;
        new_value.NOTIF_KEY      = JSON.stringify(new_value.NOTIF_KEY);
        new_value.NOTIF_TITLE    = JSON.stringify(new_value.NOTIF_TITLE);
    };

    self.isReadySubmit = function () {
        getNewValue();
        var is_clean = angular.equals(original, JSON.stringify(new_value));
        return !is_clean;
    };
});

angular.module('App').controller('SettingPaymentController', function ($rootScope, $scope, $http, $mdToast, $route, $timeout, request) {
    var self = $scope;
    var root = $rootScope;
    var original;
    var new_value;
    self.paypal_mode = ["SANDBOX", "LIVE"];

    request.getAllConfigByGroup('PAYMENT').then(function (resp) {
        self.conf_payment = resp.data;
        original = JSON.stringify(self.conf_payment);
        self.conf_payment.PAYMENT_BANK      = JSON.parse(self.conf_payment.PAYMENT_BANK);
        self.conf_payment.PAYMENT_PAYPAL    = JSON.parse(self.conf_payment.PAYMENT_PAYPAL);
        self.conf_payment.PAYMENT_RAZORPAY  = JSON.parse(self.conf_payment.PAYMENT_RAZORPAY);
    });

    self.submit = function () {
        self.loading_conf_payment = true;
        getNewValue();
        $timeout(function () { // give delay for good UI
            //console.log(JSON.stringify(new_value));
            request.updateAllConfig(new_value).then(function(resp){
                self.loading_conf_payment = false;
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', resp.msg, function(){
                        window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', resp.msg);
                }
            });
        }, 1000);
    };

    var getNewValue = function() {
        new_value = angular.copy(self.conf_payment);
        if(!new_value) return;
        new_value.PAYMENT_BANK      = JSON.stringify(new_value.PAYMENT_BANK);
        new_value.PAYMENT_PAYPAL    = JSON.stringify(new_value.PAYMENT_PAYPAL);
        new_value.PAYMENT_RAZORPAY  = JSON.stringify(new_value.PAYMENT_RAZORPAY);
    };

    self.isReadySubmit = function () {
        getNewValue();
        var is_clean = angular.equals(original, JSON.stringify(new_value));
        return !is_clean;
    };
});


angular.module('App').controller('SettingEmailController', function ($rootScope, $scope, $http, $mdToast, $route, $timeout, request) {
    var self = $scope;
    var root = $rootScope;
    var original;
    var new_value;
    var EMAIL_BCC_RECEIVER = [];

    request.getAllConfigByGroup('EMAIL').then(function (resp) {
        self.conf_email = resp.data;
        original = JSON.stringify(self.conf_email);
        self.conf_email.EMAIL_ORDER = JSON.parse(self.conf_email.EMAIL_ORDER);
        self.conf_email.EMAIL_SMTP 	= JSON.parse(self.conf_email.EMAIL_SMTP);
        self.conf_email.EMAIL_TEXT = JSON.parse(self.conf_email.EMAIL_TEXT);
        EMAIL_BCC_RECEIVER = angular.copy(self.conf_email.EMAIL_ORDER.bcc_receiver);
    });

    self.resetEmailReceiver = function() { self.conf_email.EMAIL_ORDER.bcc_receiver = EMAIL_BCC_RECEIVER; };

    self.submit = function () {
        self.loading_conf_email = true;
        getNewValue();
        $timeout(function () { // give delay for good UI
            //console.log(JSON.stringify(new_value));
            request.updateAllConfig(new_value).then(function(resp){
                self.loading_conf_email = false;
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', resp.msg, function(){
                        window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', resp.msg);
                }
            });
        }, 1000);
    };

    var getNewValue = function() {
        new_value = angular.copy(self.conf_email);
        if(!new_value) return;
        new_value.EMAIL_ORDER 	= JSON.stringify(new_value.EMAIL_ORDER);
        new_value.EMAIL_SMTP 	= JSON.stringify(new_value.EMAIL_SMTP);
        new_value.EMAIL_TEXT	= JSON.stringify(new_value.EMAIL_TEXT);
    };

    self.isReadySubmit = function () {
        getNewValue();
        var is_clean = angular.equals(original, JSON.stringify(new_value));
        return !is_clean;
    };

});


angular.module('App').controller('SettingUserController', function ($rootScope, $scope, $http, $mdToast, $route, $timeout, request) {
    var self = $scope;
    var root = $rootScope;

	/* Script controller for : User Panel Setting*/

    var cur_id = root.getSessionUid();
    self.submit_loading = false;
    self.re_passwordValid = true;
    var original;

    request.getOneUser(cur_id).then(function (data) {
        self.userdata = data.data;
        self.userdata.password = '*****';
        original = angular.copy(self.userdata);
        //console.log(JSON.stringify(self.userdata));
    });

    self.isClean = function () {
        return angular.equals(original, self.userdata);
    }

    self.isPasswordMatch = function () {
        if (self.re_password == null || self.re_password == '') {
            return true;
        } else {
            if (self.re_password == self.userdata.password) {
                return true;
            } else {
                return false;
            }
        }
    }

    self.submit = function (is_new) {
        self.submit_loading = true;
        if (!is_new) {
            //console.log(JSON.stringify(self.userdata));
            request.updateOneUser(cur_id, self.userdata).then(function (resp) {
                if (resp.status == 'success') {
                    // saving session
                    root.saveCookies(resp.data.user.id, resp.data.user.name, resp.data.user.email, resp.data.user.password);
                }
                self.afterSubmit(resp);
            });
        } else {
            if (self.userdata.password === '*****') {
                self.userdata.password = "";
                self.submit_loading = false;
                return;
            }
            self.re_passwordValid = true;
            if (self.re_password != self.userdata.password) {
                self.re_passwordValid = false;
                self.submit_loading = false;
                return;
            }
            self.userdata.id = null;
            request.insertOneUser(self.userdata).then(function (resp) {
                self.afterSubmit(resp);
            });
        }

    }

    self.afterSubmit = function (resp) {
        $timeout(function () { // give delay for good UI
            self.submit_loading = false;
            if(resp.status == 'success'){
                root.showConfirmDialogSimple('', resp.msg, function(){
                    window.location.reload();
                });
            }else{
                root.showInfoDialogSimple('', resp.msg);
            }
        }, 1000);
    };
});

