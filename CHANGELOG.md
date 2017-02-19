# Change Log
All notable changes to this project will be documented in this file.
This project adheres to [Semantic Versioning](http://semver.org/).

## [4.3.5] - 2017-02-19
### Added
- Beta support for Sandstorm.IO
- Docker support by [@schoentoon](https://github.com/schoentoon), [@elohmeier](https://github.com/elohmeier), [@patrickkostjens](https://github.com/patrickkostjens) and [@crash7](https://github.com/crash7)!

### Changed
- Updated to laravel 5.4!
- User friendly error message
- Updated locales to support more operating systems, first reported in #536 by [dabenzel](https://github.com/dabenzel)
- Updated budget report
- Improved 404 page
- Smooth curves, improved by [elamperti](https://github.com/elamperti).

### Fixed
- #549
- #553
- Fixed #559 reported by [elamperti](https://github.com/elamperti).
- #565, as reported by a user over the mail
- #566, as reported by [dspeckmann](https://github.com/dspeckmann)
- #567, as reported by [winsomniak](https://github.com/winsomniak)
- #569, as reported by [winsomniak](https://github.com/winsomniak)
- #572, as reported by [zjean](https://github.com/zjean)
- Many issues with the transaction filters which will fix reports (they tended to display the wrong amount).

## [4.3.4] - 2017-02-02
### Fixed
- Fixed bug #550, reported by [worldworm](https://github.com/worldworm)!
- Fixed bug #551, reported by [t-me](https://github.com/t-me)!

## [4.3.3] - 2017-01-30

_The 100th release of Firefly!_

### Added
- Add locales to Docker (#534) by [elohmeier](https://github.com/elohmeier).
- Optional database encryption. On by default.
- Datepicker for Firefox and other browsers.
- New instruction block for updating and installing.
- Ability to clone transactions.
- Use multi-select Bootstrap thing instead of massive lists of checkboxes.

### Removed
- Lots of old Javascript

### Fixed
- Missing sort broke various charts
- Bug in reports that made amounts behave weird
- Various bug fixes

### Security
- Tested FF against the naughty string list.

## [4.3.2] - 2017-01-09

An intermediate release because something in the Twig and Twigbridge libraries is broken and I have to make sure it doesn't affect you guys. But some cool features were on their way so there's that oo.

### Added
- Some code for issue #475, consistent overviews.
- Better currency display. Make sure you have locale packages installed.

### Changed
- Uses a new version of Laravel.

### Fixed
- The password reset routine was broken.
- Issue #522, thanks to [xpfgsyb](https://github.com/xpfgsyb)
- Issue #524, thanks to [worldworm](https://github.com/worldworm)
- Issue #526, thanks to [worldworm](https://github.com/worldworm)
- Issue #528, thanks to [skibbipl](https://github.com/skibbipl)
- Various other fixes.

## [4.3.1] - 2017-01-04
### Added
- Support for Russian and Polish. 
- Support for a proper demo website.
- Support for custom decimal places in currencies (#506, suggested by [xpfgsyb](https://github.com/xpfgsyb)).
- Most amounts are now right-aligned (#511, suggested by [xpfgsyb](https://github.com/xpfgsyb)).
- German is now a "complete" language, more than 75% translated!

### Changed
- **[New Github repository!](github.com/firefly-iii/firefly-iii)**
- Better category overview.
- #502, thanks to [zjean](https://github.com/zjean)

### Removed
- Removed a lot of administration functions.
- Removed ability to activate users.

### Fixed
- #501, thanks to [zjean](https://github.com/zjean)
- #513, thanks to [skibbipl](https://github.com/skibbipl) 

### Security
- #519, thanks to [xpfgsyb](https://github.com/xpfgsyb)

## [4.3.0] - 2015-12-26
### Added
- New method of keeping track of available budget, see issue #489
- Support for Spanish
- Firefly III now has an extended demo mode. Will expand further in the future.
 

### Changed
- New favicon
- Import routine no longer gives transactions a description #483


### Removed
- All test data generation code.

### Fixed
- Removed import accounts from search results #478
- Redirect after delete will no longer go back to deleted item #477
- Cannot math #482
- Fixed bug in virtual balance field #479

## [4.2.2] - 2016-12-18
### Added
- New budget report (still a bit of a beta)
- Can now edit user

### Changed
- New config for specific events. Still need to build Notifications.

### Fixed
- Various bugs
- Issue #472 thanks to [zjean](https://github.com/zjean)

## [4.2.1] - 2016-12-09
### Added
- BIC support (see #430)
- New category report section and chart (see the general financial report)


### Changed
- Date range picker now also available on mobile devices (see #435)
- Extended range of amounts for issue #439
- Rewrote all routes. Old bookmarks may break.

## [4.2.0] - 2016-11-27
### Added
- Lots of (empty) tests
- Expanded transaction lists (#377)
- New charts at account view
- First code for #305


### Changed
- Updated all email messages.
- Made some fonts local


### Deprecated
- Initial release.

### Removed
- Initial release.

### Fixed
- Issue #408
- Various issues with split journals
- Issue #414, thx [zjean](https://github.com/zjean)
- Issue #419, thx [schwalberich](https://github.com/schwalberich) 
- Issue #422, thx [xzaz](https://github.com/xzaz)
- Various import bugs, such as #416 ([zjean](https://github.com/zjean))


### Security
- Initial release.


## [4.1.7] - 2016-11-19
### Added
- Check for database table presence in console commands.
- Category report
- Reinstated old test routines.


### Changed
- Confirm account setting is no longer in `.env` file.
- Titles are now in reverse (current page > parent > firefly iii)
- Easier update of language files thanks to Github implementation.
- Uniform colours for charts.

### Fixed
- Made all pages more mobile friendly.
- Fixed #395 found by [marcoveeneman](https://github.com/marcoveeneman).
- Fixed #398 found by [marcoveeneman](https://github.com/marcoveeneman).
- Fixed #401 found by [marcoveeneman](https://github.com/marcoveeneman).
- Many optimizations.
- Updated many libraries.
- Various bugs found by myself.


## [4.1.6] - 2016-11-06
### Added
- New budget table for multi year report.

### Changed
- Greatly expanded help pages and their function.
- Built a new transaction collector, which I think was the idea of [roberthorlings](https://github.com/roberthorlings) originally.
- Rebuilt seach engine.

### Fixed
- #375, thanks to [schoentoon](https://github.com/schoentoon) which made it impossible to resurrect currencies.
- #370 thanks to [ksmolder](https://github.com/ksmolder)
- #378, thanks to [HomelessAvatar](https://github.com/HomelessAvatar)

## [4.1.5] - 2016-11-01
### Changed
- Report parts are loaded using AJAX, making a lot of code more simple.
- Help content will fall back to English.
- Help content is translated through Crowdin.

### Fixed
- Issue #370

## [4.1.4] - 2016-10-30
### Added
- New Dockerfile thanks to [schoentoon](https://github.com/schoentoon)
- Added changing the destination account as rule action.
- Added changing the source account as rule action.
- Can convert transactions into different types.

### Changed
- Changed the export routine to be more future-proof.
- Improved help routine.
- Integrated CrowdIn translations.
- Simplified reports
- Change error message to refer to solution.

### Fixed
- #367 thanks to [HungryFeline](https://github.com/HungryFeline)
- #366 thanks to [3mz3t](https://github.com/3mz3t)
- #362 and #341 thanks to [bnw](https://github.com/bnw)
- #355 thanks to [roberthorlings](https://github.com/roberthorlings)

## [4.1.3] - 2016-10-22
### Fixed
- Some event handlers called the wrong method.

## [4.1.2] - 2016-10-22

### Fixed
- A bug is fixed in the journal event handler that prevented Firefly III from actually storing journals.

## [4.1.1] - 2016-10-22

### Added
- Option to show deposit accounts on the front page.
- Script to upgrade split transactions
- Can now save notes on piggy banks.
- Extend user admin options.
- Run import jobs from the command line


### Changed
- New preferences screen layout.

### Deprecated
- ``firefly:import`` is now ``firefly:start-import``

### Removed
- Lots of old code

### Fixed
- #357, where non utf-8 files would break Firefly.
- Tab delimiter is not properly loaded from import configuration ([roberthorlings](https://github.com/roberthorlings))
- System response to yearly bills

## [4.0.2] - 2016-10-14
### Added
- Added ``intl`` dependency to composer file to ease installation (thanks [telyn](https://github.com/telyn))
- Added support for Croatian.

### Changed
- Updated all copyright notices to refer to the [Creative Commons Attribution-ShareAlike 4.0 International License](https://creativecommons.org/licenses/by-sa/4.0/)
- Fixed #344
- Fixed #346, thanks to [SanderKleykens](https://github.com/SanderKleykens)
- #351
- Did some internal remodelling.

### Fixed
- PostgreSQL compatibility thanks to [SanderKleykens](https://github.com/SanderKleykens)
- [roberthorlings](https://github.com/roberthorlings) fixed a bug in the ABN Amro import specific.


## [4.0.1] - 2016-10-04
### Added
- New ING import specific by [tomwerf](https://github.com/tomwerf)
- New Presidents Choice specific to fix #307
- Added some trimming (#335)

### Changed
- Initial release.

### Deprecated
- Initial release.

### Removed
- Initial release.

### Fixed
- Fixed a bug where incoming transactions would not be properly filtered in several reports.
- #334 by [cyberkov](https://github.com/cyberkov)
- #337
- #336
- #338 found by [roberthorlings](https://github.com/roberthorlings)

### Security
- Initial release.




## [4.0.0] - 2015-09-26
### Added
- Upgraded to Laravel 5.3, most other libraries upgraded as well.
- Added GBP as currency, thanks to [Mortalife](https://github.com/Mortalife)

### Changed
- Jump to version 4.0.0.
- Firefly III is now subject to a [Creative Commons Attribution-ShareAlike 4.0 International License](https://creativecommons.org/licenses/by-sa/4.0/) license. Previous versions of this software are still MIT licensed.

### Fixed
- Support for specific decimal places, thanks to [Mortalife](https://github.com/Mortalife)
- Various CSS fixes
- Various bugs, thanks to [fuf](https://github.com/fuf), [sandermulders](https://github.com/sandermulders) and [vissert](https://github.com/vissert)
- Various queries optimized for MySQL 5.7

## [3.10.4] - 2015-09-14
### Fixed
- Migration fix by [sandermulders](https://github.com/sandermulders)
- Tricky import bug fix thanks to [vissert](https://github.com/vissert)
- Currency preference will be correctly pulled from user settings, thanks to [fuf](https://github.com/fuf)
- Simplified code for upgrade instructions.


## [3.10.3] - 2016-08-29
### Added
- More fields for mass-edit, thanks to [vissert](https://github.com/vissert) (#282)
- First start of German translation

### Changed
- More optional fields for transactions and the ability to filter them.

### Removed
- Preference for budget maximum.

### Fixed
- A bug in the translation routine broke the import.
- It was possible to destroy your Firefly installation by removing all currencies. Thanks [mondjef](https://github.com/mondjef)
- Translation bugs.
- Import bug.

### Security
- Firefly will not accept registrations beyond the first one, by default.


## [3.10.2] - 2016-08-29
### Added
- New Chinese translations. Set Firefly III to show incomplete translations to follow the progress. Want to translate Firefly III in Chinese, or in any other language? Then check out [the Crowdin project](https://crowdin.com/project/firefly-iii).
- Added more admin pages. They do nothing yet.

### Changed
- Import routine will now also apply user rules.
- Various code cleanup.
- Some small HTML changes.

### Fixed
- Bug in the mass edit routines.
- Firefly III over a proxy will now work (see [issue #290](https://github.com/firefly-iii/firefly-iii/issues/290)), thanks [dfiel](https://github.com/dfiel) for reporting.
- Sneaky bug in the import routine, fixed by [Bonno](https://github.com/Bonno) 

## [3.10.1] - 2016-08-25
### Added
- More feedback in the import procedure.
- Extended model for import job.
- Web bases import procedure.


### Changed
- Scrutinizer configuration
- Various code clean up.

### Removed
- Code climate YAML file.

### Fixed
- Fixed a bug where a migration would check an empty table name.
- Fixed various bugs in the import routine.
- Fixed various bugs in the piggy banks pages.
- Fixed a bug in the `firefly:verify` routine

## [3.10] - 2015-05-25
### Added
- New charts in year report
- Can add / remove money from piggy bank on mobile device.
- Bill overview shows some useful things.
- Firefly will track registration / activation IP addresses.


### Changed
- Rewrote the import routine.
- The date picker now supports more ranges and periods.
- Rewrote all migrations. #272

### Fixed
- Issue #264
- Issue #265
- Fixed amount calculation problems, #266, thanks [xzaz](https://github.com/xzaz)
- Issue #271
- Issue #278, #273, thanks [StevenReitsma](https://github.com/StevenReitsma) and [rubella](https://github.com/rubella)
- Bug in attachment download routine would report the wrong size to the user's browser.
- Various NULL errors fixed.
- Various strict typing errors fixed.
- Fixed pagination problems, #276, thanks [xzaz](https://github.com/xzaz)
- Fixed a bug where an expense would be assigned to a piggy bank if you created a transfer first.
- Bulk update problems, #280, thanks [stickgrinder](https://github.com/stickgrinder)
- Fixed various problems with amount reporting of split transactions.

## [3.9.1]
### Fixed
- Fixed a bug where removing money from a piggy bank would not work. See issue #265 and #269

## [3.9.0]
### Added
- [zjean](https://github.com/zjean) has added code that allows you to force "https://"-URL's.
- [tonicospinelli](https://github.com/tonicospinelli) has added Portuguese (Brazil) translations.
- Firefly III supports the *splitting* of transactions:
  - A withdrawal (expense) can be split into multiple sub-transactions (with multiple destinations)
  - Likewise for deposits (incomes). You can set multiple sources.
  - Likewise for transfers.

### Changed
- Update a lot of libraries.
- Big improvement to test data generation.
- Cleaned up many repositories.

### Removed
- Front page boxes will no longer respond to credit card bills.

### Fixed
- Many bugs

## [3.8.4] - 2016-04-24
### Added
- Lots of new translations.
- Can now set page size.
- Can now mass edit transactions.
- Can now mass delete transactions.
- Firefly will now attempt to verify the integrity of your database when updating.

### Changed
- New version of Charts library.

### Fixed
- Several CSV related bugs.
- Several other bugs.
- Bugs fixed by [Bonno](https://github.com/Bonno).

## [3.8.3] - 2016-04-17
### Added
- New audit report to see what happened.

### Changed
- New Chart JS release used.
- Help function is more reliable.

### Fixed
- Expected bill amount is now correct.
- Upgrade will now invalidate cache.
- Search was broken.
- Queries run better

## [3.8.2] - 2016-04-03
### Added
- Small user administration at /admin.
- Informational popups are working in reports.

### Changed
- User activation emails are better

### Fixed
- Some bugs related to accounts and rules.


## [3.8.1] - 2016-03-29
### Added
- More translations
- Extended cookie control.
- User accounts can now be activated (disabled by default).
- Bills can now take the source and destination account name into account.

### Changed
- The pages related to rules have new URL's.

### Fixed
- Spelling errors.
- Problems related to the "account repository".
- Some views showed empty (0.0) amounts.

## [3.8.0] - 2016-03-20
### Added
- Two factor authentication, thanks to the excellent work of [zjean](https://github.com/zjean).
- A new chart showing your net worth in year and multi-year reports.
- You can now see if your current or future rules actually match any transactions, thanks to the excellent work of [roberthorlings](https://github.com/roberthorlings).
- New date fields for transactions. They are not used yet in reports or anything, but they can be filled in.
- New routine to export your data.
- Firefly III will mail the site owner when blocked users try to login, or when blocked domains are used in registrations.


### Changed
- Firefly III now requires PHP 7.0 minimum.


### Fixed
- HTML fixes, thanks to [roberthorlings](https://github.com/roberthorlings) and [zjean](https://github.com/zjean)..
- A bug fix in the ABN Amro importer, thanks to [roberthorlings](https://github.com/roberthorlings)
- It was not possible to change the opening balance, once it had been set. Thanks to [xnyhps](https://github.com/xnyhps) and [marcoveeneman](https://github.com/marcoveeneman) for spotting this.
- Various other bug fixes.



## [3.4.2] - 2015-05-25
### Added
- Initial release.

### Changed
- Initial release.

### Deprecated
- Initial release.

### Removed
- Initial release.

### Fixed
- Initial release.

### Security
- Initial release.
