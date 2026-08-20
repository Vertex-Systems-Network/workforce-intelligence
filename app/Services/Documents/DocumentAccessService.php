<?php

namespace App\Services\Documents;

use App\Models\GeneratedDocument;
use App\Models\WorkspaceMember;

/** Provides document access service behavior within the WorkIntel application. */ class DocumentAccessService
{
    /** Determines whether the can view generated condition is satisfied. */ public function canViewGenerated(WorkspaceMember $actor, GeneratedDocument $document): bool
    {
        return $this->canViewType($actor, $document->document_type);
    }

    /** Determines whether the can view type condition is satisfied. */ public function canViewType(WorkspaceMember $actor, string $type): bool
    {
        return match ($type) {
            'payslip' => $actor->hasPermission('payroll.view_all') || $actor->hasPermission('payroll.manage'),
            'billing_invoice' => $actor->hasPermission('billing.manage'),
            'invoice', 'client_report' => $actor->hasPermission('clients.view') || $actor->hasPermission('clients.manage'),
            'employment_contract', 'offer_letter' => $actor->hasPermission('hris.view_all') || $actor->hasPermission('hris.manage') || $actor->hasPermission('hris.documents.manage'),
            'attendance_report' => $actor->hasPermission('attendance.view_team') || $actor->hasPermission('attendance.manage'),
            'timesheet' => $actor->hasPermission('time.view_team') || $actor->hasPermission('time.view_all') || $actor->hasPermission('time.manage'),
            'purchase_order' => $actor->hasPermission('procurement.view') || $actor->hasPermission('procurement.manage'),
            default => true,
        };
    }

    /** @return array<int,string> */
    /** Handles the visible types operation for the current WorkIntel workflow. */ public function visibleTypes(WorkspaceMember $actor): array
    {
        return array_values(array_filter(array_keys(DocumentTemplateCatalog::TYPES), fn (string $type) => $this->canViewType($actor, $type)));
    }
}
