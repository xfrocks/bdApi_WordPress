# XenForo API Consumer
Contributors: xfrocks
Tags: xenforo, api, login, synchronization, sync, post, comment, user
Requires at least: 3.5
Tested up to: 3.8.1
License: GPLv3

## Description

Connects to XenForo API system.

### Docs & Support

You can find [howto](https://xfrocks.com/api-support/threads/177/), [FAQ](https://xfrocks.com/api-support/threads/178/) and more detailed information about XenForo API Consumer on [xfrocks.com](https://xfrocks.com/forums/16/).

## Changelog

### 1.3.6

* Add support for `add_user_role`
* Improve support for WooCommerce
* Fix create_function deprecation
* Fix bug: not working with lowercase HTTP header key

### 1.3.5

* Fix bug meta box missing for new post

### 1.3.4

* Added manual sync for WordPress post while editing
* Added option to sync user on new XenForo registration
* Improved user sync
* Improved comment sync
* Improved XenForo forum list (respect display order)
* Improved performance

### 1.3.3

* Added support for post & comment search indexing
* Added support for WooCommerce
* Added new widget: Search
* Added advanced option for `curl`
* Added notice for user admin/guest access token
* Improved user sync
* Bug fixes

### 1.3.0

* Added two way post / comment sync
* Added user group sync
* Added support for membership plugins: s2member, MemberPress, Paid Membership Pro
* Added Dashboard feature: connect / disconnect user accounts when edit user
* Added Dashboard tool: auto associate existing WordPress accounts with XenForo’s
* Improved user sync
* Improved cookie sync
* Improved options page UX
* Bug fixes

### 1.1.0

* Added options to control how posts are pushed to XenForo
* Added options to improve seamless login
* Improved notification and conversation bubbles
* Improved option explain text
* Bug fixes