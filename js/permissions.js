jQuery(document).ready(function () {
	jQuery('.permission_filters').change(function () {
		var user_id = jQuery('#change_user option:selected').data('user-id');
		var status = jQuery('#change_status option:selected').data('status');

		jQuery.ajax(ajaxurl, {
			type   : 'post',
			data   : {
				action                     : 'get_user_permissions',
				user_id                    : user_id,
				status                     : status,
				_get_user_permissions_nonce: SFPermissions._get_user_permissions_nonce,
			},
			success: function (content) {
				jQuery('#user_permissions_table').html(content);
			},
		});
	});

	jQuery(document).on('change', '.toggle_privilege', function () {
		var checkbox = jQuery(this);
		var checkbox_label = checkbox.siblings('.privilege_status');
		var permission_identifier = checkbox.data('permission-identifier');

		var allowed = checkbox.prop('checked');
		var user_id = permission_identifier.user_id;
		var privilege_type = permission_identifier.privilege_type;
		var privilege_id = permission_identifier.privilege_id;

		checkbox_label.html(SFPermissions.changing_status);
		checkbox.prop('disabled', true);

		jQuery.ajax(ajaxurl, {
			type    : 'post',
			data    : {
				action                    : 'set_user_permission',
				user_id                   : user_id,
				privilege_type            : privilege_type,
				privilege_id              : privilege_id,
				allowed                   : allowed,
				_set_user_permission_nonce: SFPermissions._set_user_permission_nonce,
			},
			success : function (content) {
				if (1 != content) {
					checkbox.prop('checked', !checkbox.prop('checked'));
					alert(SFPermissions.failed_changing_status);
				}
			},
			error   : function () {
				checkbox.prop('checked', !checkbox.prop('checked'));
				alert(SFPermissions.failed_changing_status);
			},
			complete: function () {
				var allowed = checkbox.prop('checked');
				if (true == allowed) {
					checkbox_label.html(SFPermissions.allowed);
				} else {
					checkbox_label.html(SFPermissions.not_allowed);
				}
				checkbox.prop('disabled', false);
			},
		});
	});
});