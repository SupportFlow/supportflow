module.exports = function (grunt) {
	grunt.initConfig({
		uglify: {
			options: {
				banner: '/*! SupportFlow minified version */\n'
			},
			build  : {
				src : [
					'js/dashboard.js',
					'js/email_accounts.js',
					'js/email_conversation.js',
					'js/permissions.js',
					'js/predefined-replies.js',
					'js/preferences.js',
					'js/respondents-autocomplete.js',
					'js/statistics.js',
					'js/ticket_attachments.js',
					'js/tickets.js',
				],
				dest: 'js/supportflow.min.js'
			}
		}
	});

	// Load the plugin that provides the "uglify" task.
	grunt.loadNpmTasks('grunt-contrib-uglify');

	// Default task(s).
	grunt.registerTask('default', ['uglify']);

};