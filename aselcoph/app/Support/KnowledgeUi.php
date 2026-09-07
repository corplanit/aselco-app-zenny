<?php

namespace App\Support;

use App\Models\KnowledgeCategory;
use App\Models\KnowledgeDocument;

class KnowledgeUi
{
    public static function statusTone(?string $status): string
    {
        return match ($status) {
            KnowledgeDocument::STATUS_ACTIVE => 'lime',
            KnowledgeDocument::STATUS_DRAFT => 'amber',
            KnowledgeDocument::STATUS_INACTIVE => 'slate',
            default => 'neutral',
        };
    }

    public static function statusIcon(?string $status): string
    {
        return match ($status) {
            KnowledgeDocument::STATUS_ACTIVE => 'bi-check-circle-fill',
            KnowledgeDocument::STATUS_DRAFT => 'bi-pencil-square',
            KnowledgeDocument::STATUS_INACTIVE => 'bi-pause-circle',
            default => 'bi-circle',
        };
    }

    public static function categoryIcon(?KnowledgeCategory $category): string
    {
        $hay = strtolower(trim(($category?->slug ?? '').' '.($category?->name ?? '')));

        return match (true) {
            str_contains($hay, 'billing') || str_contains($hay, 'rate') => 'bi-receipt',
            str_contains($hay, 'payment') => 'bi-wallet2',
            str_contains($hay, 'outage') || str_contains($hay, 'service-information') => 'bi-lightning-charge',
            str_contains($hay, 'meter') => 'bi-speedometer2',
            str_contains($hay, 'complaint') => 'bi-exclamation-triangle',
            str_contains($hay, 'faq') => 'bi-question-circle',
            str_contains($hay, 'ai') => 'bi-robot',
            str_contains($hay, 'company') || str_contains($hay, 'policy') => 'bi-building',
            str_contains($hay, 'customer-service') || str_contains($hay, 'assistance') => 'bi-headset',
            str_contains($hay, 'customer') || str_contains($hay, 'account') => 'bi-people',
            str_contains($hay, 'connection') || str_contains($hay, 'service') => 'bi-plug',
            default => 'bi-folder2',
        };
    }

    public static function categoryTone(?KnowledgeCategory $category): string
    {
        $hay = strtolower(trim(($category?->slug ?? '').' '.($category?->name ?? '')));

        return match (true) {
            str_contains($hay, 'billing') || str_contains($hay, 'rate') => 'amber',
            str_contains($hay, 'payment') => 'cyan',
            str_contains($hay, 'outage') || str_contains($hay, 'service-information') => 'orange',
            str_contains($hay, 'meter') => 'slate',
            str_contains($hay, 'complaint') => 'rose',
            str_contains($hay, 'faq') => 'teal',
            str_contains($hay, 'ai') => 'violet',
            str_contains($hay, 'company') || str_contains($hay, 'policy') => 'indigo',
            str_contains($hay, 'customer-service') => 'sky',
            str_contains($hay, 'assistance') || str_contains($hay, 'customer') || str_contains($hay, 'account') => 'purple',
            str_contains($hay, 'connection') || str_contains($hay, 'service') => 'lime',
            default => self::toneFromKey($category?->slug ?? $category?->name ?? 'default'),
        };
    }

    public static function toneFromKey(string $key): string
    {
        $tones = ['teal', 'indigo', 'rose', 'sky', 'lime', 'violet', 'amber', 'cyan', 'orange', 'purple'];
        $index = abs(crc32($key)) % count($tones);

        return $tones[$index];
    }

    public static function sourceIcon(?string $source): string
    {
        $hay = strtolower((string) $source);

        return match (true) {
            $hay === '' || str_contains($hay, 'staff') || str_contains($hay, 'internal') => 'bi-person-badge',
            str_contains($hay, 'upload') || str_contains($hay, 'file') => 'bi-file-earmark-text',
            str_contains($hay, 'web') || str_contains($hay, 'official') => 'bi-globe',
            default => 'bi-journal-text',
        };
    }

    /**
     * @return array<string, array{label: string, tone: string, icon: string}>
     */
    public static function serviceTypes(): array
    {
        return [
            'company' => ['label' => 'Company', 'tone' => 'indigo', 'icon' => 'bi-building'],
            'service' => ['label' => 'Service', 'tone' => 'lime', 'icon' => 'bi-plug'],
            'coverage' => ['label' => 'Coverage', 'tone' => 'sky', 'icon' => 'bi-geo-alt'],
            'account' => ['label' => 'Account', 'tone' => 'purple', 'icon' => 'bi-person-vcard'],
            'connection' => ['label' => 'Connection', 'tone' => 'lime', 'icon' => 'bi-plug'],
            'reconnection' => ['label' => 'Reconnection', 'tone' => 'teal', 'icon' => 'bi-arrow-repeat'],
            'billing' => ['label' => 'Billing', 'tone' => 'amber', 'icon' => 'bi-receipt'],
            'payment' => ['label' => 'Payment', 'tone' => 'cyan', 'icon' => 'bi-wallet2'],
            'outage' => ['label' => 'Outage', 'tone' => 'orange', 'icon' => 'bi-lightning-charge'],
            'safety' => ['label' => 'Safety', 'tone' => 'rose', 'icon' => 'bi-shield-exclamation'],
            'meter' => ['label' => 'Meter', 'tone' => 'slate', 'icon' => 'bi-speedometer2'],
            'complaints' => ['label' => 'Complaints', 'tone' => 'rose', 'icon' => 'bi-exclamation-triangle'],
            'assistance' => ['label' => 'Assistance', 'tone' => 'violet', 'icon' => 'bi-headset'],
            'general' => ['label' => 'General', 'tone' => 'teal', 'icon' => 'bi-question-circle'],
            'support' => ['label' => 'Support', 'tone' => 'indigo', 'icon' => 'bi-life-preserver'],
        ];
    }

    /**
     * @return array<string, array{tone: string, icon: string}>
     */
    public static function sources(): array
    {
        return [
            'ASELCO Knowledge Base 1.0' => ['tone' => 'indigo', 'icon' => 'bi-journal-text'],
            'Internal' => ['tone' => 'slate', 'icon' => 'bi-person-badge'],
            'Staff approved' => ['tone' => 'lime', 'icon' => 'bi-shield-check'],
            'Official website' => ['tone' => 'sky', 'icon' => 'bi-globe'],
            'Upload' => ['tone' => 'amber', 'icon' => 'bi-file-earmark-text'],
        ];
    }

    public static function suggestedDepartment(?KnowledgeCategory $category): ?string
    {
        $hay = strtolower(trim(($category?->slug ?? '').' '.($category?->name ?? '')));

        return match (true) {
            str_contains($hay, 'billing') || str_contains($hay, 'payment') || str_contains($hay, 'rate') => 'CCAD',
            str_contains($hay, 'outage') || str_contains($hay, 'meter') => 'TSD',
            str_contains($hay, 'complaint') => 'FOCAL',
            str_contains($hay, 'faq') || str_contains($hay, 'ai') => 'CSR',
            str_contains($hay, 'company') || str_contains($hay, 'customer') || str_contains($hay, 'connection') => 'AO-CDS',
            default => null,
        };
    }
}
