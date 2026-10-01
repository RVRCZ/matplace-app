<?php

namespace App\Domain\Designer;

use App\Models\CreditTransaction;
use App\Models\DesignerProfile;
use App\Models\Event;
use App\Models\FarmOrder;
use Illuminate\Support\Collection;

/**
 * What the portfolio brings its designer: visits (how many through their own links), prints and rewards.
 * The admin's statistics read the same rows, only for everybody.
 */
final class DesignerStats
{
    /** Order states that count as "printed": the money is the customer's no more. */
    private const PRINTED = [FarmOrder::STATUS_DONE, FarmOrder::STATUS_HANDED_OVER];

    /**
     * @return array{visits: int, via_ref: int, ref_visits: int}
     */
    public function visits(DesignerProfile $profile, int $days): array
    {
        $cards = $profile->models()->pluck('id');
        $views = Event::where('type', Event::VIEW)->where('created_at', '>=', now()->subDays($days))
            ->where(fn ($q) => $q->where(fn ($p) => $p->where('subject_type', 'designer')->where('subject_id', $profile->id))
                ->orWhere(fn ($m) => $m->where('subject_type', 'designer_model')->whereIn('subject_id', $cards)));

        return [
            'visits' => (clone $views)->count(),
            'via_ref' => (clone $views)->where('ref_slug', $profile->slug)->count(),
            // people who arrived through the designer's link, wherever on the site they landed
            'ref_visits' => Event::where('type', Event::REF_VISIT)->where('ref_slug', $profile->slug)->where('created_at', '>=', now()->subDays($days))->count(),
        ];
    }

    /** @return array{orders: int, pieces: int} */
    public function prints(DesignerProfile $profile): array
    {
        $orders = FarmOrder::whereIn('designer_model_id', $profile->models()->select('id'))->whereIn('status', self::PRINTED)->where('user_id', '!=', $profile->user_id);

        return ['orders' => (clone $orders)->count(), 'pieces' => (int) (clone $orders)->sum('copies')];
    }

    /** Rewards credited minus rewards taken back, in the currency of the designer's account. */
    public function rewards(DesignerProfile $profile): float
    {
        return round((float) CreditTransaction::where('user_id', $profile->user_id)->whereIn('type', ['royalty', 'royalty_reversal'])->sum('amount'), 2);
    }

    /** @return Collection<int, CreditTransaction> */
    public function lastRewards(DesignerProfile $profile, int $limit = 10): Collection
    {
        return CreditTransaction::with('order')->where('user_id', $profile->user_id)->whereIn('type', ['royalty', 'royalty_reversal'])->latest('id')->limit($limit)->get();
    }

    /** The three numbers of the card on /account. */
    public function headline(DesignerProfile $profile): array
    {
        return ['visits' => $this->visits($profile, 30)['visits'], 'prints' => $this->prints($profile)['orders'], 'rewards' => $this->rewards($profile)];
    }
}
