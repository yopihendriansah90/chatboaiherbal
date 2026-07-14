<?php

namespace App\Services;

use App\Models\PromptTemplate;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PromptCompiler
{
    public function __construct(private BusinessProfileResolver $businesses) {}

    public function compile(string $domain, string $role, string $coreInstruction): string
    {
        $parts = [$coreInstruction];
        try {
            if (! Schema::hasTable('prompt_templates')) {
                return $coreInstruction;
            }
            $business = $this->businesses->current();
            if (! $business) {
                return $coreInstruction;
            }

            $branding = PromptTemplate::query()
                ->where('business_profile_id', $business->id)
                ->whereNull('domain_pack_id')->where('role', 'branding')->where('is_active', true)
                ->with('publishedVersion')->first();
            if ($branding) {
                $parts[] = $this->content($branding);
            }

            $template = PromptTemplate::query()
                ->where('business_profile_id', $business->id)
                ->where('role', $role)->where('is_active', true)
                ->whereHas('domainPack', fn ($query) => $query->where('code', $domain))
                ->with('publishedVersion')->first();
            if ($template) {
                $parts[] = $this->content($template);
            }
        } catch (Throwable) {
            return $coreInstruction;
        }

        return implode("\n\n", array_values(array_unique(array_filter($parts))));
    }

    private function content(PromptTemplate $template): string
    {
        $custom = trim((string) $template->publishedVersion?->custom_content);
        if ($custom === '') {
            return $template->default_content;
        }

        return $template->is_protected
            ? $template->default_content."\n\nINSTRUKSI CUSTOM YANG DIIZINKAN:\n".$custom
            : $custom;
    }
}
