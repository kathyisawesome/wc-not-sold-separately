module.exports = function(grunt) {

    // load most all grunt tasks
    require('load-grunt-tasks')(grunt);

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),

        // Generate git readme from readme.txt
        wp_readme_to_markdown: {
            convert: {
                files: {
                    'readme.md': 'readme.txt'
                },
            },
        },

        // # Internationalization 

        // Add text domain
        addtextdomain: {
            textdomain: '<%= pkg.name %>',
            target: {
                files: {
                    src: ['*.php', '**/*.php', '!node_modules/**', '!build/**']
                }
            }
        },

        // Generate .pot file
        makepot: {
            target: {
                options: {
                    domainPath: '/languages', // Where to save the POT file.
                    exclude: ['build'], // List of files or directories to ignore.
                    mainFile: '<%= pkg.name %>.php', // Main project file.
                    potFilename: '<%= pkg.name %>.pot', // Name of the POT file.
                    type: 'wp-plugin' // Type of project (wp-plugin or wp-theme).
                }
            }
        },

        // bump version numbers
        replace: {
            Version: {
                src: [
                    'readme.txt',
                    'readme.md',
                    '<%= pkg.name %>.php'
                ],
                overwrite: true,
                replacements: [
                    {
                        from: /Stable tag:.*$/m,
                        to: "Stable tag: <%= pkg.version %>"
                    },
                    {
                        from: /Version:.*$/m,
                        to: "Version:           <%= pkg.version %>"
                    },
                    {
                        from: /public static \$version = \'.*.'/m,
                        to: "public static $version = '<%= pkg.version %>'"
                    },
                    {
                        from: /public \$version      = \'.*.'/m,
                        to: "public $version      = '<%= pkg.version %>'"
                    }
                ]
            }
        }

    });

    // makepot and addtextdomain tasks
    grunt.loadNpmTasks('grunt-wp-i18n');

    grunt.registerTask('docs', ['wp_readme_to_markdown']);

    grunt.registerTask('build', ['replace', 'makepot']);

};
