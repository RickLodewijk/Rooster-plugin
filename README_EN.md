# Rooster Planner

Rooster Planner is a robust WordPress plugin that allows employees to easily submit their availability and time slots through a secure front-end page. Administrators have full control via a comprehensive backend dashboard to define standard schedules, configure exceptional days (such as holidays or events), and view total staffing levels at a glance.

## 🚀 Features

- **Automated Setup:** Upon activation, the plugin automatically creates the required pages (Login, Form, Overview) with the correct shortcodes and metadata pre-configured.
- **Dynamic Date Tracking:** Dates are automatically calculated based on the ISO week number and are displayed clearly within both the submission form and the overview table.
- **Flexible Availability:** Optionally supports a "Maybe" status per time slot (configurable by the administrator).
- **Exceptions & Closures:** Easily manage specific dates that deviate from the standard schedule, or completely close specific days.
- **Role-Based Access Control:** Introduces a custom `Medewerker rooster` (`medewerker_rooster`) user role to strictly secure form access.

---

## 🛠️ Installation & Setup

1. Download or clone this repository into your `/wp-content/plugins/rooster-planner/` directory.
2. Ensure the file structure looks as follows:
```text
   rooster-planner/
   ├── includes/
   │   ├── trait-admin.php
   │   ├── trait-data.php
   │   └── trait-frontend.php
   └── rooster-planner.php (Main plugin file)

```

3. Navigate to your WordPress Dashboard -> **Plugins** and activate **Rooster Planner**.
4. **Done!** The plugin will instantly generate the following pages within your WordPress site:
* `Rooster Inloggen` (contains the `[rooster_login]` shortcode)
* `Rooster Formulier` (renders the availability input form)
* `Rooster Overzicht` (renders the complete matrix table showing total attendance)



> **Tip:** If you activated the plugin prior to uploading the latest code, simply **Deactivate** and **Re-activate** the plugin in your dashboard to trigger the automated page setup.

---

## 📖 Usage

### For Employees

1. Navigate to the **Rooster Formulier** page on the front-end.
2. If you are not logged in, you will be automatically redirected to the **Rooster Inloggen** page.
3. Select the desired week from the dropdown menu (defaults to looking up to 4 weeks ahead).
4. Fill in your availability per day using the checkboxes or radio buttons, and click **Opslaan** (Save).

### For Administrators (Admin Dashboard)

A new main menu item named **Rooster** will appear in your WordPress sidebar:

* **Instellingen (Settings):** Toggle general options, such as enabling or disabling the *"Misschien" (Maybe)* choice for time slots.
* **Standaard roosterindeling (Default Schedule):** Define repeating standard time slots per day of the week (e.g., *Ochtend (09:00–13:00)* or *Middag (13:00–17:00)*).
* **Afwijkende dagen (Exceptional Days):** Add custom dates that override the standard schedule, or check *"Gesloten" (Closed)* to fully lock down a specific date.
* **Inzendingen (Submissions):** View or edit raw database submissions entered by your staff.

---

## 🔑 Shortcodes

If you wish to display the layouts manually on other custom pages, you can utilize the following shortcodes:

| Shortcode | Description |
| --- | --- |
| `[rooster_login]` | Displays the custom employee login form (and seamlessly redirects users back to the schedule form after logging in). |
| `[rooster_overview]` | Displays the full matrix table grid outlining all schedules alongside total counts per day. |

---

## 💻 Technical Architecture & Code Structure

The plugin utilizes PHP **Traits** to keep the codebase clean, highly modular, and easy to maintain:

* **`Rooster_Planner` (Main File):** Handles plugin activation hooks, shortcode registrations, init routines, and global class constants (e.g., `WEEKS_AHEAD = 4`).
* **`Rooster_Planner_Admin`:** Handles backend settings pages, custom fields processing, and the page-specific Meta Boxes (allowing you to manually flip any page into a form/overview template).
* **`Rooster_Planner_Frontend`:** Manages front-end form rendering, form validation, security tokens (nonces), core layout CSS styling, and dynamic header date injection.
* **`Rooster_Planner_Data`:** The engine under the hood. Computes exact calendar dates based on time zones and week strings, checks exceptions, and queries the database (`rooster_submission` post-type).

---

## 🛡️ Security & Sanitation

* All form input values are strictly sanitized using `sanitize_text_field()` and `sanitize_meta_field()`.
* Front-end forms are fully secured against CSRF attacks via native WordPress **Nonces** (`wp_verify_nonce`).
* Direct file access is blocked globally via `ABSPATH` checks.
* Sensitive rendering areas strictly verify user permissions against the custom `rooster_access` capability.

## 📝 License

Created by Rick Lodewijk