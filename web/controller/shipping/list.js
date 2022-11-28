angular.module('App').controller('ShippingController', function ($rootScope, $scope, $http, $mdToast, $mdDialog, $timeout, $cookies, request) {
	var self = $scope;
	var root = $rootScope;

	root.search_enable = true;
	root.toolbar_menu = { title: 'Add Shipping' };
	root.pagetitle = 'Shipping';
	var original;

	// receiver barAction from rootScope
	self.$on('barAction', function (event, data) {
		self.addShipping(event, null);
	});

	// receiver submitSearch from rootScope
	self.$on('submitSearch', function (event, data) {
		self.q = data;
		self.loadPages();
	});

	self.loadPages = function () {
		$_q = self.q ? self.q : '';
		request.getAllShippingCount($_q).then(function (resp) {
			self.paging.total = Math.ceil(resp.data / self.paging.limit);
			self.paging.modulo_item = resp.data % self.paging.limit;
		});
		$limit = self.paging.limit;
		$current = (self.paging.current * self.paging.limit) - self.paging.limit + 1;
		if (self.paging.current == self.paging.total && self.paging.modulo_item > 0) {
			self.limit = self.paging.modulo_item;
		}
		request.getAllShippingByPage($current, $limit, $_q).then(function (resp) {
			self.shipping = resp.data;
            original = angular.copy(self.shipping);
			self.loading = false;
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

	self.deleteShipping = function(ev, a) {
		var confirm = $mdDialog.confirm().title('Delete Confirmation');
			confirm.content('Are you sure want to delete Shipping : '+a.name+', '+a.country_code+' ?');
			confirm.targetEvent(ev).ok('OK').cancel('CANCEL');

		$mdDialog.show(confirm).then(function() {
			request.deleteOneShipping(a.id).then(function(resp){
				if(resp.status == 'success'){
					root.showConfirmDialogSimple('', 'Delete Shipping '+a.name+', '+a.country_code+' <b>Success</b>!', function(){
					    window.location.reload();
					});
				}else{
				    var failed_txt = '';
                    if(resp.msg != null) failed_txt += '<br>' + resp.msg;
				    root.showInfoDialogSimple('Delete Failed', failed_txt);
				}
			});
		});
	};


	self.addShipping = function(ev, a) {
		$mdDialog.show({
			controller          : ShippingControllerDialog,
			templateUrl         : 'view/shipping/create.html',
			parent              : angular.element(document.body),
			targetEvent         : ev,
			clickOutsideToClose : true,
			shipping            : a
		})
	};

	self.isChanged = function () {
		return !angular.equals(original, self.shipping);
    };

    self.toggleActive = function(type, a) {
    	if(type == 'ALL'){
            a.active = a.active == 0 ? 1 : 0;
		} else if(type == 'ECO'){
            a.active_eco = a.active_eco == 0 ? 1 : 0;
		} else if(type == 'REG'){
            a.active_reg = a.active_reg == 0 ? 1 : 0;
		} else if(type == 'EXP'){
            a.active_exp = a.active_exp == 0 ? 1 : 0;
		}
    };

    self.submitAll = function() {
        self.loading_all = true;
        var edited = [];
        self.shipping.forEach(function (item, index, arr) {
            if(!angular.equals(item, original[index])){
                edited.push(item);
			}
        });
        $timeout(function () { // give delay for good UI
            request.updateAllShipping(edited).then(function(resp){
                self.loading_all = false;
                if(resp.status == 'success'){
                    root.showConfirmDialogSimple('', resp.msg, function(){
                        //window.location.reload();
                    });
                }else{
                    root.showInfoDialogSimple('', resp.msg);
                }
            });
        }, 1000);

    };
	
});


function ShippingControllerDialog($rootScope, $scope, $mdDialog, request, $mdToast, $route, $timeout, shipping) {
	var root    = $rootScope;
	var self    = $scope;
	var is_new  = (shipping == null);
	var now     = new Date().getTime();
	var original ;
	self.shipping = (!is_new) ? angular.copy(shipping) : {
		location:null, location_id:null,
		rate_economy:0, rate_regular:0, rate_express:0,
		active:1, active_eco:1, active_reg:1, active_exp:1
	};
	self.title = (is_new) ? 'Add Shipping' : 'Edit Shipping';
	self.buttonText = (is_new) ? 'SAVE' : 'UPDATE';
	original = angular.copy(self.shipping);
	self.is_new  = is_new;

	self.isClean = function() {
		return angular.equals(original, self.shipping);
	};

	/* get config data */
    request.getAllConfigByCode('GENERAL').then(function (resp) {
        var config = resp.data;
        self.currency = config.currency;
    });

	self.submit = function(a) {
		self.submit_loading = true;
		if(is_new){
		  	request.insertOneShipping(a).then(function(resp){
				self.afterSubmit(resp);
			});
		} else {
			a.last_update = now;
			request.updateOneShipping(a.id, a).then(function(resp){
				self.afterSubmit(resp);
			});
		}
	};

	self.afterSubmit = function(resp) {
		$timeout(function(){ // give delay for good UI
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

	self.hide = function() { $mdDialog.hide(); };
	self.cancel = function() { $mdDialog.cancel(); };
}
