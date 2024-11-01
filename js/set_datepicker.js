jQuery(function() {
	var curr_date = '<?=date("Y/m/d"); ?>';
	jQuery('#txt_from_date').datepicker({minDate: curr_date,dateFormat: 'yy-mm-dd'});	
	jQuery('#txt_to_date').datepicker({minDate: curr_date,dateFormat: 'yy-mm-dd'});	
});