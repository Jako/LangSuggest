const gulp = require('gulp'),
    replace = require('gulp-replace'),
    pkg = require('./_build/config.json');

gulp.task('bump-copyright', function () {
    return gulp.src([
        'core/components/langsuggest/model/langsuggest/langsuggest.class.php',
        'core/components/langsuggest/src/LangSuggest.php',
    ], {base: './'})
        .pipe(replace(/Copyright 2019(-\d{4})? by/g, 'Copyright ' + (new Date().getFullYear() > 2019 ? '2019-' : '') + new Date().getFullYear() + ' by'))
        .pipe(gulp.dest('.'));
});
gulp.task('bump-version', function () {
    return gulp.src([
        'core/components/langsuggest/src/LangSuggest.php',
    ], {base: './'})
        .pipe(replace(/version = '\d+.\d+.\d+[-a-z0-9]*'/ig, 'version = \'' + pkg.version + '\''))
        .pipe(gulp.dest('.'));
});
gulp.task('bump-docs', function () {
    return gulp.src([
        'mkdocs.yml',
    ], {base: './'})
        .pipe(replace(/&copy; 2019(-\d{4})?/g, '&copy; ' + (new Date().getFullYear() > 2019 ? '2019-' : '') + new Date().getFullYear()))
        .pipe(gulp.dest('.'));
});
gulp.task('bump', gulp.series('bump-copyright', 'bump-version', 'bump-docs'));

// Default Task
gulp.task('default', gulp.series('bump'));