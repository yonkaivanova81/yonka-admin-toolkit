# Yonka Admin Toolkit

[![WordPress Plugin](https://img.shields.io/badge/WordPress-Plugin-blue.svg)](https://wordpress.org/plugins/yonka-admin-toolkit/)
[![Stable Version](https://img.shields.io/wordpress/plugin/v/yonka-admin-toolkit.svg)](https://wordpress.org/plugins/yonka-admin-toolkit/)
[![Tested up to](https://img.shields.io/wordpress/plugin/dt/yonka-admin-toolkit.svg)](https://wordpress.org/plugins/yonka-admin-toolkit/)
[![License: GPLv2 or later](https://img.shields.io/badge/License-GPLv2%20or%20later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

All-in-one lightweight WordPress administration utility suite packed with 10 performance, security, SEO, and productivity modules.

---

## Description

**Yonka Admin Toolkit** is a comprehensive, modular utility plugin engineered to optimize, secure, and streamline WordPress administration without bloat. It combines 10 essential administrative and performance modules into a single, cohesive dashboard.

### Included Modules

* **🛠️ Maintenance Mode:** Toggle a customizable maintenance mode with an animated screen to notify visitors during updates.
* **🛡️ Activity Log:** Monitor administrator logins, track failed login attempts, and enforce brute-force security protection.
* **⚡ Asset Cleaner:** Scan frontend JS/CSS scripts and selectively dequeue unused files to improve site speed and PageSpeed scores.
* **🖼️ Media Inventory:** Audit your WordPress media library to locate unoptimized image assets and flag missing ALT attributes for accessibility and SEO.
* **🔗 Broken Links Repair:** Monitor live 404 "Not Found" hits and configure 301 permanent redirects to preserve SEO authority.
* **🔍 System Inspector:** Audit active plugins, registered widgets, and sidebars to identify unused or redundant elements.
* **🗄️ Database Information:** Analyze database size, inspect index health, detect table overhead, and review top storage consumers.
* **📊 Yearly Stats:** Review annual publishing performance across posts, pages, and media attachments.
* **📝 Quick Notes:** Sticky admin notes and task management checklists built right into your dashboard.
* **📢 Marquee Announcement:** Display a customizable, horizontal scrolling top notification banner with custom styles and action links.

---

## Installation

1. Upload the `yonka-admin-toolkit` folder to the `/wp-content/plugins/` directory, or install via **Plugins -> Add New -> Upload Plugin**.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Access the plugin options and individual tools via the **Yonka Admin Toolkit** top-level menu item in your admin sidebar.

---

## Frequently Asked Questions

### Is Yonka Admin Toolkit modular?
Yes. Each module operates independently to keep resource consumption minimal and execution efficient.

### I configured Maintenance Mode, but I don't see any changes on the frontend of the site. Why?
Check if you are currently logged in to your website. If so, log out and reload the page, or open your site in a different browser where you are not logged in.

### Will the Activity Log impact database performance?
No. Log storage is bounded to prevent database bloating, and query execution is optimized for low overhead.

### Can I use Broken Links Repair for external URL redirects?
Yes. The module supports relative internal paths as well as external destination URLs.

---

## Changelog

### 1.0.0
* Initial public release containing all 10 core modules.

---

## License

This project is open-source software licensed under the [GNU General Public License v2.0 (or later)](https://www.gnu.org/licenses/gpl-2.0.html).