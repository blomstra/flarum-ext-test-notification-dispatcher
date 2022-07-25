# Test Notification Dispatcher

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/blomstra/test-notification-dispatcher.svg)](https://packagist.org/packages/blomstra/test-notification-dispatcher) [![Total Downloads](https://img.shields.io/packagist/dt/blomstra/test-notification-dispatcher.svg)](https://packagist.org/packages/blomstra/test-notification-dispatcher)

A [Flarum](https://flarum.org/) extension. CLI to dispatch notifications for development/testing.

Run `php flarum help testnotifications` to get the list of available options.

This extension should probably never be executed in production.
It deletes all the test user's web notifications and might have side effects on other user's notifications if the same event concerns multiple users.

The command works by finding events that have previously triggered the given notifications, and triggering the notifications again.
It results in the web notifications being boosted back to the top and emails being sent again.
The date of the notifications will be inaccurate as it will be reset to the current time.

If more events are found than the given limit (5 by default), then the events to trigger notifications for will pe picked at random.

The notifications are sent to the given test user which can be customized via command parameters.
By default, the user with ID 1 will be used.

The following events/notifications are supported:

- `flarum/core`: Discussion renamed (picks discussions authored by the test user that contain an event post for rename, no matter who renamed it)
- `flarum/suspend`: User suspended (triggers once for the selected test user if they are suspended)
- `flarum/suspend`: User unsuspended (triggers once for the selected test user if they are not suspended)
- `flarum/subscriptions`: New post in followed discussion (will always use the last post of any discussion with one reply or more, even if all the replies are from the test user as well)
- `flarum/mentions`: Post or user mention (any post not created by the test user nor a deleted user)
- `fof/follow-tags`: New post in lurked tag (same constraints as posts in followed discussions)
- `fof/follow-tags`: New discussion in followed or lurked tag (any discussion not created by the test user)

## Installation

Install with composer:

```sh
composer require blomstra/test-notification-dispatcher:"*"
```

## Updating

```sh
composer update blomstra/test-notification-dispatcher
php flarum migrate
php flarum cache:clear
```

## Links

- [Packagist](https://packagist.org/packages/blomstra/test-notification-dispatcher)
- [GitHub](https://github.com/blomstra/test-notification-dispatcher)
- [Discuss](https://discuss.flarum.org/d/PUT_DISCUSS_SLUG_HERE)
