<?php

namespace Modules\Dashboard\Livewire\Forms;

use Illuminate\Support\Collection;
use Livewire\Form;
use Modules\Dashboard\Models\Media;

class ImageForm extends Form
{
    /** @var array<int, mixed>|null */
    public ?array $images = [];

    /** @var array<int, mixed>|null */
    public ?array $prev_images = [];

    /** @var array<int, int|string> */
    public array $deleted_images = [];

    /** Livewire's own upload limit is 12 MB; PHP (upload_max_filesize) has to allow at least this much. */
    public const MAX_KILOBYTES = 12288;

    /**
     * @param  Collection<int, Media>|Media|null  $media
     * @return array<string, mixed>
     */
    public function getImageRules(Collection|Media|null $media = null): array
    {
        $rules = [
            'deleted_images' => 'array',
            'deleted_images.*' => ' integer|exists:Modules\Dashboard\Models\Media,id',
            'images' => 'nullable|array',
            'images.*' => 'nullable|file|mimes:jpeg,jpg,png,gif,svg,webp|max:'.self::MAX_KILOBYTES,
        ];
        if ($media === null) {
            $media = collect();
        } elseif ($media instanceof Media) {
            $media = collect([$media]);
        }

        $deletedImages = $media->pluck('id')->diff(collect($this->prev_images)->pluck('id'));
        foreach ($deletedImages as $key => $id) {
            $this->deleted_images[] = $id;
        }

        return $rules;
    }

    /**
     * @param  Collection<int, mixed>|Media|null  $images  checked to hold only Media below
     *
     * @throws \InvalidArgumentException
     */
    public function setPreviousImages(Collection|Media|null $images): void
    {
        if ($images instanceof Collection && $images->isNotEmpty() && $images->first() instanceof Media === false) {
            throw new \InvalidArgumentException('previous images should be an instance of Media');
        }
        if ($images instanceof Media) {
            $images = collect([$images]);
        } elseif (is_null($images)) {
            $images = collect();
        }
        $this->prev_images = $images->all();
    }
}
