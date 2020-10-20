require(["jquery"], function($){
	$(document).on('click', 'input[type="checkbox"]', function(){
		var _self = $(this);
		
		if(_self.attr('name').search('safecharge_sub_enabled') > 0) {
			if(_self.is(':checked')) {
				$('input[name="product[price]"')
					.val('0.00')
					.prop('disabled', true);
			}
			else {
				$('input[name="product[price]"').prop('disabled', false);
			}
		}
	});
});