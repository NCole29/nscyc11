## Information

Releases use the following numbering system:
**{major}.{minor}.{incremental}**

* major: Major refactoring or rewrite - make sure you read and test very carefully!
* minor: Breaking change in some circumstances, or a new feature. Read carefully and make sure you understand the impact of the change.
* incremental: A "safe" change / improvement. Should *always* be safe to upgrade.

* **[BC]**: Items marked with [BC] indicate a breaking change that will require updates to your code if you are using that code in your extension.

## Release 3.12.0 (2026-11-20)

* Require CiviCRM 6.20.
* [!386](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/386) Fix [#346](https://lab.civicrm.org/extensions/cividiscount/-/issues/346) discount not applied to the order total on CiviCRM 6.20, so discounted event registrations were recorded at full price.
* [!383](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/383) Remove dead/unreachable code.
* [!385](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/385) Add GitLab CI from infra/extension-ci.
* [!384](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/384) Tests - Fix strict ID failures.
* [!382](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/382) Remove redundant require_once/include_once for CRM classes.
* [!381](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/381) Fix cross-test cache pollution in DiscountCode Civi test suite.

## Release 3.11.1 (2026-08-22)

* [!379](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/379) Add regression test for Membership-status-gated autodiscount eligibility.
* [!378](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/378) - Fix [#329](https://lab.civicrm.org/extensions/cividiscount/-/issues/329) discount code applied when payment recorded for pay later registration.
* [!376](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/376) Fix 403 for Civi admins.
* [!377](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/377) Add composer.json.

## Release 3.11.0 (2026-08-15)

* [!374](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/374) Validate manually entered discount codes against registration context.
* [!373](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/373) Use standalone for tests.
* [!371](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/371) Fix getValidDiscounts() reusing a stale, different code's result within one process.
* [!370](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/370) Add indexes on cividiscount_item.code and is_active.

## Release 3.10.7 (2026-07-27)

* [!369](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/369) Add PHPUnit coverage for DiscountCode.Verify and fix a crash on missing required parameters.
* [!368](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/368) getValidDiscounts: improve the SQL query.

## Release 3.10.6 (2026-06-24)

* Fix [#340](https://lab.civicrm.org/extensions/cividiscount/-/work_items/340) Call to undefined function shift_array().

## Release 3.10.5 (2026-06-15)

* Fix loading discount field.

## Release 3.10.4 (2026-06-08)

* [!366](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/366) Fix discount application for specific price field options
* [!364](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/364) Entirely remove is_display_field_mode
* [!363](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/363) Fix missing addDiscountField
* [!360](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/360) Refactor discount retrieval logic to prioritize autodiscount when code is empty and take only code into account when one is entered
* [!362](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/362) Refactoring DicountCalculator __construct
* [!361](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/361) Refactor discount calculation logic for improved readability and performance
* [!359](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/359) Fix discount message enabled parameter to default to 0 if null

## Release 3.10.3 (2026-05-04)

* [!358](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/358) Fix automatic discount applied to Anonymous user.

## Release 3.10.2 (2026-03-30)

* [!353](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/353) Use Price Set fields as fallback (fixes membership contribution pages not displaying discount code box).

## Release 3.10.1 (2026-03-16)

* [!352](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/352) Fix code discounts in DiscountCode::verify API.

## Release 3.10.0 (2026-02-27)

* [!351](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/351) Upgrade civix to 25.10.2.
* [!350](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/350) Refactor Verify API to use internal cividiscount functions, support autodiscounts etc.
* [!349](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/349) Rename entities to match actual CiviCRM entity names.
* [!348](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/348) Replace legacy membershipPayment code with LineItem.
* [!347](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/347) Move cividiscount_calc_discount to DiscountCalculator class.
* [!346](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/346) Remove unnecessary processDate.
* [!345](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/345) Add frontend_description field.
* [!344](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/344) Remove unused/always true functions.
* [!343](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/343) Code cleanup, remove deprecated functions.
* [!331](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/331) Show discount field for price option when non-applicable discounts for memberships exist.
* [!342](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/342) Replace reference to deprecated exception class.

## Release 3.9.0 (2025-10-06)

* Fix #321 disabling of discounts on Admin form
* Fix discount code permissions
* [#335] Only show enabled codes by default. Add count to pager
* [#336] Add support for membership discounts and return a description that can be used on LineItems etc
* [#337] Make description work for fixed amount discount
* [#328] Avoid considering discounts that don't meet application criteria.
* [#324] Add per-user discount setting
* Fix php warning with json_decode
* Schema - Fix entity name

## Release 3.8.13 (2024-09-22)

* [#322] Resolve "User with 'administer CiviDiscount' permission unable to administer CiviDiscount"

## Release 3.8.12 (2024-05-03)

* [#320] fix crash when event has no price set ID
* [#304] Regenerate DAO files without getReferenceColumns boilerplate
* Fix post process to not attempt to access _params on participant form

## Release 3.8.11 (2024-04-07)

* [#312] exclude event templates
* [#312] add pseudoconstant refs for item cols
* Fixes #310 price field ID discounts treated as event IDs
* Added Api4 Action verify
* Fix discount to use largest amount, for given item
* Fix membership discounts not applying on main contribution page
* Fix for AbstractEditPayment getContributionID not yet public
* [#308] Fix any-event discount
* Support exporting DiscountCodes
* Code cleanup / remove legacy code.
* Fixes #289 Ensure currency is passed to discount function
* Fix error where getPriceSetID is proteced on member form (to fix upstream)
* Reduce apiv3 usage
* dev/core#2727 Fix auto-discounts for event-price set rows
* Update hook_civicrm_permission to use new format

## Release 3.8.10 (2024-01-10)

* Fixes #306, #303 Fix handling of non-membership line items
* Fix handling for register task
* Stop using broken legacy attempt to get contact ID

## Release 3.8.9 (2023-12-22)

* Convert usage page & tabs to SearchKit display.
* Convert Discount List to SearchKit display.
* Update autogenerated code and replace legacy code (min supported version 5.69).

## Release 3.8.8 (2023-07-10)

* [!287](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/287) - Correct discount tracking when multiple memberships
* [!276](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/276) - Fix contact_id assignment from participantId in URL
* [!285](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/285) - Fix broken 'any event' discount code
* [!287](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/287) - Replace JQueryUI icon sprites with Font Awesome
* [!291](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/291) - Don't show discounts on entity types that have no available discounts
* [!296](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/296) - Drop unused columns track and event_id from cividiscount_track
* [!295](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/295) - Move entity declarations to files and add unit test

## Release 3.8.7 (2022-11-28)

* [!281](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/281) - Mega metadata update
* [!280](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/280) - Fix PHP 8 error for Participant Change Selections
* [!278](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/278) - Added intval after += to resolve TypeError

## Release 3.8.6 (2022-09-16)


* [!274](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/274) - Fix [#291](https://lab.civicrm.org/extensions/cividiscount/-/issues/291) date column sorting.
* [!275](https://lab.civicrm.org/extensions/cividiscount/-/merge_requests/275) - For event multiple participants don't require payment details if all priceset amounts are zero (ie. 100% discount).

