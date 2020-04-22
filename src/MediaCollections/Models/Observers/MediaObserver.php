<?php

namespace Spatie\MediaLibrary\MediaCollections\Models\Observers;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Application;
use Spatie\MediaLibrary\Conversions\FileManipulator;
use Spatie\MediaLibrary\MediaCollections\Filesystem;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaObserver
{
    public function creating(Media $media)
    {
        if ($media->shouldSortWhenCreating()) {
            $media->setHighestOrderNumber();
        }
    }

    public function updating(Media $media)
    {
        if ($media->file_name !== $media->getOriginal('file_name')) {
            /** @var \Spatie\MediaLibrary\MediaCollections\Filesystem $filesystem */
            $filesystem = app(Filesystem::class);

            $filesystem->syncFileNames($media);
        }
    }

    public function updated(Media $media)
    {
        if (is_null($media->getOriginal('model_id'))) {
            return;
        }

        $original = $media->getOriginal('manipulations');

        if (! $this->isLaravel7orHigher()) {
            $original = json_decode($original, true);
        }

        if ($media->manipulations !== $original) {
            $eventDispatcher = Media::getEventDispatcher();
            Media::unsetEventDispatcher();

            /** @var \Spatie\MediaLibrary\Conversions\FileManipulator $fileManipulator */
            $fileManipulator = app(FileManipulator::class);

            $fileManipulator->createDerivedFiles($media);

            Media::setEventDispatcher($eventDispatcher);
        }
    }

    public function deleted(Media $media)
    {
        if (in_array(SoftDeletes::class, class_uses_recursive($media))) {
            if (! $media->isForceDeleting()) {
                return;
            }
        }

        /** @var \Spatie\MediaLibrary\MediaCollections\Filesystem $filesystem */
        $filesystem = app(Filesystem::class);

        $filesystem->removeAllFiles($media);
    }

    private function isLaravel7orHigher(): bool
    {
        preg_match('/\d+[\.]\d+/', app()->version(), $versionArr);

        if (count($versionArr) === 0) {
            return false;
        }

        $currentVersion = (string)$versionArr[0];

        if (version_compare($currentVersion, '7.0', '>=')) {
            return true;
        }

        return false;
    }
}
