// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/* jshint node: true, browser: false */

/**
 * An activity to interface with WebEx.
 *
 * @package    mod_webexactvity
 * @author     Eric Merrill <merrill@oakland.edu>
 * @copyright  2016 Oakland University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Grunt configuration
 */
module.exports = function(grunt) {
    // We need to include the core Moodle grunt file too, otherwise we can't run tasks like "amd".
    require("grunt-load-gruntfile")(grunt);
    grunt.loadGruntfile("../../Gruntfile.js");

    const sass = require('node-sass');

    var cwd = process.cwd();

    var uglifyRename = function(destPath, srcPath) {
        destPath = srcPath.replace('src', 'build');
        destPath = destPath.replace('.js', '.min.js');
        return destPath;
    };

    grunt.initConfig({
        exec: {
            decachetheme: {
                cmd: 'php "../../admin/cli/purge_caches.php" --theme && php "../../admin/cli/build_theme_css.php" --themes=boost --direction=ltr',
                callback: function(error) {
                    if (!error) {
                        grunt.log.writeln("Moodle theme cache reset.");
                    }
                }
            },
        },
        sass: {
            dist: {
                files: {
                    "styles.css": "scss/styles.scss"
                }
            },
            options: {
                implementation: sass
            }
        },
        stylelint: {
            scss: {
                options: {
                    syntax: 'scss',

                },
                src: ['scss/styles.scss']
            },
            css: {
                src: ['styles.css'],
                options: {
                    configOverrides: {
                        rules: {
                            // These rules have to be disabled in .stylelintrc for scss compat.
                            "at-rule-no-unknown": true,
                        }
                    }
                }
            }
        },
        watch: {

            options: {
                spawn: false,
                livereload: true
            },
            scss: {
                // Watch for any changes to less files and compile.
                files: ["**/scss/**/*.scss"],
                tasks: ["cssdecache"],
            },
            amd: {
                // If any .js file changes in directory "amd/src" then run the "amd" task.
                files: ["**/amd/src/*.js"],
                tasks: ["amd"]
            },
        },
        uglify: {
            amd: {
                files: [{
                    expand: true,
                    src: ['amd/src/*.js'],
                    rename: uglifyRename
                }],
                options: {report: 'none'}
            }
        },
        eslint: {
            // Setup the local AMD source files.
            amd: {src: 'amd/src/*.js'},
            options: {report: 'none'}
        },

    });

    // Load contrib tasks from Moodle.
//     process.chdir(__dirname + '/../..');
//     grunt.loadNpmTasks("grunt-contrib-less");
//     grunt.loadNpmTasks('grunt-contrib-watch');
//     process.chdir(cwd);

    grunt.loadNpmTasks("grunt-exec");

    // Register tasks.
    grunt.registerTask('amd', ['eslint:amd', 'uglify']);
    grunt.registerTask('css', ['sass']);
    grunt.registerTask('cssdecache', ['css', 'exec:decachetheme']);
    grunt.registerTask('csscheck', ['sass', 'stylelint:scss']);
    grunt.registerTask("default", ["watch"]);

};
