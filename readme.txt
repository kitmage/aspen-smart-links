=== Aspen Smart Links ===
Contributors: aspen
Tags: shortcode, links, tracking
Requires at least: 6.0
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Shortcode buttons that apply/remove FluentCRM tags and then redirect (with an optional external new-tab open).

== Description ==

Aspen Smart Links adds the [crm_tag_button] shortcode.

Requires: FluentCRM (for tag syncing).

Features:

* Secure admin-post handler with nonces.
* Internal URLs redirect in the same tab.
* External URLs open in a new tab (best effort) while the current tab processes the tag action and reloads.
* Applies/removes FluentCRM tags for the current user (by email).
* Optional: stores tag IDs in the current user's meta (aspen_smart_links_tags) for fallback/auditing.

== Usage ==

Internal URL example:

[crm_tag_button text="Next Lesson" action="add" tag_id="12" url="/lesson-2/"]

External URL example:

[crm_tag_button text="Open Resource" action="add" tag_id="12" url="https://google.com"]

Shortcode attributes:

* text - Button label.
* action - add or remove.
* tag_id - Numeric tag ID.
* url - Internal path/URL or external URL.
* class - Space-separated CSS classes applied to the button.

== Hooks ==

Filter: aspen_smart_links_handle_tag_action

* Return true/false to fully handle the tag action.
* Return null to fall back to the default behavior (FluentCRM tag action + optional user meta fallback).

Filter: aspen_smart_links_enable_fluentcrm

* Return false to disable FluentCRM syncing.

Filter: aspen_smart_links_fluentcrm_create_if_missing

* Return false to avoid creating a FluentCRM contact when missing (default: true).

Filter: aspen_smart_links_store_user_meta

* Return true to store tag IDs in user meta (default: only when FluentCRM is not available).

Action: aspen_smart_links_tag_action

* Fires after a tag action is processed.

== Uninstall ==

Deletes the aspen_smart_links_tags user meta key.
