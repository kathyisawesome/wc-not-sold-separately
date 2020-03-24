/* jshint node:true */
module.exports = function( grunt ) {
	'use strict';

	const sass = require('node-sass');
  	require('load-grunt-tasks')(grunt);

	grunt.initConfig({

		pkg: grunt.file.readJSON('package.json'),

		// Setting folder templates.
		dirs: {
			css: 'assets/css',
			js: 'assets/js',
			scss: 'assets/scss'
		},

		// Compile all .scss files.
		sass: {
			compile: {
				options: {
					implementation: sass,
					sourceMap: 'none',
					require: 'susy'
				},
				files: [
					{
						expand: true,
						cwd: '<%= dirs.scss %>',
						src: ['*.scss'],
						dest: '<%= dirs.css %>',
						ext: '.css'
					}
				]
			}
		},

		// Generate RTL .css files.
		rtlcss: {
			dist: {
				expand: true,
				src: [
					'<%= dirs.css %>/*.css',
					'!<%= dirs.css %>/*-rtl.css'
				],
				ext: '-rtl.css'
			}
		},

		// Minify all .css files.
		cssmin: {
		  dist: {
		    files: [{
		      expand: true,
		      src: [
					'<%= dirs.css %>/*.css'
				],
		      ext: '.css'
		    }]
		  }
		},

		// Autoprefixer.
		postcss: {
			options: {
				processors: [
					require( 'autoprefixer' )
				]
			},
			dist: {
				src: [
					'<%= dirs.css %>/*.css'
				]
			}
		},

		// JavaScript linting with JSHint.
		jshint: {
			options: {
				'esversion': 6,
				'force': true,
				'boss': true,
				'curly': true,
				'eqeqeq': false,
				'eqnull': true,
				'es3': false,
				'expr': false,
				'immed': true,
				'noarg': true,
				'onevar': true,
				'quotmark': 'single',
				'trailing': true,
				'undef': true,
				'unused': true,
				'sub': false,
				'browser': true,
				'maxerr': 1000,
				globals: {
					'jQuery': false,
					'$': false,
					'Backbone': false,
					'_': false,
					'wc_bundle_params': false,
					'wc_pb_number_round': false
				},
			},
			all: [
				'Gruntfile.js',
				'<%= dirs.js %>/*.js',
				'!<%= dirs.js %>/*.min.js'
			]
		},

		// Minify .js files.
		uglify: {
			options: {
				preserveComments: 'some'
			},
			jsfiles: {
				files: [{
					expand: true,
					cwd: '<%= dirs.js %>',
					src: [
						'*.js',
						'!*.min.js'
					],
					dest: '<%= dirs.js %>',
					ext: '.min.js'
				}]
			}
		},

		// Watch changes for assets.
		watch: {
			css: {
				files: [
					'<%= dirs.scss %>/*.scss'
				],
				tasks: [ 'sass', 'postcss' ]
			},
			js: {
				files: [
					'<%= dirs.js %>/*js'
				],
				tasks: ['uglify']
			}
		},

		// bump version numbers (replace with version in package.json)
		replace: {
			Version: {
				src: [
					'readme.txt',
					'<%= pkg.name %>.php'
				],
				overwrite: true,
				replacements: [
					{
						from: /Stable tag:.*$/m,
						to: 'Stable tag: <%= pkg.version %>'
					},
					{
						from: /Version:.*$/m,
						to: 'Version: <%= pkg.version %>'
					},
					{
						from: /public \$version = \'.*.'/m,
						to: 'public $version = <%= pkg.version %>'
					},
					{
						from: /public \$version      = \'.*.'/m,
						to: 'public $version      = <%= pkg.version %>'
					}
				]
			}
		},

	});

	grunt.registerTask( 'js', [
		'jshint',
		'uglify'
	]);

	grunt.registerTask( 'css', [
		'sass',
		'rtlcss',
		'postcss',
		'cssmin'
	]);

	grunt.registerTask( 'assets', [
		'js',
		'css'
	]);

	grunt.registerTask( 'build', [
		'replace',
		'assets'
	]);
};
