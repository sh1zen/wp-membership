=== Members Control ===
Contributors: sh1zen
Tags: membership, member, access-control, newsletter, developer-tool
Donate link: https://www.paypal.com/donate?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+MembersControl.&currency_code=EUR
Requires at least: 5.0.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.1.1
License: GPLv2 or later

The ultimate WordPress solution to manage, protect, and grow your members with powerful levels, access control, and developer-ready tools.

== Description ==

**Members Control** empowers you to build and manage a thriving membership site on WordPress — offering unlimited membership levels, fine-grained content access control, targeted newsletters, and a full suite of developer tools to seamlessly integrate and customize every aspect of your user experience.

**WHY USING Members Control?**

* **Multi-Level Memberships**
  Create unlimited membership levels with independent access rules and pricing.

* **Content Access Control**
  Restrict access by level across any WordPress element — categories, tags, posts, pages, or custom post types.

* **Level-Based Newsletters**
  Send targeted newsletters to members of specific levels for personalized communication.

* **Developer Utilities**
  A rich set of utility functions enables easy customization and integration into themes or custom systems.

**DEVELOPER FUNCTIONS OVERVIEW**


* **`wpmc_get_level($level, $by = 'id')`** - Retrieve data for a specific membership level.
* **`wpmc_get_levels()`** – Retrieve all available membership levels.
* **`wpmc_stats_count_possible_members($wp_roles)`** – Estimate the number of potential members based on WordPress roles.
* **`wpmc_stats_count_members()`** – Count currently active members.
* **`wpmc_subscription_get_users($level_id = 0)`** – Get all users subscribed to a specific membership level.
* **`wpmc_user_get_subscription($user)`** – Retrieve the subscription details of a specific user.
* **`wpmc_register_update($user, $level_id, $action, $paid = 0)`** – Register or update a user’s membership status or action.
* **`wpmc_register_update_field($user, $level_id, $field)`** – Update a specific field in a member’s record.
* **`wpmc_get_member($member)`** – Retrieve complete information about a member.
* **`wpmc_membership_update($user, $level_id, $paid = 0)`** – Upgrade or modify a user’s membership level.
* **`wpmc_membership_extend($user, $days = 0)`** – Extend a user’s membership duration by a number of days.
* **`wpmc_membership_suspend($user)`** – Temporarily suspend a user’s membership.
* **`wpmc_membership_drop($user, $context = 'drop')`** – Remove a user from a membership level.
* **`wpmc_drop_expired_memberships()`** – Automatically remove expired memberships.
* **`wpmc_notify_expiring_members()`** – Notify users whose memberships are nearing expiration.
* **`wpmc_delete_member($user_id, $history = true)`** – Permanently delete a member, with the option to retain history.
* **`wpmc_user_notify($user, $context, $clear_history = false)`** – Send user notifications for specific membership-related events.

**Why Choose Members Control**

* Simplifies membership and subscription management.
* Offers granular access control.
* Provides built-in communication tools.
* Includes a complete developer API for theme and plugin integration.

**Members Control** — the all-in-one solution to manage, protect, and grow your WordPress membership site efficiently.

**DONATIONS**

This plugin is free and always will be, but if you are feeling generous and want to show your support, you can buy me a
beer or coffee [here](https://www.paypal.com/donate/?business=dev.sh1zen%40outlook.it&item_name=Thank+you+in+advanced+for+the+kind+donations.+You+will+sustain+me+developing+MembersControl.&currency_code=EUR), I will really appreciate it.

== Installation ==

This section describes how to install the plugin. In general, there are 3 ways to install this plugin like any other WordPress plugin.

**1. VIA WORDPRESS DASHBOARD**

* Click on ‘Add New’ in the plugins' dashboard
* Search for 'Members Control'
* Click ‘Install Now’ button
* Activate the plugin from the same page or from the Plugins Dashboard

**2. VIA UPLOADING THE PLUGIN TO WORDPRESS DASHBOARD**

* Download the plugin to your computer
  from [https://wordpress.org/plugins/members-control/](https://wordpress.org/plugins/members-control/)
* Click on 'Add New' in the plugins' dashboard
* Click on 'Upload Plugin' button
* Select the zip file of the plugin that you have downloaded to your computer before
* Click 'Install Now'
* Activate the plugin from the Plugins Dashboard

**3. VIA FTP**

* Download the plugin to your computer
  from [https://wordpress.org/plugins/members-control/](https://wordpress.org/plugins/members-control/)
* Unzip the zip file, which will extract the main directory
* Upload the main directory (included inside the extracted folder) to the /wp-content/plugins/ directory of your website
* Activate the plugin from the Plugins Dashboard

**FOR MULTISITE INSTALLATION**

* Log in to your primary site and go to "My Sites" » "Network Admin" » "Plugins"
* Install the plugin following one of the above ways
* Network activate the plugin

**INSTALLATION DONE, A NEW LABEL WILL BE DISPLAYED ON YOUR ADMIN MENU**

== Frequently Asked Questions ==

= What to do if I run in some issues after upgrade? =

Deactivate the plugin and reactivate it, if this doesn't work try to uninstall and reinstall it. That should
work! Otherwise, go to the new added module "Setting" and try a reset.

== Changelog ==

= 1.0.0 =

* initial version