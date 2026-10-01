<?php

namespace App\Engines\Import;

/**
 * A site where designers publish models (Printables, MakerWorld). We read what is public: the card of a model,
 * and where the site allows it a profile and the list of an author's models. Files are never fetched: both sites
 * give them only to their own logged-in users.
 */
interface ModelSource
{
    /** printables | makerworld */
    public function key(): string;

    /** Id of a model from its address on this site; null when the address is not a model of this site. */
    public function modelId(string $url): ?string;

    /** Handle from the address of a profile on this site; null when the address is not a profile. */
    public function handle(string $url): ?string;

    /** The public profile with its bio, or null when the site does not show profiles to us. */
    public function profile(string $handle): ?SourceProfile;

    /**
     * The author's published models, newest first.
     *
     * @return list<array{id: string, url: string, title: string, image: ?string, is_remix: bool}>|null null = the site has no list we can read
     */
    public function models(string $authorId, int $limit): ?array;

    /** @throws ImportFailed */
    public function fetch(string $modelId): ImportedModel;
}
