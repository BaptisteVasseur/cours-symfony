<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ICalImportMessage;
use App\Repository\PropertyICalSyncRepository;
use App\Service\ICalImportService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ICalImportHandler
{
    public function __construct(
        private readonly PropertyICalSyncRepository $syncRepo,
        private readonly ICalImportService $importService,
    ) {
    }

    public function __invoke(ICalImportMessage $message): void
    {
        $sync = $this->syncRepo->find($message->iCalSyncId);

        if ($sync === null) {
            return;
        }

        $this->importService->importFromUrl($sync);
    }
}
