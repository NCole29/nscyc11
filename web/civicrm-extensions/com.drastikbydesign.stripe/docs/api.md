# API

This extension comes with several APIs to help you troubleshoot problems. These can be run via /civicrm/api or via `cv api Stripe.xxx`.

The api commands are:

## Stripe

### Import related.

These were moved to the [StripeImport](https://lab.civicrm.org/extensions/stripeimport) extension.

## StripeCustomer (API3)

* `StripeCustomer.get` - Fetch a customer by passing either civicrm contact id or stripe customer id.
* `StripeCustomer.create` - Create a customer by passing a civicrm contact id.
* `StripeCustomer.delete` - Delete a customer by passing either civicrm contact id or stripe customer id.
* `StripeCustomer.updatecontactids` - Used to migrate civicrm_stripe_customer table to match on contact_id instead of email address.
* `StripeCustomer.updatestripemetadata` - Used to update stripe customers that were created using an older version of the extension (adds name to description and contact_id as a metadata field).
* `StripeCustomer.membershipcheck` - Used to look for potential problems and inconsistencies between Stripe and CiviCRM. Does not make any changes.

## StripePaymentintents (API3)

#### `StripePaymentintents.get`
It can be used for debugging and querying information about attempted / successful payments.

#### `StripePaymentintents.create`
This API is used internally for tracking and managing paymentIntents.
It's not advised that you use this API for anything else.

#### `StripePaymentintents.Process`
This API is used by the client javascript integration and by third-party frontend integrations.
Please contact [MJW Consulting](https://mjw.pt/stripe) if you require more information or are planning to use this API.

Permissions: `access Ajax API` + `make online contributions`

#### `StripePaymentintents.createorupdate`
This API is used by the client javascript integration to create or update the `civicrm_stripe_paymentintent` table.

Permissions: `access Ajax API` + `make online contributions`

## Scheduled Jobs

* `Job.process_stripe` - this cancels uncaptured paymentIntents and removes successful ones from the local database cache after a period of time:

  * Parameters:
    * delete_old: Delete old records from database. Specify 0 to disable. Default is "-3 month"
    * cancel_incomplete: Cancel incomplete paymentIntents in your stripe account. Specify 0 to disable. Default is "-1 hour"

## StripeWebhook (API4)

#### Create

Creates a new webhook for the specified payment processor.

#### Delete

Deletes all disabled webhooks for the specified payment processor.

#### GetFromStripe

Gets all defined webhooks for the specified payment processor.

#### Update

Creates or Updates a webhook for the specified payment processor.

This API matches any existing webhooks that have the same URL as the selected payment processor.

It will disable any that do not match the current expected configuration or if there is more than one with matching configuration enabled.

If necessary it will create a new webhook with the current configuration.


### Legacy API3 endpoints (not supported)

#### `Stripe.Listevents`:

Events are the notifications that Stripe sends to the Webhook. Listevents will list all notifications that have been sent. You can further restrict them with the following parameters:

    * `ppid` - Use the given Payment Processor ID. By default, uses the saved, live Stripe payment processor and throws an error if there is more than one.
    * `type` - Limit to the given Stripe events type. By default, show invoice.payment_succeeded. Change to 'all' to show all.
    * `output` - What information to show. Defaults to 'brief' which provides a summary. Alternatively use raw to get the raw JSON returned by Stripe.
    * `limit` - Limit number of results returned (100 is max, 10 is default).
    * `starting_after` - Only return results after this event id. This can be used for paging purposes - if you want to retreive more than 100 results.
    * `source` - By default, source is set to "stripe" and limited to events reported by Stripe in the last 30 days. If instead you specify "systemlog" you can query the `civicrm_system_log` table for events, which potentially go back farther then 30 days.
    * `subscription` - If you specify a subscription id, results will be limited to events tied to the given subscription id. Furthermore, both the `civicrm_system_log` table will be queried and the results will be supplemented by a list of expected charges based on querying Stripe, allowing you to easily find missing charges for a given subscription.
    * `filter_processed` - Set to 1 if you want to filter out results for contributions that have been properly processed by CiviCRM already.

#### `Stripe.Populatelog`:

This API call will populate your SystemLog with all of your past Stripe Events. You can safely re-run and not create duplicates. With a populated SystemLog - you can selectively replay events that may have caused errors the first time or otherwise not been properly recorded. Parameters:

    * `ppid` - Use the given Payment Processor ID. By default, uses the saved, live Stripe payment processor and throws an error if there is more than one.
    * `type` - The event type - defaults to invoice.payment_succeeded.

The standard API3 "limit" option is also supported and if specified will limit the total number of events to that limit (default 0).

#### `Stripe.Populatewebhookqueue`:

    * `ppid` - Use the given Payment Processor ID. By default, uses the saved, live Stripe payment processor and throws an error if there is more than one.
    * `type` - The event type - defaults to invoice.payment_succeeded.

The standard API3 "limit" option is also supported and if specified will limit the total number of events to that limit (default 0).

#### `Stripe.Ipn`:

Replay a given Stripe Event. Parameters. This will always fetch the chosen Event from Stripe before replaying.

    * `id` - The id from the SystemLog of the event to replay.
    * `evtid` - The Event ID as provided by Stripe.
    * `ppid` - Use the given Payment Processor ID. By default, uses the saved, live Stripe payment processor and throws an error if there is more than one.
    * `noreceipt` - Set to 1 if you want to suppress the generation of receipts or set to 0 or leave out to send receipts normally.

#### `Stripe.Retryall`:

Attempt to replay all charges for a given payment processor that are completed in Stripe but not completed in CiviCRM.

    * `ppid` - Use the given Payment Processor ID. By default, uses the saved, live Stripe payment processor and throws an error if there is more than one.
    * `limit` - Limit number of results (25 is default).
