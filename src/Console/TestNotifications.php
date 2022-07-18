<?php

/*
 * This file is part of blomstra/test-notification-dispatcher.
 *
 *  Copyright (c) 2022 Blomstra Ltd.
 *
 *  For the full copyright and license information, please view the LICENSE.md
 *  file that was distributed with this source code.
 */

namespace Blomstra\NotificationDispatcher\Console;

use Flarum\Discussion\Discussion;
use Flarum\Discussion\UserState;
use Flarum\Extension\ExtensionManager;
use Flarum\Mentions\Notification\PostMentionedBlueprint;
use Flarum\Mentions\Notification\UserMentionedBlueprint;
use Flarum\Notification\Blueprint\DiscussionRenamedBlueprint;
use Flarum\Notification\NotificationSyncer;
use Flarum\Post\DiscussionRenamedPost;
use Flarum\Post\Post;
use Flarum\Subscriptions\Notification\NewPostBlueprint;
use Flarum\Suspend\Notification\UserSuspendedBlueprint;
use Flarum\Suspend\Notification\UserUnsuspendedBlueprint;
use Flarum\Tags\TagState;
use Flarum\User\User;
use FoF\FollowTags\Notifications\NewDiscussionBlueprint;
use FoF\FollowTags\Notifications\NewPostBlueprint as FollowTagsNewPostBlueprint;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class TestNotifications extends Command
{
    protected $signature = 'testnotifications {--user=1} {--count-per-notification=5}';
    protected $description = 'Send dummy notifications to test digest';

    public function handle(ExtensionManager $manager, NotificationSyncer $syncer)
    {
        /**
         * @var User $user
         */
        $user = User::query()->findOrFail($this->option('user'));

        // Delete existing web notifications, otherwise re-sending the same blueprints won't have any effect
        $user->notifications()->delete();

        $this->info("Sending notifications to user {$user->id}");

        $renamedPosts = Post::query()
            ->where('type', DiscussionRenamedPost::$type)
            ->whereHas('discussion', function (Builder $query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->limit($this->option('count-per-notification'))
            ->inRandomOrder()
            ->get();

        if (count($renamedPosts) === 0) {
            $this->warn('None of the user discussions were renamed. Skipping.');
        }

        foreach ($renamedPosts as $post) {
            $syncer->sync(new DiscussionRenamedBlueprint($post), [$user]);

            $this->info('Sending DiscussionRenamedBlueprint');
        }

        if ($manager->isEnabled('flarum-suspend')) {
            if ($user->suspended_until) {
                $syncer->sync(new UserSuspendedBlueprint($user), [$user]);
            } else {
                $syncer->sync(new UserUnsuspendedBlueprint($user), [$user]);
            }
        }

        if ($manager->isEnabled('flarum-subscriptions')) {
            /**
             * @var UserState[] $someFollowedDiscussions
             */
            $someFollowedDiscussions = UserState::query()
                ->where('user_id', $user->id)
                ->where('subscription', 'follow')
                ->whereHas('discussion', function (Builder $query) {
                    $query
                        ->where('comment_count', '>', 1)
                        ->whereNotNull('last_post_id');
                })
                ->limit($this->option('count-per-notification'))
                ->inRandomOrder()
                ->get();

            if (count($someFollowedDiscussions) === 0) {
                $this->warn('User follows no discussions containing replies. Skipping.');
            }

            foreach ($someFollowedDiscussions as $userState) {
                // TODO: don't send a post authored by the user themselves
                $syncer->sync(new NewPostBlueprint($userState->discussion->lastPost), [$user]);

                $this->info('Sending NewPostBlueprint');
            }
        }

        if ($manager->isEnabled('flarum-mentions')) {
            $postsWithUserMention = Post::query()
                ->whereNotNull('user_id') // Guests posts might break Flarum notification template and are normally not possible at creation time
                ->where('user_id', '!=', $user->id) // Don't send notifications about own posts
                ->whereHas('mentionsUsers', function (Builder $query) use ($user) {
                    $query->where('id', $user->id);
                })
                ->limit($this->option('count-per-notification'))
                ->inRandomOrder()
                ->get();

            if (count($postsWithUserMention) === 0) {
                $this->warn('User is not directly mentioned in any post. Skipping.');
            }

            foreach ($postsWithUserMention as $post) {
                $syncer->sync(new UserMentionedBlueprint($post), [$user]);

                $this->info('Sending UserMentionedBlueprint');
            }

            $postsWithPostMention = Post::query()
                ->whereNotNull('user_id') // Guests posts might break Flarum notification template and are normally not possible at creation time
                ->where('user_id', '!=', $user->id) // Don't send notifications about own posts
                ->whereHas('mentionsPosts', function (Builder $query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->limit($this->option('count-per-notification'))
                ->inRandomOrder()
                ->get();

            if (count($postsWithPostMention) === 0) {
                $this->warn('User posts are not mentioned in any post. Skipping.');
            }

            foreach ($postsWithPostMention as $reply) {
                $syncer->sync(new PostMentionedBlueprint($reply->mentionsPosts()->where('user_id', $user->id)->first(), $reply), [$user]);

                $this->info('Sending PostMentionedBlueprint');
            }
        }

        if ($manager->isEnabled('fof-follow-tags')) {
            $lurkedTags = TagState::query()
                ->where('user_id', $user->id)
                ->where('subscription', 'lurk')
                ->pluck('tag_id');

            /**
             * @var Discussion[] $someLurkedTagDiscussions
             */
            $someLurkedTagDiscussions = Discussion::query()
                ->whereHas('tags', function (Builder $query) use ($lurkedTags) {
                    $query->whereIn('id', $lurkedTags);
                })
                ->where('user_id', '!=', $user->id)
                ->where('comment_count', '>', 1)
                ->whereNotNull('last_post_id')
                ->limit($this->option('count-per-notification'))
                ->inRandomOrder()
                ->get();

            if (count($someLurkedTagDiscussions) === 0) {
                $this->warn('User lurks no tags containing replies. Skipping.');
            }

            foreach ($someLurkedTagDiscussions as $discussion) {
                $syncer->sync(new FollowTagsNewPostBlueprint($discussion->lastPost), [$user]);

                $this->info('Sending follow-tags NewPostBlueprint');
            }

            $followedTags = TagState::query()
                ->where('user_id', $user->id)
                ->whereIn('subscription', ['follow', 'lurk'])
                ->pluck('tag_id');

            /**
             * @var Discussion[] $someFollowedTagDiscussions
             */
            $someFollowedTagDiscussions = Discussion::query()
                ->whereHas('tags', function (Builder $query) use ($followedTags) {
                    $query->whereIn('id', $followedTags);
                })
                ->where('user_id', '!=', $user->id)
                ->limit($this->option('count-per-notification'))
                ->inRandomOrder()
                ->get();

            if (count($someFollowedTagDiscussions) === 0) {
                $this->warn('User follows no tags containing discussions. Skipping.');
            }

            foreach ($someFollowedTagDiscussions as $discussion) {
                $syncer->sync(new NewDiscussionBlueprint($discussion), [$user]);

                $this->info('Sending follow-tags NewDiscussionBlueprint');
            }
        }
    }
}
