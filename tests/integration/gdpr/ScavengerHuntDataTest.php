<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\Tests\integration\gdpr;

use Carbon\Carbon;
use Flarum\Gdpr\DataProcessor;
use Flarum\Gdpr\Models\ErasureRequest;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\ScavengerHunt\Data\ScavengerHunt;
use FoF\ScavengerHunt\Tests\integration\InteractsWithHunts;
use PHPUnit\Framework\Attributes\Test;

class ScavengerHuntDataTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use InteractsWithHunts;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-gdpr', 'fof-scavenger-hunt');

        $this->setting('mail_driver', 'log');
        $this->setting('flarum-gdpr.allow-deletion', true);

        $this->prepareDatabase([
            'users' => [$this->normalUser(), $this->secondMember()],
            'fof_scavenger_hunts' => [$this->huntRow()],
            'fof_scavenger_parts' => [
                $this->partRow(['id' => 1, 'name' => 'Carrot nose']),
                $this->partRow(['id' => 2, 'name' => 'Top hat', 'sort' => 1]),
            ],
            'fof_scavenger_locations' => [
                $this->locationRow(['id' => 1, 'part_id' => 1]),
                $this->locationRow(['id' => 2, 'part_id' => 2]),
            ],
            'fof_scavenger_encounters' => [
                $this->encounterRow(['id' => 1, 'part_id' => 1, 'location_id' => 1, 'user_id' => 2, 'created_at' => '2026-11-01 10:00:00', 'claimed_at' => '2026-11-01 10:00:05']),
                $this->encounterRow(['id' => 2, 'part_id' => 2, 'location_id' => 2, 'user_id' => 2, 'created_at' => '2026-11-01 11:00:00', 'claimed_at' => '2026-11-01 11:00:04']),
                $this->encounterRow(['id' => 3, 'part_id' => 1, 'location_id' => 1, 'user_id' => 2, 'created_at' => '2026-11-02 09:00:00']),
                $this->encounterRow(['id' => 4, 'part_id' => 1, 'location_id' => 1, 'user_id' => 4, 'created_at' => '2026-11-02 09:30:00', 'claimed_at' => '2026-11-02 09:30:03']),
            ],
            'fof_scavenger_finds' => [
                ['id' => 1, 'part_id' => 1, 'user_id' => 2, 'found_at' => '2026-11-01 10:00:05'],
                ['id' => 2, 'part_id' => 2, 'user_id' => 2, 'found_at' => '2026-11-01 11:00:04'],
                ['id' => 3, 'part_id' => 1, 'user_id' => 4, 'found_at' => '2026-11-02 09:30:03'],
            ],
            'fof_scavenger_completions' => [
                ['id' => 1, 'hunt_id' => 1, 'user_id' => 2, 'completed_at' => '2026-11-01 11:00:04'],
            ],
            'gdpr_erasure' => [
                ['id' => 1, 'user_id' => 2, 'verification_token' => 'abc123', 'status' => ErasureRequest::STATUS_USER_CONFIRMED, 'created_at' => Carbon::now(), 'user_confirmed_at' => Carbon::now()],
            ],
        ]);
    }

    protected function encounterRow(array $overrides): array
    {
        $id = $overrides['id'];

        return array_merge([
            'token_hash' => hash('sha256', "token-$id"),
            'issued_at_ms' => 0,
            'expires_at' => '2026-11-01 10:10:00',
            'claimed_at' => null,
            'claim_ms' => null,
        ], $overrides);
    }

    protected function processErasure(string $mode): void
    {
        $response = $this->send($this->request('PATCH', '/api/user-erasure-requests/1', [
            'authenticatedAs' => 1,
            'json' => ['data' => ['attributes' => ['processorComment' => 'Done', 'processedMode' => $mode]]],
        ]));

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    protected function dataType(int $userId): ScavengerHunt
    {
        return $this->app()->getContainer()->make(ScavengerHunt::class, ['user' => User::findOrFail($userId), 'erasureRequest' => null]);
    }

    protected function rowsOf(string $table): array
    {
        return $this->database()->table($table)->orderBy('id')->get()
            ->map(fn ($row) => [(int) $row->id, (int) $row->user_id])->all();
    }

    #[Test]
    public function the_data_type_is_registered_with_gdpr(): void
    {
        $this->assertArrayHasKey(ScavengerHunt::class, $this->app()->getContainer()->make(DataProcessor::class)->types());
    }

    #[Test]
    public function the_descriptions_are_translated(): void
    {
        $this->app();
        $this->loadTranslations();

        $this->assertSame('Exports the parts the user found, the hunts they completed and the parts they were shown.', ScavengerHunt::exportDescription());
        $this->assertSame('Deletes the record of the parts the user was shown. Found parts and completed hunts are kept under the anonymized account.', ScavengerHunt::anonymizeDescription());
        $this->assertSame('Deletes the parts the user found, the hunts they completed and the record of the parts they were shown.', ScavengerHunt::deleteDescription());
        $this->assertSame([], ScavengerHunt::piiFields());
    }

    #[Test]
    public function the_export_lists_the_members_finds_completions_and_encounters(): void
    {
        $export = array_map(fn (string $json) => json_decode($json, true), $this->dataType(2)->export());

        $this->assertSame([
            'scavenger-hunt/finds.json' => [
                ['hunt' => 'Build a Snowman', 'part' => 'Carrot nose', 'found_at' => '2026-11-01T10:00:05+00:00'],
                ['hunt' => 'Build a Snowman', 'part' => 'Top hat', 'found_at' => '2026-11-01T11:00:04+00:00'],
            ],
            'scavenger-hunt/completions.json' => [
                ['hunt' => 'Build a Snowman', 'completed_at' => '2026-11-01T11:00:04+00:00'],
            ],
            'scavenger-hunt/encounters.json' => [
                ['hunt' => 'Build a Snowman', 'part' => 'Carrot nose', 'shown_at' => '2026-11-01T10:00:00+00:00', 'claimed_at' => '2026-11-01T10:00:05+00:00'],
                ['hunt' => 'Build a Snowman', 'part' => 'Top hat', 'shown_at' => '2026-11-01T11:00:00+00:00', 'claimed_at' => '2026-11-01T11:00:04+00:00'],
                ['hunt' => 'Build a Snowman', 'part' => 'Carrot nose', 'shown_at' => '2026-11-02T09:00:00+00:00', 'claimed_at' => null],
            ],
        ], $export);
    }

    #[Test]
    public function anonymizing_removes_encounters_and_keeps_finds_and_completions(): void
    {
        $this->processErasure(ErasureRequest::MODE_ANONYMIZATION);

        $this->assertSame([[4, 4]], $this->rowsOf('fof_scavenger_encounters'));
        $this->assertSame([[1, 2], [2, 2], [3, 4]], $this->rowsOf('fof_scavenger_finds'));
        $this->assertSame([[1, 2]], $this->rowsOf('fof_scavenger_completions'));

        $finishers = $this->body($this->send($this->request('GET', '/api/scavenger-hunt-completions', ['authenticatedAs' => 4])
            ->withQueryParams(['filter' => ['hunt' => '1'], 'include' => 'user'])));

        $this->assertSame(['2'], array_map(fn (array $completion) => $completion['relationships']['user']['data']['id'], $finishers['data']));
    }

    #[Test]
    public function deleting_removes_encounters_finds_and_completions(): void
    {
        // Called directly: in a deletion-mode erasure the user's foreign-key cascades would hide a no-op delete().
        $this->dataType(2)->delete();

        $this->assertNotNull(User::find(2));
        $this->assertSame([[4, 4]], $this->rowsOf('fof_scavenger_encounters'));
        $this->assertSame([[3, 4]], $this->rowsOf('fof_scavenger_finds'));
        $this->assertSame([], $this->rowsOf('fof_scavenger_completions'));
    }

    #[Test]
    public function a_deletion_mode_erasure_succeeds(): void
    {
        $this->processErasure(ErasureRequest::MODE_DELETION);

        $this->assertNull(User::find(2));
        $this->assertSame([[4, 4]], $this->rowsOf('fof_scavenger_encounters'));
        $this->assertSame([[3, 4]], $this->rowsOf('fof_scavenger_finds'));
        $this->assertSame([], $this->rowsOf('fof_scavenger_completions'));
    }
}
