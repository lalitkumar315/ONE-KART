angular.module('App').controller('OrderController', function ($rootScope, $scope, $http, $mdToast, $mdDialog, $cookies, request) {

    var self = $scope;
	var root = $rootScope;

	root.search_enable = true;
    root.toolbar_menu = { title: 'Add Order' };
	root.pagetitle = 'Order';

	// receiver barAction from rootScope
    self.$on('barAction', function (event, data) {
        root.setCurOrderId("");
        window.location.href = '#create_order';
    });

    // receiver submitSearch from rootScope
    self.$on('submitSearch', function (event, data) {
        self.q = data;
        self.loadPages();
    });

	self.loadPages = function () {
		$_q = self.q ? self.q : '';
        request.getAllProductOrderCount($_q).then(function (resp) {
            self.paging.total = Math.ceil(resp.data / self.paging.limit);
            self.paging.modulo_item = resp.data % self.paging.limit;
        });
		$limit = self.paging.limit;
		$current = (self.paging.current * self.paging.limit) - self.paging.limit + 1;
		if (self.paging.current == self.paging.total && self.paging.modulo_item > 0) {
			self.limit = self.paging.modulo_item;
		}
		request.getAllProductOrderByPage($current, $limit, $_q).then(function (resp) {
			self.product_order = resp.data;
			self.loading = false;
			//console.log(JSON.stringify(resp.data));
		});
	};

	//pagination property
	self.paging = {
		total: 0, // total whole item
		current: 1, // start page
		step: 3, // count number display
		limit: 20, // max item per page
		modulo_item: 0,
		onPageChanged: self.loadPages,
	};

    self.editOrder = function(ev, po) {
        root.setCurOrderId(po.id);
        window.location.href = '#create_order';
    };

	self.detailsOrder = function(ev, po) {
		$mdDialog.show({
			controller          : DetailsOrderControllerDialog,
			templateUrl         : 'view/order/details.html',
			parent              : angular.element(document.body),
			targetEvent         : ev,
			clickOutsideToClose : true,
			order               : po,
            process             : false
		})
	};

    self.processedOrderConfirm = function(ev, po) {
        var confirm = $mdDialog.confirm().title('Process Order Confirmation');
            confirm.content('After processed, order status & payment status can not be changed and product stock will be reduced.' +
                            '<br>Please review order before press button <b>PROCESS ORDER</b>');
            confirm.targetEvent(ev).ok('OK').cancel('CANCEL');

        $mdDialog.show(confirm).then(function() {
            $mdDialog.show({
                controller          : DetailsOrderControllerDialog,
                templateUrl         : 'view/order/details.html',
                parent              : angular.element(document.body),
                targetEvent         : ev,
                clickOutsideToClose : true,
                order               : po,
                process             : true
            })
        });
    };

    self.deleteOrder = function(ev, po) {
        var confirm = $mdDialog.confirm().title('Cancel Order Confirmation');
            confirm.content('Are you sure want to <b>DELETE</b> order from : '+po.buyer+' ?');
            confirm.targetEvent(ev).ok('OK').cancel('CANCEL');

        $mdDialog.show(confirm).then(function() {
            request.deleteOneProductOrder(po.id).then(function(resp){
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', 'Delete order from '+po.buyer+' <b>Success</b>!', function(){
                        window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', 'Opps , <b>Failed Delete</b> Order from '+po.buyer);
                }
            });
        });

    };
});

function DetailsOrderControllerDialog($scope, $rootScope, $mdDialog, request, $mdToast, $route, order, process) {
	var self        	= $scope;
	var root            = $rootScope;
	self.order      	= angular.copy(order);
	self.process      	= process;
	self.order_details 	=  null;
	self.hide   = function() { $mdDialog.hide(); };
	self.cancel = function() { $mdDialog.cancel(); };
	self.order.total_fees = parseFloat(self.order.total_fees).toFixed(2);

	request.getAllProductOrderDetailByOrderId(order.id).then(function (resp) {
		self.order_details = resp.data;
        // calculate data
        self.calculateTotal();
	});

    /* get config data */
    request.getAllConfigByCode('GENERAL').then(function (resp) {
        var config = resp.data;
        self.conf_currency = config.currency;
        self.conf_tax = config.tax;
        self.conf_featured_news = config.featured_news;
    });

	self.getPriceTotal = function (pod) {
	    return parseFloat(pod.price_item*pod.amount).toFixed(2);
    };

	self.print = function () {
        window.open(
            ('view/order/print.html?id=' + order.id) ,
            '_blank'
        );
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

    self.processOrder = function (od) {
        self.process_loading = true;
        request.updateOrderPaid(od.id, od, self.order_details).then(function(resp){
            self.process_loading = false;
            //console.log(JSON.stringify(resp));
            $mdDialog.show({
                templateUrl         : 'view/order/process_result.html',
                parent              : angular.element(document.body),
                clickOutsideToClose : false,
                resp                : resp,
                order               : od,
                controller          : function DialogController($scope, $rootScope, $mdDialog, $route, resp, order) {
                    $scope.resp     = resp;
                    $scope.order    = order;
                    $scope.success  = ( resp.status == 'success' );
                    $scope.cancel   = function() {
                        $mdDialog.cancel();
                        if($scope.success){
                            window.location.reload();
                        }
                    };
                    $scope.edit   = function() {
                        $mdDialog.cancel();
                        root.setCurOrderId(od.id);
                        window.location.href = '#create_order';
                    };
                }
            });
        });
    };

    self.copyPaymentUrl = function() {
        var url = window.location.href.split("#/order");
        var payment_url = url[0] + 'services/paymentPage?code=' + self.order.code;
        if (window.clipboardData && window.clipboardData.setData) {
            // Internet Explorer-specific code path to prevent textarea being shown while dialog is visible.
            return clipboardData.setData("Text", payment_url);

        }
        else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
            var textarea = document.createElement("textarea");
            textarea.textContent = payment_url;
            textarea.style.position = "fixed";
            document.body.appendChild(textarea);
            textarea.select();
            try {
                $mdToast.show($mdToast.simple().content('Payment URL copied').position('bottom right'));
                return document.execCommand("copy");
            }
            catch (ex) {
                console.warn("Copy to clipboard failed.", ex);
                return false;
            }
            finally {
                document.body.removeChild(textarea);
            }
        }
    }
}