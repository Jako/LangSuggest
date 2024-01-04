const gulp = require('gulp'),
    replace = require('gulp-replace'),
    pkg = require('./_build/config.json');

const year = new Date().getFullYear();

let phpversion;
let modxversion;
pkg.dependencies.forEach(function (dependency, index) {
    switch (pkg.dependencies[index].name) {
        case 'php':
            phpversion = pkg.dependencies[index].version.replace(/>=/, '');
            break;
        case 'modx':
            modxversion = pkg.dependencies[index].version.replace(/>=/, '');
            break;
    }
});

const bumpCopyright = function () {
    return gulp.src([
        'core/components/langsuggest/model/langsuggest/langsuggest.class.php',
        'core/components/langsuggest/src/LangSuggest.php',
    ], {base: './'})
        .pipe(replace(/Copyright 2019(-\d{4})? by/g, 'Copyright ' + (year > 2019 ? '2019-' : '') + year + ' by'))
        .pipe(gulp.dest('.'));
};
const bumpVersion = function () {
    return gulp.src([
        'core/components/langsuggest/src/LangSuggest.php',
    ], {base: './'})
        .pipe(replace(/version = '\d+\.\d+\.\d+[-a-z0-9]*'/ig, 'version = \'' + pkg.version + '\''))
        .pipe(gulp.dest('.'));
};
const bumpDocs = function () {
    return gulp.src([
        'mkdocs.yml',
    ], {base: './'})
        .pipe(replace(/&copy; 2019(-\d{4})?/g, '&copy; ' + (year > 2019 ? '2019-' : '') + year))
        .pipe(gulp.dest('.'));
};
const bumpRequirements = function () {
    return gulp.src([
        'docs/index.md',
    ], {base: './'})
        .pipe(replace(/[*-] MODX Revolution \d.\d.*/g, '* MODX Revolution ' + modxversion + '+'))
        .pipe(replace(/[*-] PHP (v)?\d.\d.*/g, '* PHP ' + phpversion + '+'))
        .pipe(gulp.dest('.'));
};
gulp.task('bump', gulp.series(bumpCopyright, bumpVersion, bumpDocs, bumpRequirements));

// Default Task
gulp.task('default', gulp.series('bump'));