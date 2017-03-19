'use strict';

var child_process = require('child_process');
var fs = require('fs');
var readline = require('readline');

var Promise = require('bluebird');
var dateFormat = require('dateformat');
var glob = require('glob');
var minimist = require('minimist');
var rimraf = require('rimraf');
var tmp = require('tmp');

var gulp = require('gulp');
var replace = require('gulp-replace');
var rename = require('gulp-rename');
var zip = require('gulp-zip');

// var sass = require('gulp-sass');
// var postcss = require('gulp-postcss');
var autoprefixer = require('autoprefixer');

var composerDir = __dirname + '/vendor/bin';
var buildDir = __dirname + '/build';
var dataDir = __dirname + '/application/data';
var scriptsDir = dataDir + '/scripts';
var langDir = __dirname + '/application/language';

var cliOptions = minimist(process.argv.slice(2), {
    string: 'php-path',
    boolean: 'dev',
    default: {'php-path': 'php', 'dev': true}
});

function ensureBuildDir() {
    if (!fs.existsSync(buildDir)) {
        fs.mkdirSync(buildDir);
    }

    if (!fs.existsSync(buildDir + '/cache')) {
        fs.mkdirSync(buildDir + '/cache');
    }
}

function download(url, path) {
    return new Promise(function (resolve, reject) {
        ensureBuildDir();
        var https = require('https');
        var file = fs.createWriteStream(path);
        file.on('finish', function () {
            file.close(resolve());
        });
        https.get(url, function (response) {
            response.pipe(file);
        }).on('error', function(err) {
            reject(err);
        });
    });
}

function runCommand(cmd, args, options) {
    return new Promise(function (resolve, reject) {
        if (!options) {
            options = {};
        }
        if (!options.stdio) {
            options.stdio = 'inherit';
        }
        child_process.spawn(cmd, args, options)
            .on('exit', function (code) {
                if (code !== 0) {
                    reject(new Error('Command "' + cmd + '" exited with code ' +  code));
                } else {
                    resolve();
                }
            });
    });
}

function runPhpCommand(cmd, args, options) {
    return runCommand(cliOptions['php-path'], [cmd].concat(args), options);
}

function composer(args) {
    var composerPath = buildDir + '/composer.phar';
    var installerPath = buildDir + '/composer-installer';
    var installerUrl = 'https://getcomposer.org/installer';
    return new Promise(function (resolve, reject) {
        fs.stat(composerPath, function(err, stats) {
            if (!err) {
                resolve();
            } else {
                download(installerUrl, installerPath)
                    .then(function () {
                        return runPhpCommand(installerPath, [], {cwd: buildDir})
                    })
                    .then(function () {
                        resolve();
                    });
            }
        });
    })
    .then(function () {
        return runPhpCommand(composerPath, ['self-update']);
    })
    .then(function () {
        if (!cliOptions['dev']) {
            args.push('--no-dev');
        }
        return runPhpCommand(composerPath, args);
    });
}

// gulp.task('css', function () {
//     return gulp.src('./application/asset/sass/*.scss')
//         .pipe(sass({
//             outputStyle: 'compressed',
//             includePaths: ['node_modules/susy/sass']
//         }).on('error', sass.logError))
//         .pipe(postcss([
//             autoprefixer({browsers: ['> 5%', '> 5% in US', 'last 2 versions']})
//         ]))
//         .pipe(gulp.dest('./application/asset/css'));
// });
//
// gulp.task('css:watch', function () {
//     gulp.watch('./application/asset/sass/*.scss', gulp.parallel('css'));
// });

gulp.task('test:cs', function () {
    ensureBuildDir();
    return runCommand('vendor/bin/php-cs-fixer', ['fix', '--dry-run', '--verbose', '--diff', '--cache-file=build/cache/.php_cs.cache']);
});
gulp.task('test:php', function () {
    ensureBuildDir();
    return runCommand(composerDir + '/phpunit', [
        '-d',
        'date.timezone=America/New_York',
        '--log-junit',
        buildDir + '/test-results.xml'
    ], {cwd: 'application/test'});
});
gulp.task('test', gulp.series('test:cs', 'test:php'));

gulp.task('deps', function () {
    return composer(['install']);
});
gulp.task('deps:update', function () {
    return composer(['update']);
});

// gulp.task('dedist', function () {
//     return gulp.src(['./.htaccess.dist', './config/*.dist', './logs/*.dist', './application/test/config/*.dist'], {base: '.'})
//         .pipe(rename(function (path) {
//             path.extname = '';
//         }))
//         .pipe(gulp.dest('.', {overwrite: false}))
// });

gulp.task('init', gulp.series(/* 'dedist',  */'deps'));

gulp.task('clean', function (cb) {
    rimraf.sync(buildDir);
    rimraf.sync(__dirname + '/vendor');
    cb();
});

gulp.task('zip', gulp.series('clean', 'init', function () {
    return gulp.src(['./**', '!./**/*.dist', '!./build/**', '!./**/node_modules/**', '!./**/.git/**', '!./**/.gitattributes', '!./**/.gitignore'],
        {base: '.'})
        .pipe(rename(function (path) {
            path.dirname = 'IiifServer/' + path.dirname;
        }))
        .pipe(zip('IiifServer.zip'))
        .pipe(gulp.dest(buildDir))
}));
