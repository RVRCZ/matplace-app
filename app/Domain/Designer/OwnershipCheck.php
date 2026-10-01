<?php

namespace App\Domain\Designer;

use App\Engines\Import\ImportFailed;
use App\Engines\Import\Sources;
use App\Models\DesignerProfile;

/**
 * "Is this Printables / MakerWorld account yours?" The designer puts a token we show them where only the owner
 * of that account can write, and we look for it there:
 *   - in the bio of the profile (Printables), or
 *   - in the description of one of their models (both sites; the only way on MakerWorld, which shows us no profiles).
 * The token can be removed again afterwards. What we keep is who the author is at the source; every imported card
 * must come from that author.
 */
final class OwnershipCheck
{
    public function __construct(private readonly Sources $sources) {}

    /**
     * @return array{ok: bool, error?: string} error = a code for designer.verify.error.<code>
     */
    public function verify(DesignerProfile $profile, string $sourceKey, string $url): array
    {
        $source = $this->sources->get($sourceKey);
        $token = $profile->tokenFor($sourceKey);
        $url = trim($url);
        try {
            if ($handle = $source->handle($url)) {
                $remote = $source->profile($handle);
                if (! $remote) {
                    return ['ok' => false, 'error' => 'use_model'];   // this site shows us no profiles: use a model's address
                }
                if (! str_contains($remote->bio, $token)) {
                    return ['ok' => false, 'error' => 'token_missing'];
                }

                return $this->accept($profile, $sourceKey, $remote->id, $remote->handle, $remote->url);
            }
            if ($id = $source->modelId($url)) {
                $model = $source->fetch($id);
                if (! str_contains($model->descriptionHtml, $token)) {
                    return ['ok' => false, 'error' => 'token_missing'];
                }
                if ($model->authorId === '') {
                    return ['ok' => false, 'error' => 'unreadable'];
                }

                return $this->accept($profile, $sourceKey, $model->authorId, $model->authorName, null);
            }
        } catch (ImportFailed $e) {
            return ['ok' => false, 'error' => $e->reason];
        }

        return ['ok' => false, 'error' => 'bad_url'];
    }

    /** @return array{ok: bool, error?: string} */
    private function accept(DesignerProfile $profile, string $source, string $id, string $handle, ?string $profileUrl): array
    {
        // one account at the source belongs to one designer here
        $idColumn = $source === 'printables' ? 'printables_user_id' : 'makerworld_uid';
        if (DesignerProfile::where($idColumn, $id)->whereKeyNot($profile->id)->exists()) {
            return ['ok' => false, 'error' => 'taken'];
        }
        $links = (array) $profile->links;
        if ($profileUrl && empty($links[$source])) {
            $links[$source] = $profileUrl;
        }
        $profile->forceFill([
            $idColumn => $id,
            ($source === 'printables' ? 'printables_username' : 'makerworld_handle') => $handle,
            $source.'_verified_at' => now(),
            'links' => $links,
        ])->save();

        return ['ok' => true];
    }
}
