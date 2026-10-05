<?php

/*
 * This file is part of fof/scavenger-hunt.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\ScavengerHunt\HuntMap;

/**
 * Everything the encounter lookup needs to know about the Hunts running right now.
 *
 * It holds Hunt seeds and every Location, so it must never leave the server.
 *
 * @phpstan-type HuntEntry array{id: int, slug: string, title: string, guests_can_see: bool, seed: string}
 * @phpstan-type PartEntry array{id: int, hunt_id: int, mode: string, wander_chance: int|null, sort: int}
 * @phpstan-type LocationEntry array{id: int, part_id: int, route: string|null, spot: string, context_type: string|null, context_id: int|null}
 */
final class HuntMap
{
    /**
     * @param array<int, HuntEntry> $hunts
     * @param array<int, PartEntry> $parts
     * @param array<int, list<LocationEntry>> $locations keyed by Part id
     */
    public function __construct(
        public readonly array $hunts,
        public readonly array $parts,
        public readonly array $locations,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->hunts === [];
    }

    /**
     * @return HuntEntry|null
     */
    public function hunt(int $id): ?array
    {
        return $this->hunts[$id] ?? null;
    }

    /**
     * @return PartEntry|null
     */
    public function part(int $id): ?array
    {
        return $this->parts[$id] ?? null;
    }

    /**
     * @return list<int>
     */
    public function partIdsOf(int $huntId): array
    {
        return array_values(array_map(
            fn (array $part) => $part['id'],
            array_filter($this->parts, fn (array $part) => $part['hunt_id'] === $huntId)
        ));
    }

    public function partCount(int $huntId): int
    {
        return count($this->partIdsOf($huntId));
    }

    /**
     * @return list<LocationEntry>
     */
    public function locationsOf(int $partId): array
    {
        return $this->locations[$partId] ?? [];
    }

    /**
     * @return array{hunts: array<int, HuntEntry>, parts: array<int, PartEntry>, locations: array<int, list<LocationEntry>>}
     */
    public function toArray(): array
    {
        return ['hunts' => $this->hunts, 'parts' => $this->parts, 'locations' => $this->locations];
    }

    /**
     * @param array{hunts: array<int, HuntEntry>, parts: array<int, PartEntry>, locations: array<int, list<LocationEntry>>} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['hunts'], $data['parts'], $data['locations']);
    }
}
