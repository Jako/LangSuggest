# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2026-11-11

### Changed

- Refactor the LangSuggest class and the OnWebPagePrerender plugin event
- The sample chunks use plain JavaScript and the modal chunk uses a native dialog element

### Fixed

- Browser language detection (q factors, regional variants)
- Context map with untrimmed or semicolon separated babel.contextKeys
- Cookie path, cookie name and jQuery dependency of the sample chunks
- Display count was decreased, when LangSuggest was not shown
- langsuggestActive body class handling
- tplLangSuggestBar chunk, lexicons and documentation

## [1.1.0] - 2021-06-25

### Added

- chunk_position system setting
- tplLangSuggestBar and tplLangSuggestBarOption demo chunks
- The body tag gets a langsuggestActive class, when a language is suggested

## [1.0.0] - 2019-03-11

### Added

- Initial release
