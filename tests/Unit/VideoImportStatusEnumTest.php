<?php

use App\Enums\VideoImportStatusEnum;

it('returns labels for every case', function (VideoImportStatusEnum $case) {
    expect($case->label())->toBeString()->not->toBeEmpty();
})->with(VideoImportStatusEnum::cases());

it('returns color class for every case', function (VideoImportStatusEnum $case) {
    expect($case->colorClass())->toBeString()->not->toBeEmpty();
})->with(VideoImportStatusEnum::cases());

it('marks ready and failed as final', function () {
    expect(VideoImportStatusEnum::Ready->isFinal())->toBeTrue()
        ->and(VideoImportStatusEnum::Failed->isFinal())->toBeTrue()
        ->and(VideoImportStatusEnum::Pending->isFinal())->toBeFalse()
        ->and(VideoImportStatusEnum::Fetching->isFinal())->toBeFalse();
});
