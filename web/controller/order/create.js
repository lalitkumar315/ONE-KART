angular.module('App').controller('AddOrderController', function ($rootScope, $scope, $http, $mdToast, $mdDialog, $route, $timeout, request) {

	// define variable
	var self 		= $scope, root = $rootScope;
	var is_new 		= ( root.getCurOrderId() == null );
	var now 		= new Date().getTime();
	var original 	    = null;
	var original_detail = new Array();

	root.search_enable 		= false;
	root.toolbar_menu 		= null;
	root.pagetitle 			= (is_new) ? 'Add Order' : 'Edit Order';
	self.button_text 		= (is_new) ? 'SAVE' : 'UPDATE';
	self.is_new 			= angular.copy(is_new);
	self.submit_loading 	= false;
	self.status_array 		= ["WAITING"];
	self.payment_status     = (is_new) ? ["WAITING"] : ["WAITING", "PAID", 'REFUND'];
	self.shipping_array 	= [];
	self.order_details      = new Array();
	self.date_ship			= new Date();
	self.now			   	= new Date();
	self.send_email_update	= false;
	self.shipping			= {selected_ship_loc:null, selected_ship:null, q:"", type:null};
	self.payment_url		= null;
	root.closeAndDisableSearch();
	self.is_paid            = false;
	
	/* check edit or add new*/
	if (is_new) {
		original = {
			buyer: null, address: null, email: null, date_ship: null, phone: null,
            shipping_location:null, shipping: null, shipping_rate:0, payment_status: 'WAITING',
            comment: null, status: "WAITING", total_fees: 0, tax:0, serial: null, created_at: now, last_update: now
		};
		self.order = angular.copy(original);
	} else {
		request.getOneProductOrder(root.getCurOrderId()).then(function(resp){
			original = resp.data;
            self.status_array = [original.status];
			self.order = angular.copy(original);
			self.date_ship = new Date(self.order.date_ship);
			self.is_paid = original.payment_status == 'PAID';
            var url = window.location.href.split("#/create_order");
            self.payment_url = url[0] + 'services/paymentPage?code=' + self.order.code;
            request.getAllProductOrderDetailByOrderId(root.getCurOrderId()).then(function (resp) {
                original_detail = resp.data;
                self.order_details = angular.copy(original_detail);
                self.calculateTotal();
            });
            self.shipping = {
                selected_ship_loc: null,
                selected_ship:self.order.shipping, q:null, type:self.order.shipping
            };
            request.listShipping(self.order.shipping_location).then(function (resp) {
                if(resp.data.shipping.length > 0) self.shipping.selected_ship_loc = resp.data.shipping[0];
            });
		});
	}

	/* get config data */
	request.getAllConfigByCode('GENERAL').then(function (resp) {
		var config = resp.data;
		self.conf_currency = config.currency;
		self.conf_tax = config.tax;
		//var shipping = root.findValue(self.config, 'SHIPPING');
		//self.shipping_array = JSON.parse(shipping);
		if (is_new) {
		    original.tax = self.conf_tax;
		    self.order.tax = self.conf_tax;
		}
	});

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


    /* method for submit action */
    /* [0] product_order done, [1] product_order_details done */
    self.done_arr = [false, false];
	self.submit = function(o) {
		var order = self.order;
		self.submit_loading = true;
		self.resp_submit    = null;
		self.submit_done    = false;
		self.done_arr       = [false, false];

        order.date_ship     = self.date_ship.getTime();
        order.total_fees    = self.total;
        order.last_update   = now;

		if(is_new){ // create
			request.insertOneProductOrder(order).then(function(resp){
				self.resp_submit = resp;
				if(resp.status == 'success'){
				    self.done_arr[0] = true;
				    self.prepareOrderDetails(resp.data.id);
				    // insert to product order details
                    request.insertAllProductOrderDetail(self.order_details, 1).then(function(){self.done_arr[1] = true;});
				} else {
					self.submit_done = true;
				}
			});
		} else {  // update
			request.updateOneProductOrder(order.id, order).then(function(resp){
				self.resp_submit = resp;
				if(resp.status == 'success'){
				    self.done_arr[0] = true;
				    self.prepareOrderDetails(order.id);
				    // insert to product order details
                    request.insertAllProductOrderDetail(self.order_details, 0).then(function(){
                    	self.done_arr[1] = true;
                    	if(self.send_email_update){ // send email
                            request.sendEmail(resp.data.id, "ORDER_UPDATE");
						}
                    });
				} else {
					self.submit_done = true;
				}
			});
		}
	};

	/* Submit watch onFinish Checker */
	self.$watchCollection('done_arr', function(new_val, old_val) {
		if(self.submit_done || (new_val[0] && new_val[1])){
			loop_run = false;
			$timeout(function(){ // give delay for good UI
				if(self.resp_submit.status == 'success'){
				    root.showConfirmDialogSimple('', self.resp_submit.msg, function(){
				        window.location.href = '#order';
				    });
				}else{
                    root.showInfoDialogSimple('', self.resp_submit.msg);
				}
				self.submit_loading = false;
			}, 1000);
		}
	});

    /* normalize order_details object by adding order id */
    self.prepareOrderDetails = function (id){
        for (var i = 0; i < self.order_details.length; i++) { self.order_details[i].order_id = id; }
    };

	/* remove product from order list */
	self.removeProduct = function (pod) {
	    var item_index = -1;
	    for(var i = 0; i < self.order_details.length; i++){
            if(self.order_details[i].product_id == pod.product_id){
                item_index = i;
            }
        }
        if(item_index > -1){
            self.order_details.splice(item_index, 1);
            self.calculateTotal();
        }
	};

    /* checker when all data ready to submit */
    self.isReadySubmit = function () {
        if (is_new) {
            self.is_clean = angular.equals(original, self.order);
            return (!self.is_clean && self.order_details.length > 0);
        } else {
            self.is_clean = angular.equals(original, self.order);
            return (!self.is_clean || original_detail.length != self.order_details.length) && self.order_details.length > 0;
        }
    };

    self.searchShippingLoc = function(query) {
        return request.listShipping(query).then(function (resp) {
            return resp.data.shipping;
        });
    };

    self.onShipLocChanged = function(item) {
		if(!item){
            self.shipping = {selected_ship_loc:null, selected_ship:null, q:""};
		} else {
            self.order.shipping_location = item.location;
		}
    };

    $scope.$watch("shipping.type", function(new_value, old_value) {
    	if(new_value != old_value && self.shipping.selected_ship_loc){
            self.order.shipping = new_value;
            if(new_value == 'Economy'){
                self.order.shipping_rate = self.shipping.selected_ship_loc.rate_economy;
            } else if(new_value == 'Regular'){
                self.order.shipping_rate = self.shipping.selected_ship_loc.rate_regular;
            } else if(new_value == 'Express'){
                self.order.shipping_rate = self.shipping.selected_ship_loc.rate_express;
            }
            self.calculateTotal();
    	}
    });

	self.cancel = function () { window.location.href = '#order'; };
	self.isNewEntry = function () { return is_new; };
    self.getPriceTotal = function (pod) { return parseFloat(pod.price_item*pod.amount).toFixed(2); };

	self.addProductDialog = function(ev) {
		$mdDialog.show({
			controller          : AddProductControllerDialog,
			templateUrl         : 'view/order/product_pick.html',
			parent              : angular.element(document.body),
			targetEvent         : ev,
			clickOutsideToClose : false,
			parentScope         : self
		})
	};

    self.editProductDialog = function(ev, pod) {
        $mdDialog.show({
            controller          : EditProductControllerDialog,
            templateUrl         : 'view/order/product_edit.html',
            parent              : angular.element(document.body),
            targetEvent         : ev,
            clickOutsideToClose : true,
			parentScope         : self,
            pod                 : pod
        })
    };

    self.copyPaymentUrl = function() {
        if (window.clipboardData && window.clipboardData.setData) {
            // Internet Explorer-specific code path to prevent textarea being shown while dialog is visible.
            return clipboardData.setData("Text", self.payment_url);

        }
        else if (document.queryCommandSupported && document.queryCommandSupported("copy")) {
            var textarea = document.createElement("textarea");
            textarea.textContent = self.payment_url;
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
    };

    self.orderPaidConfirm = function(ev, po) {
        var confirm = $mdDialog.confirm().title('Paid Order Confirmation');
        confirm.content('After set payment as PAID, order can not be changed and product stock will be reduced.' +
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

    self.cancelOrder = function(ev, po) {
        var confirm = $mdDialog.confirm().title('Cancel Order Confirmation');
        confirm.content('Are you sure want to <b>CANCEL</b> order from : '+po.buyer+' ?');
        confirm.targetEvent(ev).ok('OK').cancel('CANCEL');

        $mdDialog.show(confirm).then(function() {
            var new_ob = angular.copy(po);
            new_ob.status = 'CANCEL';
            new_ob.last_update = new Date().getTime();
            root.showProgressDialog();
            request.updateStatusProductOrder(new_ob.id, new_ob).then(function(resp){
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', 'Cancel Order from '+po.buyer+' <b>Success</b>!', function(){
                        window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', 'Opps , <b>Failed Cancel</b> Order from '+po.buyer);
                }
            });
        });
    };

    self.activateOrder = function(ev, po) {
        var confirm = $mdDialog.confirm().title('Cancel Order Confirmation');
        confirm.content('Are you sure want to <b>ACTIVATE</b> order from : '+po.buyer+' ?');
        confirm.targetEvent(ev).ok('OK').cancel('CANCEL');

        $mdDialog.show(confirm).then(function() {
            var new_ob = angular.copy(po);
            new_ob.status = 'WAITING';
            new_ob.last_update = new Date().getTime();
            root.showProgressDialog();
            request.updateStatusProductOrder(new_ob.id, new_ob).then(function(resp){
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', 'Activate Order from '+po.buyer+' <b>Success</b>!', function(){
                        window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', 'Opps , <b>Failed Cancel</b> Order from '+po.buyer);
                }
            });
        });
    };

    self.processedOrder = function(ev, po) {
        var confirm = $mdDialog.confirm().title('Process Order Confirmation');
        confirm.content('Are you sure want to <b>PROCESS</b> <br>and start <b>shipping</b> order from : '+po.buyer+' ?');
        confirm.targetEvent(ev).ok('OK').cancel('CANCEL');

        $mdDialog.show(confirm).then(function() {
            var new_ob = angular.copy(po);
            new_ob.status = 'PROCESSED';
            new_ob.last_update = new Date().getTime();
            root.showProgressDialog();
            request.updateStatusProductOrder(new_ob.id, new_ob).then(function(resp){
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', 'Process Order from '+po.buyer+' <b>Success</b>!', function(){
                        window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', 'Opps , <b>Failed Process</b> Order from '+po.buyer);
                }
            });
        });
    };

    self.refundOrder = function(ev, order) {
        $mdDialog.show({
            controller          : RefundControllerDialog,
            templateUrl         : 'view/order/refund.html',
            parent              : angular.element(document.body),
            targetEvent         : ev,
            clickOutsideToClose : true,
            parentScope         : self,
            order               : order
        })
    };

});


function EditProductControllerDialog($scope, $rootScope, $mdDialog, request, $mdToast, $route, parentScope, pod) {
	var self        	= $scope;
	var root            = $rootScope;
	self.hide           = function() { $mdDialog.hide(); };
	self.cancel         = function() { $mdDialog.cancel(); };
	var now 		    = new Date().getTime();
	self.pod            = pod;
	self.not_exist      = false;
	self.dif_name       = false;

    request.getOneProduct(pod.product_id).then(function(resp){
        self.product = resp.data;
        self.not_exist = (self.product.id == null);
        self.dif_name = (pod.product_name != self.product.name);
        if(!self.not_exist && self.pod.amount > self.product.stock){
            self.pod.amount = self.product.stock;
        }
    });

    self.decreaseAmount = function(){
        if(self.pod.amount > 1){
            self.pod.amount = self.pod.amount - 1;
            parentScope.calculateTotal();
        }
    };

    self.increaseAmount = function(){
        if(self.pod.amount < self.product.stock){
            self.pod.amount = self.pod.amount + 1;
            parentScope.calculateTotal();
        }
    };
}


function AddProductControllerDialog($scope, $rootScope, $mdDialog, request, $mdToast, $route, parentScope) {
	var self        	= $scope;
	var root            = $rootScope;
	self.hide           = function() { $mdDialog.hide(); };
	self.cancel         = function() { $mdDialog.cancel(); };
	var now 		    = new Date().getTime();
	self.category_id    = -1;

    // submit search
    self.submitSearch = function (ev, q) {
        self.q = q;
        self.loadPages();
    }

    request.getAllCategory().then(function(resp){
        var temp_category = {id:-1, name:'All Category'};
        self.categories_data = resp.data;
        self.categories_data.unshift(temp_category);
    });

    // add item to product order
    self.addItem = function (ev, p) {
    	var price = p.price_discount > 0 ? p.price_discount : p.price ;
        var item = {order_id: null, product_id: p.id, product_name: p.name, amount: 1, price_item: price, created_at: now, last_update: now};
        parentScope.order_details.push(item);
        parentScope.calculateTotal();
        self.cancel();
    };

    // check if product already exist
    self.isExistAtProductOrder = function(id){
        for(var i = 0; i < parentScope.order_details.length; i++){
            if(parentScope.order_details[i].product_id == id){
                return true;
            }
        }
        return false;
    };

	// load pages from database and display
    self.loadPages = function () {
        $_q = self.q ? self.q : '';
        request.getAllProductCount($_q, self.category_id).then(function (resp) {
            self.paging.total = Math.ceil(resp.data / self.paging.limit);
            self.paging.modulo_item = resp.data % self.paging.limit;
        });
        $limit = self.paging.limit;
        $current = (self.paging.current * self.paging.limit) - self.paging.limit + 1;
        if (self.paging.current == self.paging.total && self.paging.modulo_item > 0) {
            self.limit = self.paging.modulo_item;
        }
        request.getAllProductByPage($current, $limit, $_q, self.category_id, root.sort_by[0]).then(function (resp) {
            self.product = resp.data;
            self.loading = false;
        });
    };

    // pagination property
    self.paging = {
        total: 0, // total whole item
        current: 1, // start page
        step: 3, // count number display
        limit: 30, // max item per page
        modulo_item: 0,
        onPageChanged: self.loadPages,
    };
}

function RefundControllerDialog($scope, $rootScope, $mdDialog, request, $mdToast, $route, $timeout, parentScope, order) {
    var root        	= $rootScope;
    var self        	= $scope;
    self.hide           = function() { $mdDialog.hide(); };
    self.cancel         = function() { $mdDialog.cancel(); };
    self.conf_currency  = parentScope.conf_currency;
    self.returned_stock = true;
    self.order          = order;
    self.loading        = false;


    self.processRefund =  function () {
        self.loading = true;
        self.order.last_update = new Date().getTime();
        request.refundOrder(order.id, self.returned_stock).then(function(resp){
            $timeout(function(){ // give delay for good UI
                self.loading = false;
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', 'Refund Order from '+order.buyer+' <b>Success</b>!', function(){
                        window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', 'Opps , <b>Failed Refund</b> Order from '+order.buyer);
                }
            }, 1000);

        });
    }

}