How It Works
Internal URL Example
[crm_tag_button text="Next Lesson" action="add" tag_id="12" url="/lesson-2/"]
Result:
Add tag
Current tab goes to /lesson-2/
External URL Example
[crm_tag_button text="Open Resource" action="add" tag_id="12" url="https://google.com"]
Result:
New tab opens https://google.com
Current tab submits form
Tag added
Current page reloads
Important Browser Note

Browsers allow window.open() here because it happens directly from a click/submit event.

If popup blockers are aggressive, some browsers may block it—but usually this pattern works.

Recommended Improvement

Use a loading state so users don’t double-click:

button.disabled = true;
button.innerText = 'Loading...';

I can add that next if you'd like.

Even Better Version (Recommended)

I can also upgrade this to:

Smart UX Version

For external links:

opens new tab
button changes to “Opened”
current page refreshes cleanly
prevents double clicks
works with Elementor buttons
optional icon
