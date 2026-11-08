<?php

namespace App\Domain\Farm;

use App\Models\FarmColor;
use App\Models\FarmColorPhotoProposal;
use App\Models\FarmOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Photos of our own prints offered as the pictures of the colours they were printed in (TODO #71 D, docs/AL.md §21.7).
 * When an order gets its photo (PrintPhotos::attach), every colour of the order – the first, a second, the colours of
 * the changes and of the parts – that has no `print` photo yet and no pending proposal from this order gets one: the
 * order's web JPEG copied to farm/colors/proposals/ (the order's folder goes after a week, matplace:prune and
 * PrintPhotos::remove; the copy stays). The admin says yes or no in the materials list: yes makes it the colour's
 * photo (`photo_source` print; an e-shop photo it replaces is remembered in source_meta.eshop_photo), no deletes the copy.
 */
final class ColorPhotoProposals
{
    public const DIR = 'farm/colors/proposals';

    /** The longest side of a colour's photo, as the Drive import makes them. */
    public const PHOTO_PX = 800;

    /** @return list<FarmColorPhotoProposal> the proposals made now */
    public function proposeFor(FarmOrder $order): array
    {
        $disk = Storage::disk('public');
        if (! $order->photo_path || ! $disk->exists($order->photo_path)) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', array_merge(
            [$order->farm_color_id, $order->second_color_id], array_column($order->colorSlots(), 'color_id'), array_column($order->partPlates(), 'color_id')
        )))));
        if ($ids === []) {
            return [];
        }
        $pending = FarmColorPhotoProposal::where('farm_order_id', $order->id)->where('status', FarmColorPhotoProposal::STATUS_PENDING)->pluck('farm_color_id')->map(fn ($id) => (int) $id)->flip();
        $out = [];
        foreach (FarmColor::whereIn('id', $ids)->orderBy('id')->get() as $color) {
            // a colour photographed already keeps its photo; a proposal from this very order is still waiting
            if (($color->photo_source === FarmColor::PHOTO_PRINT && $color->photo_path) || isset($pending[$color->id])) {
                continue;
            }
            $path = self::DIR.'/'.$order->token.'-'.$color->id.'-'.Str::lower(Str::random(6)).'.jpg';
            $disk->put($path, (string) $disk->get($order->photo_path));
            $out[] = FarmColorPhotoProposal::create([
                'farm_color_id' => $color->id, 'farm_order_id' => $order->id, 'path' => $path, 'status' => FarmColorPhotoProposal::STATUS_PENDING,
                'note' => $order->number ? 'zakázka '.$order->number : null,
            ]);
        }

        return $out;
    }

    /**
     * The admin's yes: the proposal becomes the colour's photo (800 px JPEG in farm/colors/, `photo_source` print).
     * An e-shop photo it replaces is kept in source_meta.eshop_photo, an older photo of ours in source_meta.previous_photo
     * (the files stay); an older print photo gives way. Other pending proposals of the colour keep waiting.
     */
    public function approve(FarmColorPhotoProposal $proposal, ?int $by = null): FarmColor
    {
        $disk = Storage::disk('public');
        $color = $proposal->color;
        $rel = 'farm/colors/'.Str::uuid().'.jpg';
        $disk->put($rel, self::resized($disk->path($proposal->path)) ?? (string) $disk->get($proposal->path));
        $meta = (array) $color->source_meta;
        if ($color->photo_path && $color->photo_path !== $rel) {
            if ($color->photo_source === FarmColor::PHOTO_ESHOP) {
                $meta['eshop_photo'] = $color->photo_path;
            } elseif ($color->photo_source === FarmColor::PHOTO_PRINT) {
                $disk->delete($color->photo_path);
            } else {
                $meta['previous_photo'] = $color->photo_path;
            }
        }
        $color->forceFill(['photo_path' => $rel, 'photo_source' => FarmColor::PHOTO_PRINT, 'source_meta' => $meta ?: null])->save();
        $proposal->forceFill(['status' => FarmColorPhotoProposal::STATUS_APPROVED, 'decided_at' => now(), 'decided_by' => $by])->save();
        $disk->delete($proposal->path);

        return $color;
    }

    /** The admin's no: the copy goes, the row remembers the decision. */
    public function reject(FarmColorPhotoProposal $proposal, ?int $by = null): void
    {
        $proposal->forceFill(['status' => FarmColorPhotoProposal::STATUS_REJECTED, 'decided_at' => now(), 'decided_by' => $by])->save();
        Storage::disk('public')->delete($proposal->path);
    }

    /** @return Collection<int, Collection<int, FarmColorPhotoProposal>> the waiting proposals by colour id */
    public static function pending(): Collection
    {
        return FarmColorPhotoProposal::with('order')->where('status', FarmColorPhotoProposal::STATUS_PENDING)->orderBy('id')->get()->groupBy('farm_color_id');
    }

    public static function pendingCount(): int
    {
        return FarmColorPhotoProposal::where('status', FarmColorPhotoProposal::STATUS_PENDING)->count();
    }

    /** The web render (1200 × 800) brought to 800 px on the longer side, as every other colour photo. */
    private static function resized(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! is_file($path)) {
            return null;
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if (! $src) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1.0, self::PHOTO_PX / max($w, $h));
        $dst = imagecreatetruecolor(max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale)));
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $h);
        ob_start();
        imagejpeg($dst, null, 82);
        imagedestroy($src);
        imagedestroy($dst);

        return (string) ob_get_clean();
    }
}
