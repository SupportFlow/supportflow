module.exports = function (grunt) {
	var js_files = [
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
	];

	grunt.initConfig({
		uglify: {
			options: {
				banner: '/*! SupportFlow minified version */\n'
			},
			build  : {
				src : js_files,
				dest: 'js/supportflow.min.js'
			}
		},
		watch : {
			scripts: {
				files: js_files,
				tasks: ['uglify'],
			},
		},
	});

	// Load the required plugins.
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-watch');

	// Running these tasks by default
	grunt.registerTask('default', ['uglify']);

};