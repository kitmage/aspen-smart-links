# Aspen Smart Links

Adds a production-ready WordPress plugin that provides a shortcode button which can apply/remove a FluentCRM tag ID for the current user and then redirect.

Requires: FluentCRM (for tag syncing).

## Shortcode

Internal URL example:

`[crm_tag_button text="Next Lesson" action="add" tag_id="12" url="/lesson-2/"]`

- Updates the user's tag list
- Redirects the current tab to `/lesson-2/`

External URL example:

`[crm_tag_button text="Open Resource" action="add" tag_id="12" url="https://google.com"]`

- Opens `https://google.com` in a new tab (best effort)
- Processes the tag action in the current tab
- Redirects back to the current page

## Notes

- Buttons only render for logged-in users.
- External URL opening depends on browser popup policy. The plugin triggers `window.open()` from the form submit event, which is typically allowed.
- FluentCRM is the primary tag store (by contact email). Optionally, the plugin can also store tag IDs in user meta under `aspen_smart_links_tags`.

## Hooks

- Filter `aspen_smart_links_handle_tag_action`: Return `true`/`false` to fully handle the tag action, or `null` to fall back to the default behavior.
- Filter `aspen_smart_links_enable_fluentcrm`: Return `false` to disable FluentCRM syncing.
- Filter `aspen_smart_links_fluentcrm_create_if_missing`: Return `false` to avoid creating a FluentCRM contact when missing (default: true).
- Filter `aspen_smart_links_store_user_meta`: Return `true` to store tag IDs in user meta.
- Action `aspen_smart_links_tag_action`: Fired after processing.
