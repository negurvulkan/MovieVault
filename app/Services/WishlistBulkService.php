<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use App\Repositories\WishlistRepository;
use Throwable;

final class WishlistBulkService
{
    private const MAX_TARGETS = 500;

    public function __construct(
        private readonly WishlistRepository $wishlist,
        private readonly WishlistService $service
    ) {
    }

    public function preview(
        string $action,
        string $selectionMode,
        array $selectedIds,
        array $filters,
        array $payload,
        int $userId
    ): array {
        $items = $this->resolveItems($selectionMode, $selectedIds, $filters, $userId);
        if ($items === []) {
            throw new ValidationException(['Keine passenden Wunsch-Eintraege fuer die Bulk-Aktion gefunden.']);
        }

        $preparedPayload = [];
        $payloadLines = [];
        $warnings = [];
        $resolvedIds = [];
        $previewItems = [];

        if (in_array($action, ['mark_reserved', 'mark_bought', 'mark_dropped'], true)) {
            foreach ($items as $item) {
                [$status, $message] = $this->previewStatusAction($action, $item);
                $previewItems[] = $this->previewItem($item, $status, $message);
                if ($status === 'ready') {
                    $resolvedIds[] = (int) $item['id'];
                }
            }
        } elseif ($action === 'change_priority') {
            $preparedPayload = $this->normalizePriorityPayload($payload);
            $payloadLines = ['Neue Prioritaet: ' . $this->priorityLabel($preparedPayload['priority'])];

            foreach ($items as $item) {
                if (($item['priority'] ?? '') === $preparedPayload['priority']) {
                    $previewItems[] = $this->previewItem($item, 'skip', 'Eintrag hat bereits diese Prioritaet.');
                    continue;
                }

                $previewItems[] = $this->previewItem($item, 'ready', 'Prioritaet wird aktualisiert.');
                $resolvedIds[] = (int) $item['id'];
            }
        } elseif ($action === 'change_target_format') {
            $preparedPayload = $this->normalizeFormatPayload($payload);
            $payloadLines = ['Neues Wunschformat: ' . $this->formatLabel($preparedPayload['target_format'])];

            foreach ($items as $item) {
                if (($item['target_format'] ?? '') === $preparedPayload['target_format']) {
                    $previewItems[] = $this->previewItem($item, 'skip', 'Eintrag hat bereits dieses Wunschformat.');
                    continue;
                }

                $previewItems[] = $this->previewItem($item, 'ready', 'Wunschformat wird aktualisiert.');
                $resolvedIds[] = (int) $item['id'];
            }
        } elseif ($action === 'convert_to_catalog') {
            foreach ($items as $item) {
                if (!empty($item['is_converted'])) {
                    $previewItems[] = $this->previewItem($item, 'skip', 'Eintrag wurde bereits in die Sammlung uebernommen.');
                    continue;
                }
                if (($item['status'] ?? '') === 'dropped') {
                    $previewItems[] = $this->previewItem($item, 'skip', 'Verworfene Eintraege werden uebersprungen.');
                    continue;
                }

                $message = 'Eintrag wird in den Katalog uebernommen und als physisches Medium angelegt.';
                if (($item['target_format'] ?? 'dvd') === 'any') {
                    $message .= ' Format-Fallback: DVD.';
                }

                $previewItems[] = $this->previewItem($item, 'ready', $message);
                $resolvedIds[] = (int) $item['id'];
            }
            $warnings[] = 'Beim Uebernehmen werden vorhandene Katalogtitel und Exemplare per Dublettenlogik wiederverwendet.';
        } elseif ($action === 'delete_wishes') {
            foreach ($items as $item) {
                $previewItems[] = $this->previewItem($item, 'ready', 'Eintrag wird aus der Wunschliste entfernt.');
                $resolvedIds[] = (int) $item['id'];
            }
        } else {
            throw new ValidationException(['Unbekannte Bulk-Aktion fuer die Wunschliste.']);
        }

        return [
            'module' => 'wishlist',
            'action' => $action,
            'action_label' => $this->actionLabel($action),
            'resolved_ids' => $resolvedIds,
            'payload' => $preparedPayload,
            'payload_lines' => $payloadLines,
            'items' => $previewItems,
            'summary' => [
                'affected' => count($previewItems),
                'executable' => count($resolvedIds),
                'skipped' => max(0, count($previewItems) - count($resolvedIds)),
            ],
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    public function execute(array $snapshot, int $userId): array
    {
        $action = (string) ($snapshot['action'] ?? '');
        $resolvedIds = array_values(array_map('intval', (array) ($snapshot['resolved_ids'] ?? [])));
        $payload = (array) ($snapshot['payload'] ?? []);

        return match ($action) {
            'mark_reserved' => $this->statusResult($this->wishlist->updateStatuses($resolvedIds, 'reserved', $userId), count($resolvedIds)),
            'mark_bought' => $this->statusResult($this->wishlist->updateStatuses($resolvedIds, 'bought', $userId), count($resolvedIds)),
            'mark_dropped' => $this->statusResult($this->wishlist->updateStatuses($resolvedIds, 'dropped', $userId), count($resolvedIds)),
            'change_priority' => $this->statusResult($this->wishlist->updatePriority($resolvedIds, (string) $payload['priority'], $userId), count($resolvedIds)),
            'change_target_format' => $this->statusResult($this->wishlist->updateTargetFormat($resolvedIds, (string) $payload['target_format'], $userId), count($resolvedIds)),
            'delete_wishes' => $this->statusResult($this->wishlist->deleteItems($resolvedIds, $userId), count($resolvedIds)),
            'convert_to_catalog' => $this->executeConvert($resolvedIds, $userId),
            default => throw new ValidationException(['Unbekannte Bulk-Aktion fuer die Wunschliste.']),
        };
    }

    private function executeConvert(array $itemIds, int $userId): array
    {
        $processed = 0;
        $skipped = 0;
        $failed = 0;
        $warnings = [];

        foreach ($itemIds as $itemId) {
            try {
                $result = $this->service->convert($itemId, $userId);
                $processed++;
                foreach ((array) ($result['warnings'] ?? []) as $warning) {
                    $warnings[] = (string) $warning;
                }
            } catch (ValidationException $exception) {
                $skipped++;
                foreach ($exception->errors() as $error) {
                    $warnings[] = (string) $error;
                }
            } catch (Throwable $throwable) {
                $failed++;
                $warnings[] = 'Mindestens ein Wunsch-Eintrag konnte nicht uebernommen werden.';
            }
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function resolveItems(string $selectionMode, array $selectedIds, array $filters, int $userId): array
    {
        if ($selectionMode === 'filtered') {
            $items = $this->wishlist->listItems($userId, $filters);
        } elseif ($selectionMode === 'ids') {
            $items = $this->wishlist->findItemsByIds($selectedIds, $userId);
        } else {
            throw new ValidationException(['Die Auswahlart fuer die Bulk-Aktion ist ungueltig.']);
        }

        if (count($items) > self::MAX_TARGETS) {
            throw new ValidationException(['Es duerfen hoechstens 500 Wunsch-Eintraege auf einmal verarbeitet werden.']);
        }

        return $items;
    }

    private function normalizePriorityPayload(array $payload): array
    {
        $priority = trim((string) ($payload['priority'] ?? ''));
        if (!in_array($priority, ['low', 'medium', 'high'], true)) {
            throw new ValidationException(['Bitte eine gueltige Prioritaet fuer die Bulk-Aktion waehlen.']);
        }

        return ['priority' => $priority];
    }

    private function normalizeFormatPayload(array $payload): array
    {
        $targetFormat = trim((string) ($payload['target_format'] ?? ''));
        if (!in_array($targetFormat, ['dvd', 'bluray', 'any'], true)) {
            throw new ValidationException(['Bitte ein gueltiges Wunschformat fuer die Bulk-Aktion waehlen.']);
        }

        return ['target_format' => $targetFormat];
    }

    private function previewStatusAction(string $action, array $item): array
    {
        $status = (string) ($item['status'] ?? 'open');

        return match ($action) {
            'mark_reserved' => match ($status) {
                'reserved' => ['skip', 'Eintrag ist bereits reserviert.'],
                'bought' => ['skip', 'Bereits gekaufte Eintraege werden nicht erneut reserviert.'],
                'dropped' => ['skip', 'Verworfene Eintraege werden nicht reserviert.'],
                default => ['ready', 'Eintrag wird als reserviert markiert.'],
            },
            'mark_bought' => match ($status) {
                'bought' => ['skip', 'Eintrag ist bereits als gekauft markiert.'],
                'dropped' => ['skip', 'Verworfene Eintraege werden nicht als gekauft markiert.'],
                default => ['ready', 'Eintrag wird als gekauft markiert.'],
            },
            'mark_dropped' => match ($status) {
                'dropped' => ['skip', 'Eintrag ist bereits verworfen.'],
                default => ['ready', 'Eintrag wird verworfen.'],
            },
            default => ['skip', 'Aktion nicht verfuegbar.'],
        };
    }

    private function previewItem(array $item, string $status, string $message): array
    {
        return [
            'id' => (int) $item['id'],
            'label' => $this->itemLabel($item),
            'detail' => $this->itemDetail($item),
            'status' => $status,
            'message' => $message,
        ];
    }

    private function itemLabel(array $item): string
    {
        if (($item['kind'] ?? 'movie') === 'season') {
            return sprintf(
                '%s - Staffel %s',
                $item['series_title'] ?: $item['title'],
                $item['season_number'] ?: '?'
            );
        }

        return (string) $item['title'];
    }

    private function itemDetail(array $item): string
    {
        return sprintf(
            '%s, %s, Liste: %s',
            $this->priorityLabel((string) ($item['priority'] ?? 'medium')),
            ucfirst((string) ($item['status'] ?? 'open')),
            (string) ($item['list_name'] ?? '-')
        );
    }

    private function priorityLabel(string $priority): string
    {
        return match ($priority) {
            'high' => 'Hoch',
            'low' => 'Niedrig',
            default => 'Mittel',
        };
    }

    private function formatLabel(string $format): string
    {
        return match ($format) {
            'bluray' => 'Blu-ray',
            'any' => 'Egal',
            default => 'DVD',
        };
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'mark_reserved' => 'Wunsch-Eintraege reservieren',
            'mark_bought' => 'Wunsch-Eintraege als gekauft markieren',
            'mark_dropped' => 'Wunsch-Eintraege verwerfen',
            'change_priority' => 'Prioritaet aendern',
            'change_target_format' => 'Wunschformat aendern',
            'convert_to_catalog' => 'In Sammlung uebernehmen',
            'delete_wishes' => 'Wunsch-Eintraege loeschen',
            default => 'Bulk-Aktion',
        };
    }

    private function statusResult(int $processed, int $expected): array
    {
        return [
            'processed' => $processed,
            'skipped' => max(0, $expected - $processed),
            'failed' => 0,
            'warnings' => [],
        ];
    }
}
