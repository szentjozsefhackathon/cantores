<?php

namespace App\Http\Requests;

use App\Models\Music;
use App\Models\MusicPlanSlotPlan;
use Illuminate\Validation\Validator;

/**
 * A music to add to a deck from the remote — at the end of a slot, or at the end
 * of the deck when no slot is given.
 */
class ProjectionAddedMusicRequest extends ProjectionDeckEditRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'music_id' => ['required', 'integer'],
            'slot_plan_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'music_id.required' => __('Choose a music to add.'),
            'music_id.integer' => __('Choose a music to add.'),
            'slot_plan_id.integer' => __('That slot is not in this plan.'),
        ];
    }

    /**
     * The music must be one this person can see, and the slot one of this deck's
     * own plan — a heading is read by a whole congregation.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                if ($this->music() === null) {
                    $validator->errors()->add('music_id', __('Choose a music to add.'));
                }

                if ($this->input('slot_plan_id') !== null && $this->slotPlanId() === null) {
                    $validator->errors()->add('slot_plan_id', __('That slot is not in this plan.'));
                }
            },
        ];
    }

    public function music(): ?Music
    {
        return Music::query()->visibleTo($this->user())->find((int) $this->input('music_id'));
    }

    public function slotPlanId(): ?int
    {
        $id = $this->input('slot_plan_id');
        $planId = $this->projection()->music_plan_id;

        if ($id === null || $planId === null) {
            return null;
        }

        return MusicPlanSlotPlan::query()
            ->whereKey((int) $id)
            ->where('music_plan_id', $planId)
            ->value('id');
    }
}
