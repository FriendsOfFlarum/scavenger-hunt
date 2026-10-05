<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Notification;

use Flarum\Database\AbstractModel;
use Flarum\Locale\TranslatorInterface;
use Flarum\Notification\AlertableInterface;
use Flarum\Notification\Blueprint\BlueprintInterface;
use Flarum\Notification\MailableInterface;
use Flarum\User\User;
use FoF\ScavengerHunt\Hunt;

class HuntCompletedBlueprint implements BlueprintInterface, AlertableInterface, MailableInterface
{
    public function __construct(
        public Hunt $hunt
    ) {
    }

    public function getSubject(): ?AbstractModel
    {
        return $this->hunt;
    }

    public function getFromUser(): ?User
    {
        return null;
    }

    public function getData(): mixed
    {
        return null;
    }

    public function getEmailViews(): array
    {
        return [
            'text' => 'fof-scavenger-hunt::emails.plain.huntCompleted',
            'html' => 'fof-scavenger-hunt::emails.html.huntCompleted',
        ];
    }

    public function getEmailSubject(TranslatorInterface $translator): string
    {
        return $translator->trans('fof-scavenger-hunt.email.hunt_completed.subject', ['{hunt}' => $this->hunt->title]);
    }

    public static function getType(): string
    {
        return 'scavengerHuntCompleted';
    }

    public static function getSubjectModel(): string
    {
        return Hunt::class;
    }
}
