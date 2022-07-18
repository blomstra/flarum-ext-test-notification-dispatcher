<?php

/*
 * This file is part of blomstra/test-notification-dispatcher.
 *
 * Copyright (c) 2022 Blomstra Ltd.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Blomstra\NotificationDispatcher;

use Flarum\Extend;

return [
    (new Extend\Console())
        ->command(Console\TestNotifications::class),
];
