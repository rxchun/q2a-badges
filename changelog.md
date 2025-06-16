<a href="https://www.paypal.com/paypalme/chun128" target="_blank"/>
<img src="https://i.ibb.co/Rz9rfk4/bmc-button.png" border="0" alt="Donate with PayPal!"/>
<a/>

# Q2A Badges Changelog

## [5.2.35] - 2025-06-16

### Smarter database requests
Instead of sending separate requests for each user one by one, we now group them together into a single query batch fetch to work faster and smoother behind the scenes. This reduces the overhead of repeated network/database round trips.  

### Lazy loading for badges
The list of users who earned badges now loads only when you scroll, making the page quicker and easier to navigate.  
(This one has been in the to-do list for a while now, as I've noticed larger websites suffering from this)

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




