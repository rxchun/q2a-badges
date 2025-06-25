<a href="https://www.paypal.com/paypalme/chun128" target="_blank"/>
<img src="https://i.ibb.co/Rz9rfk4/bmc-button.png" border="0" alt="Donate with PayPal!"/>
<a/>

# Q2A Badges Changelog

## [5.2.38] - 2025-06-25

 - Widget - badge url fix.

---

## [5.2.37] - 2025-06-21

### Widget Batch Fetching + User Account Data Caching

 1. Collect all user handles into an array while looping through badges.
 2. Batch fetch all user accounts at once via `fetch_user_accounts()`
 3. Cache user data in `$userAccounts` to avoid repeated database calls.

### Badge Details Caching

 - Added `$badgeCache` to cache badge details by slug.
 - Avoid repeated option/database lookups for badges already processed.

### Other

 - Refactor: Moved `badge-utils.php` to `inc` folder for better organization.
 - Reuse functions from `badge-utils.php` for the widget, instead of redeclaring them:
   - `get_user_avatar($handle, $size)`
   - `generate_avatar_html($handle, $avatarUrl, $size)`
 - Updated default user avatar to a smaller `.svg` image.
 
---

## [5.2.36] - 2025-06-20

 - Abstract fetch endpoint away from HTML.

---

## [5.2.35] - 2025-06-16

### Smarter database requests
Instead of sending separate requests for each user one by one, they're now grouped into a single batch fetch. This reduces the overhead of repeated network/database round trips.  

### Lazy loading for badges
This one has been in the to-do list for a while now, as I've noticed websites with large amounts of badges suffering from this.

Badges page:

The list of users who earned badges now only loads when clicked, and as you scroll down the pop-up, more content is loaded dynamically, making the page faster and easier to navigate.

After closing a pop-up, the list is destroyed to minimize nodes, while maintaining good performance.

Once a list of badges is fully loaded, meaning the scroll has reached the end, it will save the list temporarily in memory, so it wont fetch the same list again, in case you reopen it for that page visit.

### Better code organization
Organized files and created a “utils” file that holds common functions used throughout the site, making future updates simpler and more modular.

### Fresh new look for the Badges pop-up
The pop-up box on the Badges page has been redesigned for a cleaner, more user-friendly experience.

---

## [5.2.33] - 2025-03-04

- Switched widget timestap to CORE lang.

---

## [5.2.32] - 2025-11-19

- Structure fix for "Users List" page.

---

## [5.2.31] - 2024-10-28

- UX Adjustments.

---

## [5.2.3] - 2024-06-21

- Moved `qa-badge-lang-default.php` file to `lang` folder.

---

## [5.2.2]

- Swapped JS algorithm to group badges by type, to a PHP solution.  
  (To reduce Javascript implementation.)
- JS code improvements.
- Structural work.

---

## [5.2]

- New widget design.
- Added id tags to Badges Page to serve as link anchors.  
  For example, now if you click on a widget badge, you get redirected and scrolled to that Badge's location in the Badges Page.
- Some other tweaks.

---

## [5.0]

- Badges Page: Badges source/users who received, now displayed in a pop-up instead of a SlideDown toggle below the respective badge.
- Profile Page: Now Badge title shows the number of Badges you've earned for each type of badge. (Ex: Bronze 33, Silver 9, Gold 3)
- Other fixes/arrangements.  
  `Screenshots`_.

---

## [4.9.3]

- Badges counter added next to titles on profile pages. Example: Bronze(6), Silver(1), Gold(3)

---

## [4.9.1]

- Send badge awarded events through the event system - by pvginkel.

---

## [4.9]

- Badges styles fully redesigned.
- Mobile-ready styles.
- Added algorithm to group Badges by "topics" on Badges page.
- Added support for Right-To-Left themes.
- Fixed badges not appearing next to the user on question lists and next to the logged-in text on the navigation bar.




