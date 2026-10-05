# FoF Scavenger Hunt

A forum-wide game in which members find Parts hidden around the forum and collect them to build a Collectable. Used for seasonal events, all-year fun and commercial promotions.

## Hunts

**Hunt**:
A scheduled campaign in which members collect every Part of one Collectable. Several Hunts may run at the same time.
_Avoid_: Game, Event, Campaign, Quest

**Collectable**:
The thing a Hunt builds, such as a snowman, shown assembling as its Parts are found.
_Avoid_: Kit, Set, Prize, Item

**Template**:
A built-in Collectable design (artwork and Part list) for one Occasion, which a new Hunt is copied from. Editing a Hunt never changes its Template.
_Avoid_: Preset, Default collectable, Theme

**Occasion**:
The season or celebration a Template is designed for, such as winter, Ramadan or New Year.
_Avoid_: Season (too narrow), Holiday, Theme

**Part**:
One piece of a Collectable, such as the carrot nose. Belongs to exactly one Hunt.
_Avoid_: Piece, Component, Item, Collectible

**Asset**:
An image an admin has uploaded for use as a Collectable or Part in any Hunt.
_Avoid_: Upload, File, Media

## Hiding

**Hiding spot**:
A registered place on a forum page where a Part can appear, such as a post's footer or the page corner.
_Avoid_: Slot, Spot (in prose — the short form is fine in code), Position, Mount point

**Location**:
One place a Part may turn up: an optional page, a Hiding spot, and optionally a specific item such as one post.
_Avoid_: Placement (that is the mode), Hiding place

**Placement mode**:
The rule for how a Part uses its Locations: Fixed, Assigned or Wandering.
_Avoid_: Placement type, Strategy

**Fixed**:
The Placement mode in which a Part appears at every one of its Locations, the same for every member. The only mode that can have a Hint.

**Random modes**:
Assigned and Wandering together: the Placement modes in which nobody can be told where to look.
_Avoid_: Random (on its own, as a mode name)

**Assigned**:
The Random mode in which each member is given one of the Part's Locations, chosen secretly and consistently per member.
_Avoid_: Per-user random

**Wandering**:
The Random mode in which a Part has a chance of appearing at any of its Locations on each page view.
_Avoid_: Roaming, Chance, Random spawn

**Hint**:
A clue a Hunt manager writes for a Fixed Part to help members find it.
_Avoid_: Clue, Tip

## Finding

**Encounter**:
The server's decision that a Part is showing in a Hiding spot for one member right now. It carries a Claim token.
_Avoid_: Spawn, Sighting, Appearance

**Claim token**:
The single-use, short-lived secret that lets a member turn an Encounter into a Find.
_Avoid_: Nonce, Key, Code

**Find**:
A member's claimed Part. A member can find each Part once.
_Avoid_: Claim (that is the act), Catch, Collection

**Participant**:
A member with at least one Find in a Hunt.
_Avoid_: Player (any member who may play), Hunter, Entrant

**Completion**:
A member having found every Part of a Hunt. It survives later changes to the Hunt's Parts; only a Progress reset removes it.
_Avoid_: Win, Finish, Trophy (that is how a Completion is displayed)

**Progress reset**:
A Hunt manager removing one member's Finds and Completion from a Hunt, for example after cheating.
_Avoid_: Disqualification, Revoke, Wipe

**First finishers**:
A Hunt's Completions in the order they were earned.
_Avoid_: Leaderboard (that is fof/gamification's forum-wide ranking), Hall of fame, Winners

## Running hunts

**Hunt manager**:
A member allowed to configure Hunts and track their progress. Moderators and admins by default.
_Avoid_: Hunt admin, Organiser, Game master

**Hunt dashboard**:
The forum page where Hunt managers configure Hunts and track Participants.
_Avoid_: Admin page (that is the admin panel), Control panel

## Rewards

**Trophy**:
A Completion as shown on the member's profile, with the finished Collectable.
_Avoid_: Badge, Achievement, Award

**Reward message**:
Text a Hunt manager writes for a Hunt, revealed only to members who earn its Completion, such as a promo code.
_Avoid_: Coupon, Prize text, Secret

**Reward group**:
A group members join automatically when they earn a Hunt's Completion.
_Avoid_: Prize group, Winners group
