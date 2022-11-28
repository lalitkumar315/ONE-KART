angular.module('App').factory("request", function ($http, $cookies) {
    var api_base = '../../services/';

    var obj = {};
    var token = $cookies.get(window.location.origin + '_session_password');
    var config = {headers: {'Token': token}};
    obj.getOneProductOrder = function (id) {
        return $http.get(api_base + 'getOneProductOrder?id=' + id, config);
    };
    obj.getAllProductOrderDetailByOrderId = function (order_id) {
        return $http.get(api_base + 'getAllProductOrderDetailByOrderId?order_id=' + order_id);
    };
    obj.getAllConfigByCode = function (code) {
        return $http.get(api_base + 'getConfigByCode?code='+code, config);
    };

    return obj;
});
angular.module('App').controller('PrintOrderController', function ($scope, $rootScope, $location, request) {

    var self = $scope;
    var root = $rootScope;
    if ($location.search().id) {
        self.id = $location.search().id;
    } else {
        console.log("Id Not Found");
        return;
    }

    root.findValue = function (config, code) {
        for (var i = 0; i < config.length; ++i) {
            var obj = config[i];
            if (obj.code == code) return obj.value;
        }
    };

    request.getOneProductOrder(self.id).then(function (resp) {
        self.order = resp.data;
        self.order.total_fees = parseFloat(self.order.total_fees).toFixed(2);
        window.document.title = 'OneKart Order - ' + self.order.code;
        request.getAllProductOrderDetailByOrderId(self.id).then(function (resp) {
            self.order_details = resp.data;
            // calculate data
            self.calculateTotal();
        });
    });

    /* get config data */
    request.getAllConfigByCode('GENERAL').then(function (resp) {
        var config = resp.data;
        self.conf_currency = config.currency;
    });

    self.getPriceTotal = function (pod) {
        return parseFloat(pod.price_item * pod.amount).toFixed(2);
    };

    self.calculateTotal = function () {
        var sub_total = 0;
        var price_tax = 0;
        self.amount_total = 0;
        self.price_tax_formatted = 0;
        self.sub_total_formatted = 0;
        self.total = 0;
        for(var i=0; i<self.order_details.length; i++){
            self.amount_total += self.order_details[i].amount;
            sub_total += self.order_details[i].price_item * self.order_details[i].amount;
        }
        price_tax = (self.order.tax / 100) * sub_total;
        self.price_tax_formatted = parseFloat(price_tax).toFixed(2);
        self.sub_total_formatted = parseFloat(sub_total).toFixed(2);
        self.shipping_rate_formatted = parseFloat(self.order.shipping_rate).toFixed(2);
        self.total = parseFloat(sub_total + price_tax + self.order.shipping_rate).toFixed(2);
    };

    self.printAction = function () {
        window.print();
    };
});