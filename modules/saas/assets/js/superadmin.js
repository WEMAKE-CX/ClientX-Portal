$(document).ready(function() {
	"use strict";

	$('#plan_image').change(function() {
		const file = this.files[0];
		if (file) {
		 	let reader = new FileReader();
		 	reader.onload = function(event) {
		    	$('#imgPreview').attr('src', event.target.result);
		    	$('#imgPreview').removeClass('hide');
		  	}
		 	reader.readAsDataURL(file);
		 	$('.existing_image').addClass('hide');
		}
	});

	$('body').on('change', 'input[name="settings[i_have_c_panel]"]',function (event) {
	    if($(this).val() == "1"){
	        $(".mysql_server_details").hide();
	        $(".cpanel_details").show();
	        return false;
	    }
	    $(".cpanel_details").hide();
	    $(".mysql_server_details").show();

	});

	$('input[name="settings[i_have_c_panel]"]:checked').trigger("change");

	$('body').on('click', '#checkDbUser',function (event) {
		var mysql_host = $('input[name="settings[mysql_host]"]').val().trim();
		var mysql_root_username = $('input[name="settings[mysql_root_username]"]').val().trim();
		var mysql_port = $('input[name="settings[mysql_port]"]').val().trim();
		var mysql_password = $('input[name="settings[mysql_password]"]').val().trim();
		var i_have_c_panel = $('input[name="settings[i_have_c_panel]"]:checked').val().trim();
		var cpanel_port = $('input[name="settings[cpanel_port]"]').val().trim();
		var cpanel_username = $('input[name="settings[cpanel_username]"]').val().trim();
		var cpanel_password = $('input[name="settings[cpanel_password]"]').val().trim();

		var btnCheckDbUser = $(this);
		btnCheckDbUser.attr('disabled', true);
		$('.loader').show();

		$.ajax({
			url: `${admin_url}saas/plans/checkDbUser`,
			type: 'POST',
			data: {mysql_host, mysql_port, mysql_root_username, mysql_password, i_have_c_panel, cpanel_port, cpanel_username, cpanel_password},
			dataType: 'json',
		}).done(function(res) {
			btnCheckDbUser.attr('disabled', false);
			$('.loader').hide();
			alert_float(res.color, res.message);
		});
	});

	$('body').on('show.bs.modal', '#change_plan_modal', function(event) {
		$('#change-plan-form')[0].reset();
		$('.selectpicker').selectpicker('refresh');
	});

	function changeSaasPlan(form) {
		$.ajax({
			url: `${admin_url}saas/plans/changeSaasPlan`,
			type: 'POST',
			dataType: 'json',
			data: $(form).serialize(),
		})
		.done(function(res) {
			var type = (res.status) ? 'success' : 'danger';
			alert_float(type,res.message);
			$('#change_plan_modal').modal('hide');
			setTimeout(function () {
				location.reload();
	        }, 2000);
		});
	}

	appValidateForm($('#change-plan-form'), {
		saas_plan: "required"
	}, changeSaasPlan);
    
    function generateAuthToken(length) {
        let result = '';
        const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        const charactersLength = characters.length;
        let counter = 0;
        while (counter < length) {
            result += characters.charAt(Math.floor(Math.random() * charactersLength));
            counter += 1;
        }
        
        return result;
    }

    $(".gentoken").on('click', function() {
        $('input[name="settings[saas_api_token]"]').val(generateAuthToken(40));
    });
});

